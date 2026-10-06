<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
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
        'hub_id',
        'email_verified_at',
    ];

    // Keeps $user->role in JSON output and toArray(), where the old string
    // column used to appear.
    protected $appends = ['role'];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
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

    public function hub()
    {
        return $this->belongsTo(Hub::class);
    }

    /**
     * Roles that oversee the whole company rather than a single branch.
     *
     * Without a bypass nobody could administer the system: an SA restricted to
     * one hub cannot see the stock, machines or requests they are responsible
     * for approving.
     */
    public const HUB_UNRESTRICTED_ROLES = ['SA', 'Developer', 'Admin'];

    public function seesAllHubs(): bool
    {
        return in_array($this->role, self::HUB_UNRESTRICTED_ROLES, true);
    }

    /**
     * The hub id a query should be limited to, or null for no limit at all.
     *
     * Fails closed on purpose. A restricted user with no hub set returns 0,
     * which matches no row, so they see nothing until an admin assigns them a
     * branch. Returning null there would have quietly granted them the
     * company-wide view that null means for administrators — the exact
     * opposite of what an unconfigured account should get.
     */
    public function hubScope(): ?int
    {
        if ($this->seesAllHubs()) {
            return null;
        }

        return $this->hub_id ?? 0;
    }

    /**
     * Whether this account sees head-office data.
     *
     * Defective items and maintenance contracts are handled centrally, not per
     * branch. Administrative roles see them because they see everything; a
     * branch user sees them only if their own hub is flagged as head office.
     *
     * A restricted account with no hub is excluded, like everywhere else.
     */
    public function atHeadOffice(): bool
    {
        if ($this->seesAllHubs()) {
            return true;
        }

        return $this->hub_id !== null
            && Hub::whereKey($this->hub_id)->where('is_head_office', true)->exists();
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
