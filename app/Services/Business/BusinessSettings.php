<?php

namespace App\Services\Business;

use App\Models\BusinessProfile;
use App\Models\Setting;

class BusinessSettings
{
    public function profile(): ?BusinessProfile
    {
        return BusinessProfile::query()->first();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        return $setting?->typedValue() ?? $default;
    }

    public function settings(?string $group = null): array
    {
        $query = Setting::query();

        if ($group !== null) {
            $query->where('group', $group);
        }

        return $query->get()->mapWithKeys(fn (Setting $setting) => [
            $setting->key => $setting->typedValue(),
        ])->all();
    }

    public function put(string $key, mixed $value, string $type = 'string', string $group = 'general', bool $isPublic = false): Setting
    {
        $storedValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => $value === null ? null : (string) $value,
        };

        return Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group' => $group,
                'is_public' => $isPublic,
            ],
        );
    }
}
