<?php

namespace App\Support\Community;

use App\Licensing\Features;
use App\Mail\CommunityMail;
use App\Models\CommunityConversation;
use App\Models\CommunityNotification;
use App\Models\CommunityProfile;
use App\Models\CommunityReport;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Modul „Community“: Treffpunkt für Mitglieder mit Pinnwand, Profilen, eigener Seite,
 * persönlichen Nachrichten und Foren. Konten kommen aus dem Mitgliederbereich (Rolle member).
 */
class Community
{
    public static function on(): bool
    {
        return Features::on('community');
    }

    /** Angemeldete Person, die in der Community schreiben darf (sonst null) */
    public static function me(): ?User
    {
        $u = auth()->user();

        return $u?->canUseCommunity() ? $u : null;
    }

    public static function title(): string
    {
        return Setting::get('community_title') ?: 'Community';
    }

    /** public = Gäste dürfen mitlesen, members = nur angemeldete Mitglieder */
    public static function isPublic(): bool
    {
        return Setting::get('community_visibility', 'public') === 'public';
    }

    public static function pagesAllowed(): bool
    {
        return Setting::get('community_pages', '1') === '1';
    }

    /** Profil anlegen, falls es noch keins gibt (mit eindeutigem Kurznamen) */
    public static function profile(User $user): CommunityProfile
    {
        if ($user->communityProfile) {
            return $user->communityProfile;
        }
        $base = Str::limit(str_replace('-', '_', Str::slug(Str::ascii($user->name), '_')) ?: 'mitglied', 20, '');
        $handle = $base;
        for ($i = 2; CommunityProfile::where('handle', $handle)->exists() || in_array($handle, self::RESERVED, true); $i++) {
            $handle = $base.$i;
        }
        $profile = CommunityProfile::create(['user_id' => $user->id, 'handle' => $handle])->refresh();
        $user->setRelation('communityProfile', $profile);

        return $profile;
    }

    public const RESERVED = ['admin', 'community', 'nova', 'support', 'moderator', 'system'];

    /** Benachrichtigung in der Community, auf Wunsch zusätzlich per E-Mail */
    public static function notify(User $to, ?User $actor, string $type, string $text, string $url, bool $mail = false): void
    {
        if ($actor && ($to->id === $actor->id || self::blocked($to, $actor))) {
            return;
        }
        CommunityNotification::create(['user_id' => $to->id, 'actor_id' => $actor?->id, 'type' => $type, 'text' => Str::limit($text, 250), 'url' => $url]);
        if ($mail && Setting::get('community_mail', '1') === '1' && self::profile($to)->email_notifications) {
            try {
                Mail::to($to->email)->send(new CommunityMail($to, $text, $url));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @Erwähnungen im Text benachrichtigen */
    public static function mentions(string $text, User $actor, string $what, string $url): void
    {
        preg_match_all('/(?<![\w@])@([a-z0-9_]{2,30})/i', $text, $m);
        $handles = array_slice(array_unique(array_map('strtolower', $m[1])), 0, 10);
        if (! $handles) {
            return;
        }
        foreach (CommunityProfile::with('user')->whereIn('handle', $handles)->get() as $p) {
            if ($p->user) {
                self::notify($p->user, $actor, 'mention', $actor->name.' hat Sie '.$what.' erwähnt.', $url, true);
            }
        }
    }

    /** Hat $user die Person $other blockiert? */
    public static function blocked(User $user, User $other): bool
    {
        return DB::table('community_blocks')->where('user_id', $user->id)->where('blocked_id', $other->id)->exists();
    }

    public static function follows(User $user, User $other): bool
    {
        return DB::table('community_follows')->where('follower_id', $user->id)->where('followed_id', $other->id)->exists();
    }

    /** @return array{messages: int, notifications: int} */
    public static function counts(User $user): array
    {
        return [
            'messages' => CommunityConversation::for($user)->get()->filter(fn ($c) => $c->isUnreadFor($user))->count(),
            'notifications' => CommunityNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    public static function openReports(): int
    {
        return CommunityReport::whereNull('resolved_at')->count();
    }

    /** Letzte Aktivität merken (höchstens einmal pro Minute schreiben) */
    public static function touch(User $user): void
    {
        $p = self::profile($user);
        if (! $p->last_seen_at || $p->last_seen_at->lt(now()->subMinute())) {
            $p->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }
}
