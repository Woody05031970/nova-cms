<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EditorialItem;
use App\Models\Page;
use App\Models\SocialPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Modul „Redaktionsplan“: Kalender mit geplanten Seiten, Beiträgen, Themen und Aktionen */
class EditorialController extends Controller
{
    public function index(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('monat'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('monat').'-01')->startOfDay()
            : now()->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $entries = collect();
        foreach (EditorialItem::whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date')->get() as $item) {
            $entries->push(['day' => $item->date->toDateString(), 'kind' => 'item', 'channel' => $item->channel, 'title' => $item->title, 'item' => $item, 'done' => $item->done]);
        }
        foreach (Page::where('status', 'scheduled')->whereBetween('published_at', [$from, $to])->get() as $page) {
            $entries->push(['day' => $page->published_at->toDateString(), 'kind' => 'page', 'channel' => 'website', 'title' => $page->title, 'page' => $page,
                'time' => $page->published_at->format('H:i'), 'done' => false]);
        }
        // Bereits veröffentlichte Seiten pro Tag zusammengefasst, damit der Kalender übersichtlich bleibt
        $published = Page::where('status', 'published')->whereBetween('published_at', [$from, $to])->orderBy('published_at')->get()
            ->groupBy(fn (Page $p) => $p->published_at->toDateString());
        foreach ($published as $day => $pages) {
            $entries->push(['day' => $day, 'kind' => 'published', 'channel' => 'website', 'title' => $pages->count() === 1 ? $pages->first()->title
                : $pages->take(2)->pluck('title')->implode(', ').($pages->count() > 2 ? ' und '.($pages->count() - 2).' weitere' : ''),
                'count' => $pages->count(), 'page' => $pages->count() === 1 ? $pages->first() : null, 'done' => true]);
        }
        foreach (SocialPost::whereBetween(DB::raw('COALESCE(sent_at, scheduled_at)'), [$from, $to])->get() as $post) {
            $at = $post->sent_at ?? $post->scheduled_at;
            $entries->push(['day' => $at->toDateString(), 'kind' => 'social', 'channel' => $post->network, 'title' => Str::limit($post->text, 60),
                'time' => $at->format('H:i'), 'done' => $post->status === 'sent', 'failed' => $post->status === 'failed']);
        }

        return view('admin.plan.index', [
            'month' => $month,
            'days' => $entries->sortBy(fn ($e) => $e['day'].($e['time'] ?? '00:00'))->groupBy('day'),
            'upcoming' => Page::where('status', 'scheduled')->orderBy('published_at')->limit(10)->get(),
            'channels' => EditorialItem::CHANNELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = EditorialItem::create($this->validated($request));

        return redirect()->route('admin.plan', ['monat' => $item->date->format('Y-m')])->with('status', '„'.$item->title.'“ steht im Plan.');
    }

    public function update(Request $request, EditorialItem $item): RedirectResponse
    {
        if ($request->has('toggle')) {
            $item->update(['done' => ! $item->done]);
        } else {
            $item->update($this->validated($request));
        }

        return redirect()->route('admin.plan', ['monat' => $item->date->format('Y-m')]);
    }

    public function destroy(EditorialItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('admin.plan', ['monat' => $item->date->format('Y-m')])->with('status', 'Eintrag entfernt.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'date' => ['required', 'date'],
            'channel' => ['required', Rule::in(array_keys(EditorialItem::CHANNELS))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['title.required' => 'Bitte geben Sie ein Thema an.', 'date.required' => 'Bitte wählen Sie ein Datum.']);
    }
}
