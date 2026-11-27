<?php

namespace App\Support;

use App\Licensing\Features;
use App\Models\Setting;

/**
 * WhatsApp-Anbindung ohne fremde Skripte: Links nach wa.me öffnen den Chat in der
 * WhatsApp-App oder in WhatsApp Web. Es werden keine Daten an Meta übertragen,
 * solange niemand klickt.
 */
class WhatsApp
{
    public static function enabled(): bool
    {
        return Features::on('whatsapp') && Setting::get('whatsapp_enabled') === '1' && self::number() !== null;
    }

    /** Rufnummer im internationalen Format ohne Pluszeichen, z. B. 4917612345678 */
    public static function number(?string $raw = null): ?string
    {
        $raw ??= (string) Setting::get('whatsapp_number', '');
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with(ltrim($raw), '0')) {
            $digits = '49'.substr($digits, 1); // deutsche Nummer ohne Ländervorwahl
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }

    public static function link(?string $text = null): ?string
    {
        $number = self::number();
        if (! $number) {
            return null;
        }
        $text ??= (string) Setting::get('whatsapp_message', '');

        return 'https://wa.me/'.$number.($text !== '' ? '?text='.rawurlencode($text) : '');
    }

    /** @return array{url: string, label: string, position: string}|null */
    public static function button(): ?array
    {
        if (! self::enabled() || Setting::get('whatsapp_floating', '1') !== '1') {
            return null;
        }

        return [
            'url' => self::link(),
            'label' => (string) Setting::get('whatsapp_label', 'Fragen? Schreiben Sie uns'),
            'position' => Setting::get('whatsapp_position') === 'left' ? 'left' : 'right',
        ];
    }

    /** Link für „Per WhatsApp fragen“ auf Produktseiten */
    public static function productLink(string $productName, string $url): ?string
    {
        if (! self::enabled() || Setting::get('whatsapp_products', '1') !== '1') {
            return null;
        }

        return self::link('Hallo, ich habe eine Frage zu „'.$productName.'“: '.$url);
    }
}
