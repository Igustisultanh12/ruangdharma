<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'string'];

    /** Ambil nilai settings (alias kanonis). */
    public static function get(string $key, $default = null)
    {
        $s = static::where('key', $key)->first();
        return $s ? $s->value : $default;
    }
    /** Simpan nilai settings (alias kanonis). Array -> JSON. */
    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : (string) $value]);
    }
    /** Alias lama — dipakai banyak controller/service. */
    public static function getValue(string $key, $default = null) { return static::get($key, $default); }
    public static function setValue(string $key, $value): void { static::set($key, $value); }

    /** Ambil sebagai array (untuk key JSON seperti blocked_keywords / notification_templates). */
    public static function getArray(string $key, array $default = []): array
    {
        $raw = static::get($key);
        if ($raw === null || $raw === '') return $default;
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : $default;
    }
    public static function getInt(string $key, int $default = 0): int { return (int) (static::get($key, $default) ?? $default); }
    public static function getBool(string $key, bool $default = false): bool
    {
        $v = static::get($key);
        if ($v === null) return $default;
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }
}
