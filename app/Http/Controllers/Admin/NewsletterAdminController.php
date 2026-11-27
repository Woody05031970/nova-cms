<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Support\Blocks\BlockSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Newsletter in der Verwaltung: Abonnenten, Ausgaben schreiben, testen und versenden. */
class NewsletterAdminController extends Controller
{
    /** So viele E-Mails pro Schritt; der Browser ruft die Schritte nacheinander auf. */
    public const BATCH = 20;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('suche'));

        return view('admin.newsletter.index', [
            'campaigns' => NewsletterCampaign::latest('id')->get(),
            'subscribers' => NewsletterSubscriber::when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
                ->latest('id')->paginate(25)->withQueryString(),
            'stats' => [
                'confirmed' => NewsletterSubscriber::confirmed()->count(),
                'pending' => NewsletterSubscriber::where('status', 'pending')->count(),
                'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
                'last30' => NewsletterSubscriber::confirmed()->where('confirmed_at', '>=', now()->subDays(30))->count(),
            ],
            'search' => $q,
        ]);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // damit Excel Umlaute richtig zeigt
            fputcsv($out, ['E-Mail', 'Name', 'Status', 'Angemeldet am', 'Bestätigt am'], ';');
            NewsletterSubscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, $s->name, $s->statusLabel(), $s->created_at?->format('d.m.Y H:i'), $s->confirmed_at?->format('d.m.Y H:i')], ';');
                }
            });
            fclose($out);
        }, 'newsletter-abonnenten-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroySubscriber(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('status', "{$subscriber->email} wurde gelöscht.");
    }

    public function create(): View
    {
        return view('admin.newsletter.edit', ['campaign' => new NewsletterCampaign(['status' => 'draft']), 'recipients' => NewsletterSubscriber::confirmed()->count()]);
    }

    public function store(Request $request, BlockSanitizer $sanitizer): RedirectResponse
    {
        $campaign = NewsletterCampaign::create($this->validated($request, $sanitizer) + ['status' => 'draft']);

        return redirect()->route('admin.newsletter.edit', $campaign)->with('status', 'Entwurf gespeichert.');
    }

    public function edit(NewsletterCampaign $campaign): View
    {
        return view('admin.newsletter.edit', ['campaign' => $campaign, 'recipients' => NewsletterSubscriber::confirmed()->count()]);
    }

    public function update(Request $request, NewsletterCampaign $campaign, BlockSanitizer $sanitizer): RedirectResponse
    {
        abort_if($campaign->status !== 'draft', 422, 'Ein versendeter Newsletter kann nicht mehr geändert werden.');
        $campaign->update($this->validated($request, $sanitizer));

        return back()->with('status', 'Entwurf gespeichert.');
    }

    public function destroy(NewsletterCampaign $campaign): RedirectResponse
    {
        $campaign->delete();

        return redirect()->route('admin.newsletter.index')->with('status', 'Der Newsletter wurde gelöscht.');
    }

    public function test(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        try {
            Mail::to($request->user()->email)->send(new NewsletterMail($campaign));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['mail' => 'Die Test-E-Mail konnte nicht gesendet werden. Bitte prüfen Sie den E-Mail-Versand unter Einstellungen.']);
        }

        return back()->with('status', 'Test-E-Mail an '.$request->user()->email.' gesendet.');
    }

    /** Sendet den nächsten Schwung. Gibt den Fortschritt zurück, bis alle erreicht sind. */
    public function sendBatch(NewsletterCampaign $campaign): JsonResponse
    {
        if ($campaign->status === 'sent') {
            return response()->json(['done' => true, 'sent' => $campaign->sent_count, 'total' => $campaign->total]);
        }
        if ($campaign->status === 'draft') {
            $campaign->update(['status' => 'sending', 'cursor' => 0, 'sent_count' => 0, 'failed_count' => 0, 'total' => NewsletterSubscriber::confirmed()->count()]);
        }

        $batch = NewsletterSubscriber::confirmed()->where('id', '>', $campaign->cursor)->orderBy('id')->limit(self::BATCH)->get();
        foreach ($batch as $sub) {
            try {
                Mail::to($sub->email, $sub->name)->send(new NewsletterMail($campaign, $sub));
                $campaign->sent_count++;
            } catch (Throwable $e) {
                report($e);
                $campaign->failed_count++;
            }
            $campaign->cursor = $sub->id;
        }
        $done = $batch->count() < self::BATCH;
        if ($done) {
            $campaign->status = 'sent';
            $campaign->sent_at = now();
        }
        $campaign->save();

        return response()->json(['done' => $done, 'sent' => $campaign->sent_count, 'failed' => $campaign->failed_count, 'total' => $campaign->total]);
    }

    private function validated(Request $request, BlockSanitizer $sanitizer): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'preheader' => ['nullable', 'string', 'max:150'],
            'heading' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
            'image' => ['nullable', 'string', 'max:500'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:500'],
        ], ['subject.required' => 'Bitte geben Sie einen Betreff ein.', 'body.required' => 'Bitte schreiben Sie einen Text.']);
        $data['image'] = $sanitizer->image($data['image'] ?? null);
        $url = trim((string) ($data['button_url'] ?? ''));
        // Relative Links zur eigenen Website werden für E-Mails vollständig gemacht
        $data['button_url'] = $url === '' ? null : (str_starts_with($url, '/') ? url($url) : (preg_match('#^https?://#i', $url) ? $url : 'https://'.$url));

        return $data;
    }
}
