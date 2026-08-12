<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';
    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'role',
        'role_id',
        'is_active',
    ];

    // Keeps $user->role in JSON output and toArray(), where the old string
    // column used to appear.
    protected $appends = ['role'];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function isActive()
    {
        return $this->is_active === 1 || $this->is_active === true;
    }

    /**
     * The role a user holds, as its stable code (e.g. 'SA', 'ServiceDesk').
     *
     * users.role used to be a plain string; it is now a foreign key to roles.
     * This accessor keeps `$user->role` returning — and accepting — the code, so
     * every existing gate and `['role' => 'Admin']` assignment still works.
     * Use userRole() when you want the Role model itself.
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn () => Role::codeFor($this->role_id),
            set: fn ($value) => ['role_id' => Role::idForCode($value)],
        );
    }

    public function userRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** Filter users by role code, replacing the old where('role', ...). */
    public function scopeWithRole($query, string $code)
    {
        return $query->where('role_id', Role::idForCode($code));
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * True for Super Admin and Developer. Developer sits at or above SA, so
     * gating on isSA() grants it access to every module automatically —
     * including ones added later.
     */
    public function isSA()
    {
        return in_array($this->role, ['SA', 'Developer'], true);
    }

    public function isDeveloper()
    {
        return $this->role === 'Developer';
    }

    public function isAdmin()
    {
        return $this->role === 'Admin';
    }

    public function isEngineer()
    {
        return $this->role === 'Engineer';
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function receivings()
    {
        return $this->hasMany(Receiving::class, 'created_by');
    }

    public function partRequests()
    {
        return $this->hasMany(PartRequest::class, 'requested_by');
    }

    public function machineRequests()
    {
        return $this->hasMany(MachineRequest::class, 'requested_by');
    }

    public function machines()
    {
        return $this->hasMany(Machine::class, 'created_by');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'created_by');
    }

    public function defectiveItems()
    {
        return $this->hasMany(DefectiveItem::class, 'engineer_id');
    }

    public function costHistory()
    {
        return $this->hasMany(CostHistory::class, 'updated_by');
    }
}
