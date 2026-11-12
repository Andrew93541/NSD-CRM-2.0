<?php

namespace App\Services\Security;

class TenantContext
{
    protected static ?int $companyId = null;
    protected static bool $bypassed = false;
    protected static ?string $verifiedKey = null;

    public static function setCompanyId(?int $id): void
    {
        static::$companyId = $id;
        static::$verifiedKey = null;
    }

    public static function reset(): void
    {
        static::$companyId = null;
        static::$bypassed = false;
        static::$verifiedKey = null;
    }

    public static function clear(): void
    {
        static::$companyId = null;
        static::$bypassed = false;
        static::$verifiedKey = null;
        if (session()) {
            session()->forget('current_company_id');
        }
    }

    public static function getCompanyId(): ?int
    {
        if (static::$companyId !== null) {
            if (auth()->check()) {
                $user = auth()->user();
                $reqId = app()->bound('request') ? spl_object_id(request()) : 0;
                $cacheKey = "{$reqId}:{$user->id}:" . static::$companyId;

                if ($reqId > 0 && static::$verifiedKey === $cacheKey) {
                    return static::$companyId;
                }

                $isValid = $user->companies()
                    ->where('companies.id', static::$companyId)
                    ->wherePivot('status', 'active')
                    ->where('companies.is_active', true)
                    ->exists();

                if ($isValid) {
                    if ($reqId > 0) {
                        static::$verifiedKey = $cacheKey;
                    }
                    return static::$companyId;
                }

                static::$companyId = null;
                static::$verifiedKey = null;
            } else {
                return static::$companyId;
            }
        }

        if (auth()->check()) {
            $user = auth()->user();

            if (session() && session()->has('current_company_id')) {
                $sessionCompanyId = (int) session()->get('current_company_id');
                $isValid = $user->companies()
                    ->where('companies.id', $sessionCompanyId)
                    ->wherePivot('status', 'active')
                    ->where('companies.is_active', true)
                    ->exists();

                if ($isValid) {
                    static::$companyId = $sessionCompanyId;
                    return static::$companyId;
                } else {
                    session()->forget('current_company_id');
                }
            }

            $primaryCompany = $user->companies()
                ->wherePivot('status', 'active')
                ->where('companies.is_active', true)
                ->orderByDesc('company_user.is_primary')
                ->first();

            if ($primaryCompany) {
                static::$companyId = $primaryCompany->id;
                if (session()) {
                    session()->put('current_company_id', $primaryCompany->id);
                }
                return static::$companyId;
            }
        }

        return null;
    }

    public static function bypass(callable $callback)
    {
        $previous = static::$bypassed;
        static::$bypassed = true;
        try {
            return $callback();
        } finally {
            static::$bypassed = $previous;
        }
    }

    public static function isBypassed(): bool
    {
        return static::$bypassed;
    }
}
