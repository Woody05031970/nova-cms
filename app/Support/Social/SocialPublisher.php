<?php

namespace App\Support\Social;

use App\Ai\AiException;
use App\Ai\AiManager;
use App\Ai\ContentAssistant;
use App\Licensing\Features;
use App\Models\Page;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Support\Seo\SiteSeo;
use Illuminate\Support\Str;
use Throwable;

/**
 * Modul „Social Media“: Beiträge vorschlagen und bei Facebook und Instagram veröffentlichen,
 * sofort oder zusammen mit einer zeitgesteuert veröffentlichten Seite.
 */
class SocialPublisher
{
    public function __construct(private readonly Meta $meta) {}

    public static function on(): bool
    {
        return Features::on('social');
    }

    /**
     * Legt Beiträge zu einer Seite an. Ist die Seite online, gehen sie sofort raus,
     * sonst zum geplanten Veröffentlichungstermin der Seite.
     *
     * @param  list<string>  $networks
     * @return list<SocialPost>
     */
    public function forPage(Page $page, array $networks, string $text): array
    {
        $posts = [];
        foreach (array_intersect(array_keys(SocialPost::NETWORKS), $networks) as $network) {
            $post = SocialPost::create([
                'network' => $network,
                'text' => $text,
                'link' => $page->url(),
                'image' => $this->image($page),
                'page_id' => $page->id,
                'scheduled_at' => $page->isPublished() ? now() : $page->published_at,
            ]);
            if ($page->isPublished()) {
                $this->send($post);
            }
            $posts[] = $post->fresh();
        }

        return $posts;
    }

    /** Einzelner Beitrag ohne Seite, z. B. aus dem Bereich „Social Media“ */
    public function now(string $network, string $text, ?string $link, ?string $image): SocialPost
    {
        $post = SocialPost::create(['network' => $network, 'text' => $text, 'link' => $link, 'image' => $image, 'scheduled_at' => now()]);
        $this->send($post);

        return $post->fresh();
    }

    public function send(SocialPost $post): void
    {
        try {
            $id = match ($post->network) {
                'facebook' => $this->meta->postFacebook($post->text, $post->link, $post->image),
                'instagram' => $this->meta->postInstagram($this->instagramText($post), $post->image),
            };
            $post->update(['status' => 'sent', 'external_id' => $id, 'error' => null, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $post->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    /** Fällige Beiträge zu inzwischen veröffentlichten Seiten senden */
    public function runDue(): int
    {
        if (! self::on()) {
            return 0;
        }
        $due = SocialPost::where('status', 'pending')->where('scheduled_at', '<=', now())
            ->whereHas('page', fn ($q) => $q->where('status', 'published'))->limit(10)->get();
        foreach ($due as $post) {
            $this->send($post);
        }

        return $due->count();
    }

    /** Instagram zeigt keine klickbaren Links: Hinweis auf die Bio statt Adresse */
    private function instagramText(SocialPost $post): string
    {
        $text = $post->link ? str_replace($post->link, 'Link in der Bio', $post->text) : $post->text;

        return trim($text);
    }

    /** Bild für den Beitrag: erstes Bild der Seite als vollständige Adresse */
    public function image(Page $page): ?string
    {
        return app(SiteSeo::class)->shareImage($page);
    }

    /** Vorschlag für den Beitragstext: per KI, wenn eingerichtet, sonst aus einer Vorlage */
    public function suggest(string $title, string $text, ?string $url, ?string $keyword = null): array
    {
        if (app(AiManager::class)->isConfigured()) {
            try {
                return ['text' => app(ContentAssistant::class)->socialPost($title, $text, $url), 'ai' => true];
            } catch (AiException) {
                // Dann eben die Vorlage
            }
        }

        return ['text' => self::template($title, $text, $url, $keyword), 'ai' => false];
    }

    public static function template(string $title, string $text, ?string $url, ?string $keyword = null): string
    {
        $summary = Str::limit(trim(preg_replace(['/\s+/u', '/\.(\s*\.)+/u'], [' ', '.'], $text)), 260);
        $tags = collect(preg_split('/[\s,]+/u', trim(($keyword ?? '').' '.$title.' '.Setting::get('site_name'))))
            ->map(fn ($w) => preg_replace('/[^\p{L}\p{N}]/u', '', $w))
            ->filter(fn ($w) => mb_strlen($w) >= 4)
            ->map(fn ($w) => '#'.mb_strtoupper(mb_substr($w, 0, 1)).mb_substr($w, 1))
            ->unique(fn ($t) => mb_strtolower($t))->take(4)->implode(' ');

        return trim($title."\n\n".$summary.($url ? "\n\n👉 Mehr dazu: ".$url : '').($tags ? "\n\n".$tags : ''));
    }
}
