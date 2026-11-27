<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Einfache Web-Firewall: sperrt bekannte Angreifer und wehrt typische Angriffsmuster ab.
 * Geprüft werden nur Adresse und Parameter, nicht der Inhalt von Formularen der Verwaltung.
 */
class Firewall
{
    /** Typische Muster aus automatisierten Angriffen */
    public const PATTERNS = [
        'path_traversal' => '#(\.\./|\.\.\\\\|%2e%2e%2f|%2e%2e/)#i',
        'sql_injection' => '#(\bunion\b[^a-z]+\bselect\b|\bselect\b.+\bfrom\b.+\bwhere\b|\bsleep\s*\(|benchmark\s*\(|\bor\b\s+1\s*=\s*1|information_schema)#i',
        'xss' => '#(<\s*script|javascript:|on(error|load)\s*=|<\s*iframe|<\s*svg[^>]*on\w+\s*=)#i',
        'probe' => '#(^|/)(wp-login\.php|wp-admin|xmlrpc\.php|\.env|\.git/|phpmyadmin|\.aws/|config\.php\.bak|vendor/phpunit|cgi-bin)#i',
        'php_injection' => '#(base64_decode\s*\(|eval\s*\(|php://|data://|expect://|/etc/passwd|proc/self)#i',
    ];

    /** Nach so vielen Treffern in 10 Minuten wird die IP für eine Stunde gesperrt. */
    public const AUTO_BLOCK_HITS = 10;

    public function handle(Request $request, Closure $next): Response
    {
        if (! InstallState::isInstalled() || Setting::get('security_firewall') === '0') {
            return $next($request);
        }

        $ip = (string) $request->ip();
        if (BlockedIp::isBlocked($ip)) {
            return $this->deny();
        }

        $subject = rawurldecode($request->getPathInfo()).' '.urldecode((string) $request->getQueryString());
        foreach (self::PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $subject)) {
                $this->strike($ip, $name, $request);

                return $this->deny();
            }
        }

        return $next($request);
    }

    private function strike(string $ip, string $type, Request $request): void
    {
        SecurityEvent::record('attack', $type.': '.mb_substr($request->getRequestUri(), 0, 300), null, $ip);

        $key = 'nova-fw:'.$ip;
        RateLimiter::hit($key, 600);
        if (Setting::get('security_autoblock') !== '0' && RateLimiter::attempts($key) >= self::AUTO_BLOCK_HITS) {
            BlockedIp::block($ip, 'Automatisch: wiederholte Angriffsversuche', 60);
            RateLimiter::clear($key);
        }
    }

    private function deny(): Response
    {
        return response('Zugriff verweigert.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
