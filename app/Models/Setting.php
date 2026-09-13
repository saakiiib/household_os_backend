<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /**
     * Get a setting value by key.
     * Uses cache for performance. Pass $default to override if not found.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $settings = Cache::remember('settings_all', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        });

        return $settings[$key] ?? $default;
    }

    /**
     * Set a setting value. Creates or updates.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings_all');
    }

    /**
     * Set multiple settings at once.
     */
    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings_all');
    }

    /**
     * Get all settings as key => value array.
     */
    public static function allAsArray(): array
    {
        return Cache::remember('settings_all', 3600, function () {
            return static::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get all settings grouped by group column.
     */
    public static function grouped(): array
    {
        return Cache::remember('settings_grouped', 3600, function () {
            return static::all()->groupBy('group')->map(function ($items) {
                return $items->pluck('value', 'key')->toArray();
            })->toArray();
        });
    }

    /**
     * Get a setting value as a full URL (for logos, images).
     */
    public static function url(string $key, ?string $default = null): ?string
    {
        $value = static::get($key, $default);

        if (!$value) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Otherwise treat as a stored file path
        return Storage::disk('public')->url($value);
    }

    /**
     * Flush the settings cache.
     */
    public static function flushCache(): void
    {
        Cache::forget('settings_all');
        Cache::forget('settings_grouped');
    }
}
