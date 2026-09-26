<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function flag(string $key, bool $default = false): bool
    {
        $setting = static::query()->find($key);

        if (! $setting) {
            return $default;
        }

        return (bool) ($setting->value['enabled'] ?? $default);
    }

    public static function values(string $key, array $default = []): array
    {
        $setting = static::query()->find($key);

        if (! $setting) {
            return $default;
        }

        return array_values($setting->value['values'] ?? $default);
    }
}
