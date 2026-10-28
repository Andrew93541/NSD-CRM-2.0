<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'project_id',
        'code',
        'name',
        'location',
        'address',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'description',
        'status',
        'supervisor_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_site')
            ->withPivot('role_in_site')
            ->withTimestamps();
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class, 'assigned_site_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function hasValidCoordinates(): bool
    {
        if ($this->latitude === null || $this->longitude === null) {
            return false;
        }

        if (!is_numeric($this->latitude) || !is_numeric($this->longitude)) {
            return false;
        }

        $lat = (float) $this->latitude;
        $lng = (float) $this->longitude;

        return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
    }

    public function mapUrl(): ?string
    {
        if (!$this->hasValidCoordinates()) {
            return null;
        }

        $lat = number_format((float) $this->latitude, 6, '.', '');
        $lng = number_format((float) $this->longitude, 6, '.', '');

        return "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lng}#map=16/{$lat}/{$lng}";
    }

    public function formattedAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->location,
            $this->city,
            $this->state,
            $this->postal_code,
        ], fn ($val) => $val !== null && trim((string) $val) !== '');

        return implode(', ', array_unique($parts));
    }

    // Compatibility aliases for supervisor_id / site_manager_id
    public function getSiteManagerIdAttribute(): ?int
    {
        return $this->supervisor_id;
    }

    public function setSiteManagerIdAttribute(?int $value): void
    {
        $this->attributes['supervisor_id'] = $value;
    }

    public function siteManager(): BelongsTo
    {
        return $this->supervisor();
    }
}
