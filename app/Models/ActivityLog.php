<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    /**
     * Restricts the audit trail to the reader's own actions.
     *
     * The administrative roles — SA, Developer, Admin — see everyone, because
     * auditing other people is the point of the log for them. Everybody else
     * sees only the rows they created: an engineer can review what they did,
     * but not what a colleague did.
     *
     * By user, not by hub, and deliberately so. Hub scoping elsewhere answers
     * "whose stock is this"; this answers "whose actions are these", and two
     * engineers sharing a branch still have no business reading each other's
     * audit trail.
     *
     * Rows with a null user_id — a failed sign-in against a username that
     * matches no account — belong to nobody and so appear only for the
     * administrative roles.
     *
     * Inserts are unaffected: global scopes apply to reads, so log() and
     * logFailedLogin() keep recording everything regardless of who is acting.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ownActions', function (Builder $query) {
            $user = auth()->user();

            if (! $user || $user->seesAllHubs()) {
                return;
            }

            $query->where('activity_logs.user_id', $user->id);
        });
    }

    // The activity_logs table has created_at only - no updated_at column. Without
    // this, every insert fails with "Invalid column name 'updated_at'", which
    // broke every ActivityLog::log() call in the app.
    const UPDATED_AT = null;
    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'remarks',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Recorded when a sign-in is refused. Shared with the activity log report. */
    public const ACTION_FAILED_LOGIN = 'Failed Login';

    /**
     * How a single line of stock is identified in a remark.
     *
     * Serial or quantity, never both: a serialised unit is identified by its
     * serial and is always one item, so a quantity beside it says nothing.
     * Non-serialised stock has no serial to name, so the quantity is the only
     * thing that makes the line meaningful.
     *
     * Lives here because receiving and requests both write it, and the two
     * drifting apart is exactly what makes a log hard to read.
     */
    public static function unitLabel(?string $serial, int $quantity): string
    {
        $serial = trim((string) $serial);

        return $serial !== '' ? "Serial: {$serial}" : "Unserialized | Qty: {$quantity}";
    }

    public static function log($action, $remarks = null)
    {
        return static::create([
            'user_id' => auth()?->id(),
            'user_role' => auth()?->user()?->role ?? 'System',
            'action' => $action,
            'remarks' => $remarks,
            // Null when the action came from the console rather than a browser,
            // which is itself worth being able to tell apart in an audit.
            'ip_address' => self::currentIp(),
        ]);
    }

    /** The caller's IP, or null outside an HTTP request. */
    private static function currentIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A refused sign-in, which the plain log() cannot record properly: nobody is
     * authenticated, so user_id would always be null and the row would not say
     * which account was targeted.
     *
     * $userId is passed when the address matched a real account — a wrong
     * password, a deactivated account, an unverified one — so those attempts
     * are attributed to the user being hammered. It stays null when no such
     * account exists, because there is genuinely nobody to attribute it to.
     *
     * The remarks are written in a fixed "Email: x | reason" shape; the
     * activity log report reads the address back out of it for rows with no
     * user_id. Change the shape here and that fallback must change too. The
     * IP used to be a third segment and now has its own column.
     *
     * Sign-in used to be by username, and rows logged before that changed
     * still carry the older "Username: x" prefix — the report reads both.
     */
    public static function logFailedLogin(string $email, string $reason, ?string $ip = null, ?int $userId = null)
    {
        return static::create([
            'user_id' => $userId,
            'user_role' => 'System',
            'action' => self::ACTION_FAILED_LOGIN,
            // The IP is no longer appended to the text as well - it has its
            // own column now, and repeating it would show twice on screen.
            // Rows written before that change keep their trailing "| IP: x",
            // and the migration lifted those values into the column.
            'remarks' => "Email: {$email} | {$reason}",
            'ip_address' => $ip ?: self::currentIp(),
        ]);
    }
}
