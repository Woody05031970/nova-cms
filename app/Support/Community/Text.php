<?php

namespace App\Support\Community;

use Illuminate\Support\HtmlString;

/**
 * Text von Mitgliedern sicher anzeigen: alles wird maskiert, danach werden Links, @Erwähnungen,
 * #Schlagworte, **fett** und Zeilenumbrüche hervorgehoben. HTML von Mitgliedern gibt es nicht.
 */
class Text
{
    public static function render(?string $text): HtmlString
    {
        $html = e((string) $text);
        $html = preg_replace_callback('#\bhttps?://[^\s<>"\']+#i', function ($m) {
            $url = rtrim($m[0], '.,;:!?)');
            $rest = substr($m[0], strlen($url));

            return '<a href="'.$url.'" class="text-nova-700 underline decoration-nova-300 underline-offset-2 hover:decoration-nova-600" rel="nofollow ugc noopener" target="_blank">'.e(mb_strimwidth(html_entity_decode($url), 0, 60, '…')).'</a>'.$rest;
        }, $html);
        $html = preg_replace_callback('/(?<![\w@&\/])@([a-z0-9_]{2,30})/i', fn ($m) => '<a href="'.route('community.profile', strtolower($m[1])).'" class="font-medium text-nova-700 hover:underline">@'.$m[1].'</a>', $html);
        $html = preg_replace_callback('/(?<![\w&#\/])#([\p{L}0-9_]{2,40})/u', fn ($m) => '<a href="'.route('community.home', ['tag' => mb_strtolower($m[1])]).'" class="text-nova-700 hover:underline">#'.$m[1].'</a>', $html);
        $html = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $html);

        return new HtmlString(nl2br($html, false));
    }

    /** Kurze Vorschau ohne Formatierung */
    public static function excerpt(?string $text, int $len = 140): string
    {
        return mb_strimwidth(trim(preg_replace('/\s+/', ' ', str_replace('**', '', (string) $text))), 0, $len, '…');
    }
}
