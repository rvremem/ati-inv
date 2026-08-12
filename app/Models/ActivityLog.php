<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    // The activity_logs table has created_at only - no updated_at column. Without
    // this, every insert fails with "Invalid column name 'updated_at'", which
    // broke every ActivityLog::log() call in the app.
    const UPDATED_AT = null;
    protected $fillable = [
        'user_id',
        'user_role',
        'action',
        'remarks',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log($action, $remarks = null)
    {
        return static::create([
            'user_id' => auth()?->id(),
            'user_role' => auth()?->user()?->role ?? 'System',
            'action' => $action,
            'remarks' => $remarks,
        ]);
    }
}
