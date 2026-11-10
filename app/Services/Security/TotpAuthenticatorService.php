<?php

namespace App\Services\Security;

use App\Support\QrCodeSvgGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * RFC 6238 Time-Based One-Time Password (TOTP) Service (HMAC-SHA1, 6 digits, 30-second window).
 * Compatible with Google Authenticator, Microsoft Authenticator, Authy, 1Password, and Apple Passwords.
 */
class TotpAuthenticatorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically random RFC 4648 Base32 secret key.
     */
    public function generateSecret(int $length = 32): string
    {
        if ($length < 16 || $length > 64) {
            throw new InvalidArgumentException('TOTP secret length must be between 16 and 64 Base32 characters.');
        }

        $secret = '';
        $alphabetLength = strlen(self::BASE32_ALPHABET);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }

    /**
     * Build a standard otpauth://totp/ URI for authenticator app enrollment.
     */
    public function buildOtpAuthUri(
        string $issuer,
        string $accountName,
        string $secret,
        int $period = 30,
        int $digits = 6
    ): string {
        $cleanIssuer = trim($issuer) !== '' ? trim($issuer) : 'NSD CRM 2.0';
        $cleanAccount = trim($accountName);
        $label = rawurlencode($cleanIssuer) . ':' . rawurlencode($cleanAccount);

        $query = http_build_query([
            'secret' => strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret)),
            'issuer' => $cleanIssuer,
            'algorithm' => 'SHA1',
            'digits' => $digits,
            'period' => $period,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Compute the 6-digit RFC 6238 TOTP code for a given Base32 secret and Unix timestamp.
     */
    public function generateCode(
        string $secret,
        ?int $timestamp = null,
        int $period = 30,
        int $digits = 6
    ): string {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, $period);

        // 64-bit big-endian counter
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $binaryKey = $this->decodeBase32($secret);

        $hash = hash_hmac('sha1', $binaryCounter, $binaryKey, true);
        $offset = ord($hash[19]) & 0x0F;

        $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binaryCode % (10 ** $digits);

        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Resolve the matching RFC 6238 time-step counter within +/- $window steps, or null if invalid.
     */
    public function resolveMatchingTimeStep(
        string $secret,
        string $code,
        int $window = 1,
        ?int $timestamp = null,
        int $period = 30,
        int $digits = 6
    ): ?int {
        $normalizedSecret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
        if (strlen($normalizedSecret) < 16) {
            return null;
        }

        $normalizedCode = preg_replace('/\s+/', '', trim($code));
        if (!preg_match('/^\d{' . $digits . '}$/', $normalizedCode)) {
            return null;
        }

        $timestamp = $timestamp ?? time();
        $baseStep = intdiv($timestamp, $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $stepTimestamp = ($baseStep + $offset) * $period;
            $candidate = $this->generateCode(
                $normalizedSecret,
                $stepTimestamp,
                $period,
                $digits
            );

            if (hash_equals($candidate, $normalizedCode)) {
                return $baseStep + $offset;
            }
        }

        return null;
    }

    /**
     * Verify a submitted 6-digit TOTP code within +/- $window time steps (default +/- 30s).
     */
    public function verifyCode(
        string $secret,
        string $code,
        int $window = 1,
        ?int $timestamp = null,
        int $period = 30,
        int $digits = 6
    ): bool {
        return $this->resolveMatchingTimeStep($secret, $code, $window, $timestamp, $period, $digits) !== null;
    }

    /**
     * Verify a submitted 6-digit TOTP code with RFC 6238 Section 5.2 replay protection:
     * atomically and durably updates `users.two_factor_last_step` in the database using a single
     * conditional UPDATE (`WHERE two_factor_last_step IS NULL OR two_factor_last_step < :step`),
     * preventing concurrent race conditions and surviving `php artisan cache:clear`.
     */
    public function verifyCodeWithReplayProtection(
        int|string $userId,
        string $secret,
        string $code,
        int $window = 1,
        ?int $timestamp = null,
        int $period = 30,
        int $digits = 6
    ): bool {
        $matchedStep = $this->resolveMatchingTimeStep($secret, $code, $window, $timestamp, $period, $digits);
        if ($matchedStep === null) {
            return false;
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'two_factor_last_step')) {
            $affectedRows = DB::table('users')
                ->where('id', $userId)
                ->where(function ($query) use ($matchedStep) {
                    $query->whereNull('two_factor_last_step')
                        ->orWhere('two_factor_last_step', '<', (int) $matchedStep);
                })
                ->update([
                    'two_factor_last_step' => (int) $matchedStep,
                    'updated_at' => now(),
                ]);

            if ($affectedRows !== 1) {
                return false;
            }

            Cache::put(self::replayCacheKey($userId), (int) $matchedStep, now()->addMinutes(10));

            return true;
        }

        $cacheKey = self::replayCacheKey($userId);
        $lastAcceptedStep = Cache::get($cacheKey);

        if ($lastAcceptedStep !== null && (int) $matchedStep <= (int) $lastAcceptedStep) {
            return false;
        }

        Cache::put($cacheKey, (int) $matchedStep, now()->addMinutes(10));

        return true;
    }

    /**
     * Clear the stored TOTP replay time-step for a user (e.g., on administrator 2FA reset).
     */
    public function clearReplayState(int|string $userId): void
    {
        Cache::forget(self::replayCacheKey($userId));

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'two_factor_last_step')) {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'two_factor_last_step' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public static function replayCacheKey(int|string $userId): string
    {
        return 'totp_last_accepted_step:' . $userId;
    }

    /**
     * Generate a local inline SVG QR code for an otpauth:// provisioning URI without external API calls.
     */
    public function generateQrSvg(string $otpAuthUri, int $sizePx = 180): string
    {
        return QrCodeSvgGenerator::generateSvg($otpAuthUri, $sizePx);
    }

    /**
     * Decode an RFC 4648 Base32 string into raw binary bytes.
     */
    public function decodeBase32(string $secret): string
    {
        $cleaned = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
        if ($cleaned === '') {
            return '';
        }

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        $length = strlen($cleaned);
        for ($i = 0; $i < $length; $i++) {
            $val = strpos(self::BASE32_ALPHABET, $cleaned[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}

