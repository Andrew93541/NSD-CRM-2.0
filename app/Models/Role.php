<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, BelongsToCompany;

    protected $guarded = ["id"];

    protected $casts = [
        "permissions" => "array",
    ];

    public function hasPermission(string $permission): bool
    {
        $perms = $this->permissions ?? [];
        if (in_array("*", $perms, true)) {
            return true;
        }

        return in_array($permission, $perms, true);
    }
}
