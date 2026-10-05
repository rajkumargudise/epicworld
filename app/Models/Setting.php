<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Administrator-managed runtime settings (AI provider choice and API
 * keys). Every value is stored encrypted with the application key, so
 * a database dump alone never exposes a credential. Reads never throw:
 * if the table is missing or a value cannot be decrypted (e.g. the
 * APP_KEY was rotated) the setting simply reads as unset.
 */
class Setting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    public static function read(string $key): ?string
    {
        try {
            $value = static::query()->where('key', $key)->value('value');

            return $value === null || $value === '' ? null : Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }

    public static function write(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            static::query()->where('key', $key)->delete();

            return;
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
    }
}
