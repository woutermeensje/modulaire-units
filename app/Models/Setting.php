<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * Editable site settings with their defaults (taken from the original WordPress site).
     *
     * @var array<string, array{label: string, default: string, type?: string}>
     */
    public const FIELDS = [
        'site_name' => ['label' => 'Sitenaam', 'default' => 'ModulaireHuisvesting.nl'],
        'site_tagline' => ['label' => 'Slogan', 'default' => 'Bekijk het complete aanbod van Modulaire Units!'],
        'phone' => ['label' => 'Telefoonnummer', 'default' => '085 239 20 40'],
        'email' => ['label' => 'E-mailadres', 'default' => 'informatie@modulairehuisvesting.nl'],
        'address' => ['label' => 'Adres', 'default' => 'Veenland 31, 2291 NS, Wateringen'],
        'notification_email' => ['label' => 'Ontvanger formulieren', 'default' => 'informatie@modulairehuisvesting.nl'],
        'error_notification_email' => ['label' => 'Ontvanger foutmeldingen (leeg = ontvanger formulieren)', 'default' => ''],
        'footer_about' => ['label' => 'Footer: over ons', 'default' => 'Modulairehuisvesting.nl levert flexibele, tijdelijke en modulaire huisvesting voor organisaties in heel Nederland.', 'type' => 'textarea'],
        'discount_popup_code' => ['label' => 'Kortingspop-up: code (leeg = pop-up uit)', 'default' => ''],
        'discount_popup_title' => ['label' => 'Kortingspop-up: titel', 'default' => 'Ontvang korting op je modulaire unit'],
        'discount_popup_text' => ['label' => 'Kortingspop-up: tekst', 'default' => 'Noem deze kortingscode bij je offerteaanvraag of in contact met onze adviseurs.', 'type' => 'textarea'],
        'google_analytics_id' => ['label' => 'Google Analytics ID (G-…)', 'default' => ''],
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $values = Cache::rememberForever('settings', fn (): array => static::query()->pluck('value', 'key')->all());

        $value = $values[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? (self::FIELDS[$key]['default'] ?? null);
        }

        return $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
