<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Licensing\Features;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\SocialPost;
use App\Support\Pages\PageTemplates;
use App\Support\Social\SocialPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $trash = $request->boolean('papierkorb');

        $pages = ($trash ? Page::onlyTrashed() : Page::query()->main()->with('translations'))
            ->orderByDesc('is_home')->orderBy('menu_order')->orderBy('title')->get();

        return view('admin.pages.index', [
            'pages' => $pages,
            'trash' => $trash,
            'trashCount' => Page::onlyTrashed()->count(),
            'templates' => PageTemplates::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['template' => ['required', Rule::in(array_keys(PageTemplates::all()))]]);
        $tpl = PageTemplates::make($data['template']);

        $isHome = ($tpl['is_home'] ?? false) && ! Page::where('is_home', true)->exists();

        $page = Page::create([
            'title' => $tpl['title'],
            'slug' => $isHome ? Page::uniqueSlug('startseite') : Page::uniqueSlug($tpl['title']),
            'blocks' => Page::cleanBlocks($tpl['blocks']),
            'is_home' => $isHome,
            'purpose' => $tpl['purpose'] ?? null,
            'show_in_menu' => $tpl['show_in_menu'] ?? true,
            'menu_order' => (int) Page::max('menu_order') + 1,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Seite angelegt. Passen Sie die Inhalte an und klicken Sie auf "Veröffentlichen".');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', [
            'page' => $page,
            'revisions' => $page->revisions()->with('user')->limit(10)->get(),
            'socialPosts' => SocialPublisher::on() ? $page->socialPosts()->limit(5)->get() : collect(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'blocks' => ['required', 'json'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'show_in_menu' => ['boolean'],
            'is_home' => ['boolean'],
            'members_only' => ['nullable', 'boolean'],
            'action' => ['required', Rule::in(['save', 'publish', 'unpublish', 'schedule'])],
            'publish_at' => [Rule::excludeIf($request->input('action') !== 'schedule'), 'required', 'date', 'after:now'],
            'social_networks' => ['nullable', 'array'],
            'social_networks.*' => [Rule::in(array_keys(SocialPost::NETWORKS))],
            'social_text' => ['nullable', 'required_with:social_networks', 'string', 'max:2200'],
        ], [
            'publish_at.required' => 'Bitte wählen Sie Datum und Uhrzeit für die Veröffentlichung.',
            'publish_at.after' => 'Der Termin muss in der Zukunft liegen.',
            'social_text.required_with' => 'Bitte schreiben Sie einen Text für den Beitrag bei Facebook oder Instagram.',
            'title.required' => 'Bitte geben Sie der Seite einen Titel.',
            'meta_title.max' => 'Der Titel für Google sollte höchstens 70 Zeichen haben, sonst wird er abgeschnitten.',
        ]);

        $blocks = Page::cleanBlocks(json_decode($data['blocks'], true) ?: []);

        DB::transaction(function () use ($page, $data, $blocks, $request) {
            $page->saveRevision($request->user()->id);

            $isHome = $page->locale === null && (bool) ($data['is_home'] ?? false);
            if ($isHome && ! $page->is_home) {
                Page::where('is_home', true)->update(['is_home' => false]);
            }

            $schedule = $data['action'] === 'schedule' && ! $page->isPublished() && Features::on('redaktionsplan');
            $status = match (true) {
                $data['action'] === 'publish' => 'published',
                $data['action'] === 'unpublish' => 'draft',
                $schedule => 'scheduled',
                default => $page->status,
            };

            $page->update([
                'title' => $data['title'],
                'slug' => $isHome ? $page->slug : Page::uniqueSlug($data['slug'] ?: $data['title'], $page->id),
                'blocks' => $blocks,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'focus_keyword' => isset($data['focus_keyword']) ? trim($data['focus_keyword']) : null,
                'show_in_menu' => $page->locale === null && (bool) ($data['show_in_menu'] ?? false),
                'is_home' => $isHome,
                'visibility' => Features::on('mitgliederbereich') && $request->has('members_only') ? ($request->boolean('members_only') && ! $isHome ? 'members' : 'public') : $page->visibility,
                'status' => $status,
                'published_at' => match (true) {
                    $schedule => Carbon::parse($data['publish_at']),
                    $status === 'published' => $page->isScheduled() ? now() : ($page->published_at ?? now()),
                    $data['action'] === 'unpublish' && $page->isScheduled() => null,
                    default => $page->published_at,
                },
                'updated_by' => $request->user()->id,
            ]);
        });

        $page->refresh();
        $social = '';
        if (! empty($data['social_networks']) && SocialPublisher::on() && in_array($page->status, ['published', 'scheduled'], true)) {
            $posts = app(SocialPublisher::class)->forPage($page, $data['social_networks'], $data['social_text']);
            $social = ' '.collect($posts)->map(fn (SocialPost $p) => $p->networkName().': '.match ($p->status) {
                'sent' => 'Beitrag veröffentlicht.',
                'pending' => 'Beitrag folgt mit der Seite.',
                default => $p->error,
            })->implode(' ');
        }

        $message = match ($data['action']) {
            'publish' => 'Veröffentlicht. Ihre Änderungen sind jetzt auf der Website zu sehen.',
            'schedule' => $page->isScheduled() ? 'Geplant: Die Seite geht am '.$page->published_at->format('d.m.Y').' um '.$page->published_at->format('H:i').' Uhr automatisch online.' : 'Gespeichert.',
            'unpublish' => 'Die Seite ist jetzt offline und nur noch für Sie sichtbar.',
            default => $page->isPublished() ? 'Gespeichert und auf der Website aktualisiert.' : 'Entwurf gespeichert.',
        };

        return redirect()->route('admin.pages.edit', $page)->with('status', $message.$social);
    }

    public function restoreRevision(Request $request, Page $page, PageRevision $revision): RedirectResponse
    {
        abort_unless($revision->page_id === $page->id, 404);

        $page->saveRevision($request->user()->id);
        $page->update([
            'title' => $revision->title,
            'blocks' => $revision->blocks,
            'meta_title' => $revision->meta_title,
            'meta_description' => $revision->meta_description,
        ]);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Die Version vom '.$revision->created_at->format('d.m.Y, H:i').' Uhr wurde wiederhergestellt.');
    }

    public function preview(Page $page): View
    {
        return view('site.page', ['page' => $page, 'preview' => true]);
    }

    /** Live-Vorschau im Editor: zeigt den aktuellen, noch nicht gespeicherten Stand. Speichert nichts. */
    public function livePreview(Request $request): Response
    {
        $data = $request->validate([
            'page' => ['required', 'integer', 'exists:pages,id'],
            'title' => ['nullable', 'string', 'max:160'],
            'blocks' => ['required', 'json'],
        ]);
        $page = Page::findOrFail($data['page'])->replicate();
        $page->id = (int) $data['page'];
        $page->exists = false;
        $page->title = $data['title'] ?: $page->title;
        $page->blocks = Page::cleanBlocks(json_decode($data['blocks'], true) ?: []);

        return response()->view('site.page', ['page' => $page, 'preview' => true, 'live' => true])
            ->header('X-Robots-Tag', 'noindex');
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->update(['is_home' => false]);
        $page->delete();

        return redirect()->route('admin.pages.index')->with('status', "„{$page->title}“ liegt jetzt im Papierkorb.");
    }

    public function restore(int $id): RedirectResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();

        return redirect()->route('admin.pages.index')->with('status', "„{$page->title}“ wurde wiederhergestellt.");
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->forceDelete();

        return redirect()->route('admin.pages.index', ['papierkorb' => 1])->with('status', "„{$page->title}“ wurde endgültig gelöscht.");
    }
}
