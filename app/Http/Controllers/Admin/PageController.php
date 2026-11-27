<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\Pages\PageTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $trash = $request->boolean('papierkorb');

        $pages = ($trash ? Page::onlyTrashed() : Page::query())
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
            'show_in_menu' => ['boolean'],
            'is_home' => ['boolean'],
            'action' => ['required', Rule::in(['save', 'publish', 'unpublish'])],
        ], [
            'title.required' => 'Bitte geben Sie der Seite einen Titel.',
            'meta_title.max' => 'Der Titel für Google sollte höchstens 70 Zeichen haben, sonst wird er abgeschnitten.',
        ]);

        $blocks = Page::cleanBlocks(json_decode($data['blocks'], true) ?: []);

        DB::transaction(function () use ($page, $data, $blocks, $request) {
            $page->saveRevision($request->user()->id);

            $isHome = (bool) ($data['is_home'] ?? false);
            if ($isHome && ! $page->is_home) {
                Page::where('is_home', true)->update(['is_home' => false]);
            }

            $status = match ($data['action']) {
                'publish' => 'published',
                'unpublish' => 'draft',
                default => $page->status,
            };

            $page->update([
                'title' => $data['title'],
                'slug' => $isHome ? $page->slug : Page::uniqueSlug($data['slug'] ?: $data['title'], $page->id),
                'blocks' => $blocks,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'show_in_menu' => (bool) ($data['show_in_menu'] ?? false),
                'is_home' => $isHome,
                'status' => $status,
                'published_at' => $status === 'published' ? ($page->published_at ?? now()) : $page->published_at,
                'updated_by' => $request->user()->id,
            ]);
        });

        $message = match ($data['action']) {
            'publish' => 'Veröffentlicht. Ihre Änderungen sind jetzt auf der Website zu sehen.',
            'unpublish' => 'Die Seite ist jetzt offline und nur noch für Sie sichtbar.',
            default => $page->isPublished() ? 'Gespeichert und auf der Website aktualisiert.' : 'Entwurf gespeichert.',
        };

        return redirect()->route('admin.pages.edit', $page)->with('status', $message);
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
