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
        'geofence_radius_m',
        'shift_start',
        'shift_end',
        'description',
        'status',
        'kiosk_token',
        'supervisor_id',
        'site_manager_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->kiosk_token) && \Illuminate\Support\Facades\Schema::hasColumn('sites', 'kiosk_token')) {
                $site->kiosk_token = \Illuminate\Support\Str::random(48);
            }
        });
    }

    public function ensureKioskToken(): string
    {
        if (empty($this->kiosk_token)) {
            $this->kiosk_token = \Illuminate\Support\Str::random(48);
            if (\Illuminate\Support\Facades\Schema::hasColumn('sites', 'kiosk_token')) {
                $this->saveQuietly();
            }
        }

        return (string) $this->kiosk_token;
    }

    public function rotateKioskToken(): string
    {
        $this->kiosk_token = \Illuminate\Support\Str::random(48);
        if (\Illuminate\Support\Facades\Schema::hasColumn('sites', 'kiosk_token')) {
            $this->saveQuietly();
        }

        return (string) $this->kiosk_token;
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius_m' => 'integer',
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

    public function linkedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_sites')
            ->withPivot(['company_id', 'start_date', 'end_date', 'is_primary'])
            ->withTimestamps();
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function siteManager(): BelongsTo
    {
        if (!empty($this->attributes['site_manager_id'])) {
            return $this->belongsTo(User::class, 'site_manager_id');
        }

        return $this->supervisor();
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_site')
            ->withPivot('role_in_site')
            ->withTimestamps();
    }

    public function siteAssignments(): HasMany
    {
        return $this->hasMany(SiteAssignment::class)->orderByDesc('is_active')->orderByDesc('start_date');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function projectDocuments(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
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

    /**
     * Calculate Haversine distance in meters from this site's coordinates to a given GPS point.
     */
    public function distanceMetersFrom(float $lat, float $lng): ?float
    {
        if (!$this->hasValidCoordinates()) {
            return null;
        }

        $earthRadiusMeters = 6371000.0;
        $latFrom = deg2rad((float) $this->latitude);
        $lonFrom = deg2rad((float) $this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($angle * $earthRadiusMeters, 2);
    }

    public function isWithinGeofence(float $lat, float $lng): bool
    {
        $distance = $this->distanceMetersFrom($lat, $lng);
        if ($distance === null) {
            return true;
        }

        $radius = (int) ($this->geofence_radius_m ?: 200);
        return $distance <= $radius;
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

    public function getSiteManagerIdAttribute(?int $value = null): ?int
    {
        return $value ?? ($this->attributes['site_manager_id'] ?? ($this->attributes['supervisor_id'] ?? null));
    }
}
