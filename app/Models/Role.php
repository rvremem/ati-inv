<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * The table is tiny and read on nearly every request (User::$role reads it),
     * so it is loaded once per request rather than per lookup.
     */
    private static ?\Illuminate\Support\Collection $cache = null;

    public static function cached(): \Illuminate\Support\Collection
    {
        return self::$cache ??= static::query()->get();
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    public static function codeFor(?int $id): ?string
    {
        return $id === null ? null : self::cached()->firstWhere('id', $id)?->code;
    }

    public static function idForCode(?string $code): ?int
    {
        return $code === null ? null : self::cached()->firstWhere('code', $code)?->id;
    }

    /** Valid role codes, for validation rules and dropdowns. */
    public static function codes(): array
    {
        return self::cached()->where('is_active', true)->pluck('code')->all();
    }

    public static function selectable(): \Illuminate\Support\Collection
    {
        return self::cached()->where('is_active', true)->sortBy('name')->values();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
