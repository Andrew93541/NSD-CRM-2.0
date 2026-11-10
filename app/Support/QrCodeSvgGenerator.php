<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Pure-PHP ISO/IEC 18004 QR Code SVG Generator (Byte Mode, Error Correction Level L, Versions 1–10).
 * Generates local inline SVG QR codes for RFC 6238 otpauth:// URIs with zero external dependencies or network requests.
 */
class QrCodeSvgGenerator
{
    /**
     * Version parameters for Error Correction Level L (Versions 1 to 10):
     * [total_data_codewords, ec_codewords_per_block, [[num_blocks, data_codewords_per_block], ...], [alignment_centers]]
     */
    private const VERSION_SPEC_L = [
        1 => [19, 7, [[1, 19]], []],
        2 => [34, 10, [[1, 34]], [6, 18]],
        3 => [55, 15, [[1, 55]], [6, 22]],
        4 => [80, 20, [[1, 80]], [6, 26]],
        5 => [108, 26, [[1, 108]], [6, 30]],
        6 => [136, 18, [[2, 68]], [6, 34]],
        7 => [156, 20, [[2, 78]], [6, 22, 38]],
        8 => [194, 24, [[2, 97]], [6, 24, 42]],
        9 => [232, 30, [[2, 116]], [6, 26, 46]],
        10 => [274, 18, [[2, 68], [2, 69]], [6, 28, 50]],
    ];

    /**
     * Precomputed 18-bit BCH(18,6) version info strings for Versions 7–10.
     */
    private const VERSION_INFO_BITS = [
        7 => 0x07C94,
        8 => 0x085BC,
        9 => 0x09A99,
        10 => 0x0A4D3,
    ];

    /**
     * Generate a standalone inline <svg> QR Code for the given payload string.
     */
    public static function generateSvg(string $payload, int $sizePx = 180, int $marginModules = 4): string
    {
        $bytes = array_values(unpack('C*', $payload));
        $byteLen = count($bytes);

        if ($byteLen === 0 || $byteLen > 271) {
            throw new InvalidArgumentException('QR payload length must be between 1 and 271 bytes.');
        }

        $version = self::selectVersion($byteLen);
        [$totalDataCw, $ecCwPerBlock, $blockGroups, $alignCenters] = self::VERSION_SPEC_L[$version];

        $dataCodewords = self::encodeByteModeData($bytes, $version, $totalDataCw);
        $finalCodewords = self::interleaveWithErrorCorrection($dataCodewords, $ecCwPerBlock, $blockGroups);

        $matrixSize = 17 + ($version * 4);
        [$matrix, $reserved] = self::createFunctionPatternMatrix($version, $matrixSize, $alignCenters);

        self::placeCodewords($matrix, $reserved, $matrixSize, $finalCodewords);

        // Apply Mask Pattern 0: (row + col) % 2 === 0 and write Format Info for Level L (01) + Mask 0 (000) -> 0x77C4
        self::applyMaskZero($matrix, $reserved, $matrixSize);
        self::placeFormatInformation($matrix, $matrixSize, 0x77C4);

        return self::renderMatrixToSvg($matrix, $matrixSize, $sizePx, $marginModules);
    }

    private static function selectVersion(int $byteLen): int
    {
        foreach (self::VERSION_SPEC_L as $ver => $spec) {
            $totalDataCw = $spec[0];
            $headerBytes = $ver <= 9 ? 2 : 3; // 4-bit mode + 8/16-bit length + 4-bit terminator
            if ($byteLen <= ($totalDataCw - $headerBytes)) {
                return $ver;
            }
        }

        throw new InvalidArgumentException('Payload exceeds Version 10-L capacity.');
    }

    private static function encodeByteModeData(array $bytes, int $version, int $totalDataCw): array
    {
        $bits = '0100'; // Byte mode indicator
        $lenBits = $version <= 9 ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $lenBits, '0', STR_PAD_LEFT);

        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }

        $maxBits = $totalDataCw * 8;
        $terminator = min(4, $maxBits - strlen($bits));
        if ($terminator > 0) {
            $bits .= str_repeat('0', $terminator);
        }

        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        $codewords = [];
        for ($i = 0, $n = strlen($bits); $i < $n; $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($codewords) < $totalDataCw) {
            $codewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        return $codewords;
    }

    private static function interleaveWithErrorCorrection(array $dataCodewords, int $ecCwPerBlock, array $blockGroups): array
    {
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        foreach ($blockGroups as [$numBlocks, $cwPerBlock]) {
            for ($b = 0; $b < $numBlocks; $b++) {
                $block = array_slice($dataCodewords, $offset, $cwPerBlock);
                $offset += $cwPerBlock;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::computeReedSolomon($block, $ecCwPerBlock);
            }
        }

        $interleaved = [];
        $maxDataLen = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $interleaved[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecCwPerBlock; $i++) {
            foreach ($ecBlocks as $ecBlock) {
                $interleaved[] = $ecBlock[$i];
            }
        }

        return $interleaved;
    }

    private static function computeReedSolomon(array $data, int $degree): array
    {
        [$exp, $log] = self::gfTables();

        // Build generator polynomial: prod_{i=0..degree-1} (x - alpha^i)
        $gen = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            for ($j = 0, $n = count($gen); $j < $n; $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j + 1] ^= self::gfMul($gen[$j], $exp[$i], $exp, $log);
            }
            $gen = $next;
        }

        $rem = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                for ($j = 0; $j < $degree; $j++) {
                    $rem[$j] ^= self::gfMul($gen[$j + 1], $factor, $exp, $log);
                }
            }
        }

        return $rem;
    }

    private static function gfTables(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        return $cached = [$exp, $log];
    }

    private static function gfMul(int $a, int $b, array $exp, array $log): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return $exp[$log[$a] + $log[$b]];
    }

    private static function createFunctionPatternMatrix(int $version, int $size, array $alignCenters): array
    {
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder patterns + separators at (0,0), (0, size-7), (size-7, 0)
        self::placeFinderPattern($matrix, $reserved, $size, 0, 0);
        self::placeFinderPattern($matrix, $reserved, $size, 0, $size - 7);
        self::placeFinderPattern($matrix, $reserved, $size, $size - 7, 0);

        // 2. Alignment patterns
        $numCenters = count($alignCenters);
        for ($i = 0; $i < $numCenters; $i++) {
            for ($j = 0; $j < $numCenters; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $numCenters - 1) || ($i === $numCenters - 1 && $j === 0)) {
                    continue;
                }
                self::placeAlignmentPattern($matrix, $reserved, $alignCenters[$i], $alignCenters[$j]);
            }
        }

        // 3. Timing patterns on row 6 and col 6
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $bit;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $bit;
                $reserved[$i][6] = true;
            }
        }

        // 4. Dark module at (4 * version + 9, 8)
        $matrix[(4 * $version) + 9][8] = 1;
        $reserved[(4 * $version) + 9][8] = true;

        // 5. Reserve format information areas
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // 6. Version information for Versions 7..10
        if ($version >= 7 && isset(self::VERSION_INFO_BITS[$version])) {
            $vBits = self::VERSION_INFO_BITS[$version];
            for ($i = 0; $i < 18; $i++) {
                $bit = ($vBits >> $i) & 1;
                $r = intdiv($i, 3);
                $c = ($size - 11) + ($i % 3);
                $matrix[$r][$c] = $bit;
                $reserved[$r][$c] = true;
                $matrix[$c][$r] = $bit;
                $reserved[$c][$r] = true;
            }
        }

        return [$matrix, $reserved];
    }

    private static function placeFinderPattern(array &$matrix, array &$reserved, int $size, int $topR, int $leftC): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $topR + $r;
                $cc = $leftC + $c;
                if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                    continue;
                }
                $inOuter = ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6) && ($r === 0 || $r === 6 || $c === 0 || $c === 6);
                $inInner = ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                $matrix[$rr][$cc] = ($inOuter || $inInner) ? 1 : 0;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeAlignmentPattern(array &$matrix, array &$reserved, int $centerR, int $centerC): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $rr = $centerR + $r;
                $cc = $centerC + $c;
                $isDark = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                $matrix[$rr][$cc] = $isDark ? 1 : 0;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeCodewords(array &$matrix, array $reserved, int $size, array $codewords): void
    {
        $bits = [];
        foreach ($codewords as $cw) {
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($cw >> $b) & 1;
            }
        }

        $bitIdx = 0;
        $numBits = count($bits);
        $upward = true;

        for ($rightCol = $size - 1; $rightCol >= 1; $rightCol -= 2) {
            if ($rightCol === 6) {
                $rightCol = 5; // Skip vertical timing pattern column
            }

            for ($step = 0; $step < $size; $step++) {
                $r = $upward ? ($size - 1 - $step) : $step;
                for ($cOffset = 0; $cOffset < 2; $cOffset++) {
                    $c = $rightCol - $cOffset;
                    if (!$reserved[$r][$c]) {
                        $matrix[$r][$c] = $bitIdx < $numBits ? $bits[$bitIdx++] : 0;
                    }
                }
            }

            $upward = !$upward;
        }
    }

    private static function applyMaskZero(array &$matrix, array $reserved, int $size): void
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$reserved[$r][$c] && (($r + $c) % 2 === 0)) {
                    $matrix[$r][$c] ^= 1;
                }
            }
        }
    }

    private static function placeFormatInformation(array &$matrix, int $size, int $formatBits): void
    {
        // Copy 1: around top-left finder
        $coords1 = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
            [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
        ];
        // Copy 2: bottom-left and top-right
        $coords2 = [
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8], [$size - 5, 8], [$size - 6, 8], [$size - 7, 8],
            [8, $size - 8], [8, $size - 7], [8, $size - 6], [8, $size - 5], [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1],
        ];

        for ($i = 0; $i < 15; $i++) {
            $bit = ($formatBits >> (14 - $i)) & 1;
            [$r1, $c1] = $coords1[$i];
            [$r2, $c2] = $coords2[$i];
            $matrix[$r1][$c1] = $bit;
            $matrix[$r2][$c2] = $bit;
        }
    }

    private static function renderMatrixToSvg(array $matrix, int $size, int $sizePx, int $margin): string
    {
        $totalModules = $size + ($margin * 2);
        $pathData = '';

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($matrix[$r][$c] === 1) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $pathData .= "M{$x},{$y}h1v1h-1z";
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img" aria-label="Authenticator App TOTP QR Code"><rect width="100%%" height="100%%" fill="#ffffff"/><path d="%3$s" fill="#0f172a"/></svg>',
            $totalModules,
            $sizePx,
            $pathData
        );
    }
}

