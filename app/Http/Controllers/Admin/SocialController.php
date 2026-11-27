<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Shop\ShopSettings;
use App\Support\Social\Meta;
use App\Support\Social\SocialPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Modul „Social Media“: Verbindung zu Facebook und Instagram, Beiträge schreiben und veröffentlichen */
class SocialController extends Controller
{
    public function index(): View
    {
        return view('admin.social.index', [
            'pages' => Page::published()->orderBy('title')->get(['id', 'title', 'slug', 'is_home']),
            'products' => ShopSettings::enabled() ? Product::active()->orderBy('name')->get(['id', 'name']) : collect(),
            'posts' => SocialPost::with('page:id,title')->latest('id')->limit(30)->get(),
            'fb' => ['connected' => Meta::connected('facebook'), 'page_id' => Meta::pageId(), 'name' => Setting::get('social_fb_name')],
            'ig' => ['connected' => Meta::connected('instagram'), 'name' => Setting::get('social_ig_name')],
        ]);
    }

    public function connect(Request $request, Meta $meta): RedirectResponse
    {
        $data = $request->validate([
            'page_id' => ['required', 'regex:/^\d{5,30}$/'],
            'token' => [Meta::token() ? 'nullable' : 'required', 'string', 'max:1000'],
        ], [
            'page_id.regex' => 'Die Seiten-ID besteht nur aus Ziffern. Sie finden sie auf Ihrer Facebook-Seite unter „Info“ › „Seitentransparenz“.',
            'token.required' => 'Bitte fügen Sie den Seiten-Zugriffsschlüssel ein.',
        ]);
        $token = $data['token'] ?: Meta::token();

        try {
            $info = $meta->verify($data['page_id'], $token);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['token' => $e->getMessage()]);
        }

        ShopSettings::putSecret('social_fb_token', $token);
        Setting::putMany([
            'social_fb_page_id' => $data['page_id'],
            'social_fb_name' => $info['name'],
            'social_ig_id' => $info['instagram_id'],
            'social_ig_name' => $info['instagram_name'],
        ]);

        return redirect()->route('admin.social')->with('status', 'Verbunden mit der Facebook-Seite „'.$info['name'].'“'
            .($info['instagram_id'] ? ' und Instagram @'.$info['instagram_name'].'.' : '. Ein Instagram-Konto ist mit dieser Seite nicht verknüpft.'));
    }

    public function disconnect(): RedirectResponse
    {
        ShopSettings::putSecret('social_fb_token', null);
        Setting::putMany(['social_fb_page_id' => null, 'social_fb_name' => null, 'social_ig_id' => null, 'social_ig_name' => null]);

        return redirect()->route('admin.social')->with('status', 'Die Verbindung zu Facebook und Instagram wurde getrennt.');
    }

    /** Textvorschlag für eine Seite, ein Produkt oder ein freies Thema */
    public function suggest(Request $request, SocialPublisher $publisher): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:2000'],
        ]);
        [$title, $text, $url, $keyword] = $this->source($data['source'], $data['topic'] ?? '');
        if ($title === '') {
            return response()->json(['message' => 'Bitte wählen Sie eine Seite oder ein Produkt, oder beschreiben Sie Ihr Thema.'], 422);
        }

        return response()->json($publisher->suggest($title, $text, $url, $keyword) + ['image' => $this->sourceImage($data['source']), 'link' => $url]);
    }

    public function publish(Request $request, SocialPublisher $publisher): RedirectResponse
    {
        $data = $request->validate([
            'networks' => ['required', 'array', 'min:1'],
            'networks.*' => [Rule::in(array_keys(SocialPost::NETWORKS))],
            'text' => ['required', 'string', 'max:2200'],
            'source' => ['nullable', 'string', 'max:40'],
        ], [
            'networks.required' => 'Bitte wählen Sie Facebook, Instagram oder beides.',
            'text.required' => 'Bitte schreiben Sie einen Text für den Beitrag.',
        ]);
        $source = $data['source'] ?? '';
        $link = $this->source($source, '')[2];
        $image = $this->sourceImage($source);

        $results = [];
        foreach ($data['networks'] as $network) {
            if (! Meta::connected($network)) {
                $results[] = SocialPost::NETWORKS[$network].': noch nicht verbunden.';

                continue;
            }
            $post = $publisher->now($network, $data['text'], $link, $image);
            $results[] = $post->networkName().': '.($post->status === 'sent' ? 'veröffentlicht.' : $post->error);
        }

        return redirect()->route('admin.social')->with('status', implode(' ', $results));
    }

    public function retry(SocialPost $post, SocialPublisher $publisher): RedirectResponse
    {
        abort_if($post->status === 'sent', 404);
        $publisher->send($post);
        $post->refresh();

        return redirect()->route('admin.social')->with('status', $post->status === 'sent' ? 'Beitrag veröffentlicht.' : $post->error);
    }

    /** @return array{0: string, 1: string, 2: ?string, 3: ?string} Titel, Text, Link, Suchbegriff */
    private function source(string $source, string $topic): array
    {
        if (preg_match('/^page:(\d+)$/', $source, $m) && ($page = Page::find($m[1]))) {
            return [$page->title, $page->meta_description.' '.$page->plainText(), $page->url(), $page->focus_keyword];
        }
        if (preg_match('/^product:(\d+)$/', $source, $m) && ($product = Product::active()->find($m[1]))) {
            return [$product->name, $product->short_description.' '.strip_tags((string) $product->description).' Preis: '.$product->price(), $product->url(), null];
        }
        $topic = trim($topic);

        return [Str::limit($topic, 80, ''), $topic, null, null];
    }

    private function sourceImage(string $source): ?string
    {
        if (preg_match('/^page:(\d+)$/', $source, $m) && ($page = Page::find($m[1]))) {
            return app(SocialPublisher::class)->image($page);
        }
        if (preg_match('/^product:(\d+)$/', $source, $m) && ($product = Product::find($m[1])) && $product->image()) {
            return url($product->image());
        }

        return null;
    }
}
