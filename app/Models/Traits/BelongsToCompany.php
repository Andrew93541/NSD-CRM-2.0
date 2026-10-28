<?php

namespace App\Models\Traits;

use App\Models\Company;
use App\Services\Security\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $companyId = TenantContext::getCompanyId();
                if ($companyId) {
                    $model->company_id = $companyId;
                } elseif (! TenantContext::isBypassed()) {
                    throw new \RuntimeException(
                        'Cannot create record for ' . static::class . ' without an explicit or resolved company context.'
                    );
                }
            }
        });

        static::addGlobalScope('company_scope', function (Builder $builder) {
            if (TenantContext::isBypassed()) {
                return;
            }

            $companyId = TenantContext::getCompanyId();
            if ($companyId) {
                $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
            } else {
                // Fail closed when company context cannot be resolved safely
                $builder->whereRaw('1 = 0');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
