<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model {
    protected $fillable = ['key','value'];
    public static function getValue(string $key, $default = null) {
        $s = static::where('key', $key)->first();
        return $s ? $s->value : $default;
    }
    public static function setValue(string $key, $value): void {
        static::updateOrCreate(['key'=>$key], ['value'=> is_array($value) ? json_encode($value) : (string)$value]);
    }
}
