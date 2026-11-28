<?php

namespace App\Support\Admin;

use App\Licensing\Features;
use App\Licensing\LicenseServer;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Plugins\Hooks;
use App\Support\Community\Community;

/**
 * Linke Navigation der Verwaltung: Punkte in aufklappbaren Kategorien.
 *
 * Reihenfolge der Kategorien, Zuordnung und Reihenfolge der Punkte sowie ausgeblendete Punkte lassen sich
 * unter „Backoffice-Menü anpassen“ ändern (Setting admin_nav). Das Dashboard steht immer oben.
 */
class AdminNav
{
    public const SETTING = 'admin_nav';

    /** Standard-Kategorien: Schlüssel => [Name, Symbol] */
    public const GROUPS = [
        'website' => ['Website', 'pages'],
        'kommunikation' => ['Kommunikation', 'inbox'],
        'verkaufen' => ['Verkaufen', 'cart'],
        'mitglieder' => ['Mitglieder', 'users'],
        'sichtbarkeit' => ['Sichtbarkeit & KI', 'sparkles'],
        'system' => ['System', 'settings'],
        'erweiterungen' => ['Erweiterungen', 'puzzle'],
    ];

    /** Alte Gruppennamen (Plugins vor 0.9.1) auf neue Kategorien abbilden */
    private const LEGACY_GROUPS = ['Inhalte' => 'website', 'Wachstum' => 'sichtbarkeit', 'System' => 'system'];

    /**
     * Alle Punkte mit Standard-Kategorie, inklusive Punkte aus Plugins.
     *
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        $off = fn (string ...$f) => ! collect($f)->contains(fn ($x) => Features::on($x));
        $core = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => route('admin.dashboard'), 'icon' => 'home', 'active' => 'admin.dashboard', 'group' => null],
            // Website
            ['key' => 'pages', 'label' => 'Seiten', 'url' => route('admin.pages.index'), 'icon' => 'pages', 'active' => 'admin.pages.*', 'group' => 'website'],
            ['key' => 'menu', 'label' => 'Menü', 'url' => route('admin.menu'), 'icon' => 'menu', 'active' => 'admin.menu*', 'group' => 'website'],
            ['key' => 'design', 'label' => 'Design', 'url' => route('admin.design'), 'icon' => 'palette', 'active' => 'admin.design*', 'group' => 'website'],
            ['key' => 'media', 'label' => 'Medien', 'url' => route('admin.media.index'), 'icon' => 'image', 'active' => 'admin.media.*', 'group' => 'website'],
            ['key' => 'plan', 'label' => 'Redaktionsplan', 'url' => route('admin.plan'), 'icon' => 'calendar', 'active' => 'admin.plan*', 'group' => 'website', 'locked' => $off('redaktionsplan')],
            ['key' => 'languages', 'label' => 'Sprachen', 'url' => route('admin.languages'), 'icon' => 'globe', 'active' => 'admin.languages*', 'group' => 'website', 'locked' => $off('mehrsprachigkeit')],
            // Kommunikation
            ['key' => 'messages', 'label' => 'Anfragen', 'url' => route('admin.messages'), 'icon' => 'inbox', 'active' => 'admin.messages*', 'group' => 'kommunikation', 'badge' => fn () => ContactMessage::unread()],
            ['key' => 'bookings', 'label' => 'Termine', 'url' => route('admin.bookings'), 'icon' => 'clock', 'active' => 'admin.bookings*', 'group' => 'kommunikation', 'locked' => $off('terminbuchung'),
                'badge' => fn () => Features::on('terminbuchung') ? Booking::where('status', 'pending')->where('starts_at', '>=', now())->count() : 0],
            ['key' => 'newsletter', 'label' => 'Newsletter', 'url' => route('admin.newsletter.index'), 'icon' => 'mail', 'active' => 'admin.newsletter.*', 'group' => 'kommunikation', 'locked' => $off('newsletter')],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'url' => route('admin.whatsapp'), 'icon' => 'chat', 'active' => 'admin.whatsapp*', 'group' => 'kommunikation', 'locked' => $off('whatsapp')],
            ['key' => 'social', 'label' => 'Social Media', 'url' => route('admin.social'), 'icon' => 'share', 'active' => 'admin.social*', 'group' => 'kommunikation', 'locked' => $off('social')],
            ['key' => 'reviews', 'label' => 'Bewertungen', 'url' => route('admin.reviews'), 'icon' => 'heart', 'active' => 'admin.reviews*', 'group' => 'kommunikation', 'locked' => $off('google-bewertungen')],
            // Verkaufen
            ['key' => 'shop', 'label' => 'Shop', 'url' => route('admin.shop.index'), 'icon' => 'cart', 'active' => 'admin.shop.*', 'group' => 'verkaufen'],
            ['key' => 'customers', 'label' => 'Kunden', 'url' => route('admin.customers'), 'icon' => 'user', 'active' => 'admin.customers*', 'group' => 'verkaufen', 'locked' => $off('kundenkonto')],
            ['key' => 'coupons', 'label' => 'Gutscheine', 'url' => route('admin.coupons'), 'icon' => 'tag', 'active' => 'admin.coupons*', 'group' => 'verkaufen', 'locked' => $off('gutscheine')],
            ['key' => 'erp', 'label' => 'Warenwirtschaft', 'url' => route('admin.erp'), 'icon' => 'layers', 'active' => 'admin.erp*', 'group' => 'verkaufen', 'locked' => $off('warenwirtschaft')],
            // Mitglieder & Community
            ['key' => 'members', 'label' => 'Mitglieder', 'url' => route('admin.members'), 'icon' => 'users', 'active' => 'admin.members*', 'group' => 'mitglieder', 'locked' => $off('mitgliederbereich', 'community'),
                'badge' => fn () => $off('mitgliederbereich', 'community') ? 0 : User::where('role', 'member_pending')->count()],
            ['key' => 'community', 'label' => 'Community', 'url' => route('admin.community'), 'icon' => 'community', 'active' => 'admin.community*', 'group' => 'mitglieder', 'locked' => $off('community'),
                'badge' => fn () => Features::on('community') ? Community::openReports() : 0],
            // Sichtbarkeit & KI
            ['key' => 'ai', 'label' => 'KI-Assistent', 'url' => route('admin.ai'), 'icon' => 'sparkles', 'active' => 'admin.ai*', 'group' => 'sichtbarkeit'],
            ['key' => 'seo', 'label' => 'SEO', 'url' => route('admin.seo'), 'icon' => 'search', 'active' => 'admin.seo*', 'group' => 'sichtbarkeit'],
            ['key' => 'geo', 'label' => 'KI-Sichtbarkeit', 'url' => route('admin.geo'), 'icon' => 'globe', 'active' => 'admin.geo*', 'group' => 'sichtbarkeit', 'locked' => $off('geo')],
            ['key' => 'stats', 'label' => 'Statistik', 'url' => route('admin.stats'), 'icon' => 'chart', 'active' => 'admin.stats*', 'group' => 'sichtbarkeit', 'locked' => $off('statistik')],
            // System
            ['key' => 'accessibility', 'label' => 'Barrierefreiheit', 'url' => route('admin.accessibility'), 'icon' => 'accessibility', 'active' => 'admin.accessibility*', 'group' => 'system'],
            ['key' => 'turbo', 'label' => 'Turbo', 'url' => route('admin.turbo'), 'icon' => 'bolt', 'active' => 'admin.turbo*', 'group' => 'system', 'locked' => $off('turbo')],
            ['key' => 'security', 'label' => 'Sicherheit', 'url' => route('admin.security'), 'icon' => 'shield', 'active' => 'admin.security*', 'group' => 'system'],
            ['key' => 'backups', 'label' => 'Datensicherung', 'url' => route('admin.backups'), 'icon' => 'upload', 'active' => 'admin.backups*', 'group' => 'system', 'locked' => $off('datensicherung')],
            ['key' => 'plugins', 'label' => 'Plugins', 'url' => route('admin.plugins'), 'icon' => 'puzzle', 'active' => 'admin.plugins*', 'group' => 'system'],
            ['key' => 'settings', 'label' => 'Einstellungen', 'url' => route('admin.settings'), 'icon' => 'settings', 'active' => 'admin.settings*', 'group' => 'system'],
        ];
        if (LicenseServer::enabled()) {
            $core[] = ['key' => 'license-server', 'label' => 'Lizenzserver', 'url' => route('admin.license-server'), 'icon' => 'key', 'active' => 'admin.license-server*', 'group' => 'system'];
        }
        $coreCount = count($core);

        $items = app(Hooks::class)->applyFilters('admin.menu', $core);
        $out = [];
        $seen = [];
        foreach (array_values($items) as $i => $item) {
            if (! is_array($item) || empty($item['label'])) {
                continue;
            }
            if ($i >= $coreCount || empty($item['key'])) {
                // Punkte aus Plugins: stabiler Schlüssel aus der Adresse, Kategorie aus „group“ oder „Erweiterungen“
                $item['key'] = 'x-'.substr(md5((string) ($item['url'] ?? $item['label'])), 0, 10);
                $g = $item['group'] ?? null;
                $item['group'] = isset(self::GROUPS[$g]) ? $g : (self::LEGACY_GROUPS[$g] ?? 'erweiterungen');
                $item['sort'] = (int) ($item['order'] ?? 50);
            }
            if (isset($seen[$item['key']])) {
                continue;
            }
            $seen[$item['key']] = true;
            $item['sort'] ??= count($out);
            $out[] = $item;
        }

        return $out;
    }

    /** Gespeicherte Anpassungen */
    public static function custom(): array
    {
        $c = json_decode((string) Setting::get(self::SETTING, ''), true);

        return is_array($c) ? $c : [];
    }

    /**
     * Kategorien mit ihren Punkten in der eingestellten Reihenfolge.
     *
     * @param  bool  $withHidden  auch ausgeblendete Punkte (für die Anpassen-Seite)
     * @return array{top: list<array>, groups: list<array{key: string, label: string, icon: string, items: list<array>}>, single: bool}
     */
    public static function tree(bool $withHidden = false): array
    {
        $custom = self::custom();
        $names = is_array($custom['labels'] ?? null) ? $custom['labels'] : [];
        $order = array_values(array_unique(array_merge(
            array_values(array_filter(is_array($custom['groups'] ?? null) ? $custom['groups'] : [], fn ($g) => isset(self::GROUPS[$g]))),
            array_keys(self::GROUPS)
        )));
        $conf = is_array($custom['items'] ?? null) ? $custom['items'] : [];

        $top = [];
        $byGroup = array_fill_keys($order, []);
        foreach (self::items() as $item) {
            $c = is_array($conf[$item['key']] ?? null) ? $conf[$item['key']] : [];
            $item['hidden'] = (bool) ($c['hidden'] ?? false) && $item['key'] !== 'dashboard';
            if ($item['group'] === null) {
                $top[] = $item;

                continue;
            }
            $g = isset($byGroup[$c['group'] ?? '']) ? $c['group'] : $item['group'];
            $item['sort'] = isset($c['order']) ? (int) $c['order'] : 1000 + $item['sort'];
            $item['default_group'] = $item['group'];
            $item['group'] = $g;
            if ($item['hidden'] && ! $withHidden) {
                continue;
            }
            $byGroup[$g][] = $item;
        }

        $groups = [];
        foreach ($order as $key) {
            $items = collect($byGroup[$key])->sortBy('sort')->values()->all();
            if (! $items && ! $withHidden) {
                continue;
            }
            $label = trim((string) ($names[$key] ?? ''));
            $groups[] = ['key' => $key, 'label' => $label !== '' ? $label : self::GROUPS[$key][0], 'icon' => self::GROUPS[$key][1], 'items' => $items];
        }

        return ['top' => $top, 'groups' => $groups, 'single' => (bool) ($custom['single'] ?? true)];
    }

    /**
     * Speichert die Anpassung.
     *
     * @param  list<array{key: string, label?: string, items: list<array{key: string, hidden?: bool}>}>  $groups
     */
    public static function save(array $groups, bool $single): void
    {
        $known = collect(self::items())->pluck('key')->flip();
        $out = ['groups' => [], 'labels' => [], 'items' => [], 'single' => $single];
        foreach ($groups as $g) {
            $key = $g['key'] ?? null;
            if (! is_string($key) || ! isset(self::GROUPS[$key]) || in_array($key, $out['groups'], true)) {
                continue;
            }
            $out['groups'][] = $key;
            $label = mb_substr(trim(strip_tags((string) ($g['label'] ?? ''))), 0, 40);
            if ($label !== '' && $label !== self::GROUPS[$key][0]) {
                $out['labels'][$key] = $label;
            }
            foreach (array_values(is_array($g['items'] ?? null) ? $g['items'] : []) as $pos => $item) {
                $ik = $item['key'] ?? null;
                if (is_string($ik) && $known->has($ik) && $ik !== 'dashboard' && ! isset($out['items'][$ik])) {
                    $out['items'][$ik] = ['group' => $key, 'order' => $pos, 'hidden' => (bool) ($item['hidden'] ?? false)];
                }
            }
        }
        Setting::put(self::SETTING, json_encode($out, JSON_UNESCAPED_UNICODE));
    }

    public static function reset(): void
    {
        Setting::put(self::SETTING, null);
    }

    /** Zahl für den Punkt (Ungelesenes usw.), null wenn nichts */
    public static function badge(array $item): ?int
    {
        $b = $item['badge'] ?? null;
        $n = $b instanceof \Closure ? (int) $b() : (int) $b;

        return $n > 0 ? $n : null;
    }

    public static function isActive(array $item): bool
    {
        if (! empty($item['active'])) {
            return request()->routeIs($item['active']);
        }

        return ! empty($item['url']) && request()->url() === $item['url'];
    }
}
