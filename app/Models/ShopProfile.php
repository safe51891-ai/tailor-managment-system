<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProfile extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'logo_path', 'primary_color', 'sidebar_color', 'background_color'];

    public function theme()
    {
        $defaults = ['primary_color' => '#176458', 'sidebar_color' => '#182e36', 'background_color' => '#f5f3ed'];
        $colors = [];
        foreach ($defaults as $key => $default) {
            $value = $this->getAttribute($key);
            $colors[$key] = is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/D', $value) ? strtolower($value) : $default;
        }
        $colors['canvas_text'] = self::textColor($colors['background_color']);
        $colors['canvas_muted'] = self::mix($colors['background_color'], $colors['canvas_text'], 0.75);
        $colors['link_color'] = self::textColor($colors['primary_color']) === '#ffffff' ? $colors['primary_color'] : self::mix($colors['primary_color'], '#000000', 0.55);
        $colors['primary_text'] = self::textColor($colors['primary_color']);
        $colors['primary_hover'] = self::mix($colors['primary_color'], '#000000', 0.15);
        $colors['primary_hover_text'] = self::textColor($colors['primary_hover']);
        $colors['primary_soft'] = self::mix($colors['primary_color'], '#ffffff', 0.90);
        $colors['sidebar_text'] = self::textColor($colors['sidebar_color']);
        $colors['sidebar_muted'] = self::mix($colors['sidebar_color'], $colors['sidebar_text'], 0.80);
        $colors['sidebar_hover'] = self::mix($colors['sidebar_color'], $colors['sidebar_text'], 0.10);
        $colors['sidebar_active'] = self::mix($colors['sidebar_color'], $colors['sidebar_text'], 0.16);
        return $colors;
    }

    private static function textColor($hex)
    {
        $channels = array_map(function ($v) {
            $v = hexdec($v) / 255;
            return $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }, str_split(substr($hex, 1), 2));
        $luminance = $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#000000';
    }

    private static function mix($hex, $other, $weight)
    {
        $result = '#';
        foreach ([1, 3, 5] as $offset) {
            $result .= sprintf('%02x', round(hexdec(substr($hex, $offset, 2)) * (1 - $weight) + hexdec(substr($other, $offset, 2)) * $weight));
        }
        return $result;
    }

    public static function current()
    {
        return static::find(1) ?: new static(['name' => config('app.name')]);
    }
}
