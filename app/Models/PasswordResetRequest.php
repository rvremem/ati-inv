<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetRequest extends Model
{
    protected $table = 'password_reset_requests';

    /** The table has created_at only — nothing ever updates a pending reset. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Matches the signed link's own lifetime, and Laravel's verification default. */
    public const EXPIRES_MINUTES = 60;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->created_at === null
            || $this->created_at->addMinutes(self::EXPIRES_MINUTES)->isPast();
    }

    /**
     * Binds the emailed link to this exact pending password.
     *
     * If a second reset is requested, the first one's link stops matching and
     * is dead on arrival — so only the most recent request can ever be
     * applied, and an older email cannot resurrect a password the person has
     * since changed their mind about.
     */
    public function verificationHash(): string
    {
        return sha1($this->id . '|' . $this->password);
    }
}
