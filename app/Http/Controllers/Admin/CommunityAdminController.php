<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityMessage;
use App\Models\CommunityPost;
use App\Models\CommunityProfile;
use App\Models\CommunityReport;
use App\Models\ForumBoard;
use App\Models\ForumTopic;
use App\Models\Setting;
use App\Models\User;
use App\Support\Community\Community;
use App\Support\Community\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Modul „Community“ in der Verwaltung: Einstellungen, Foren, Meldungen, Sperren */
class CommunityAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.community', [
            'stats' => [
                'Mitglieder' => CommunityProfile::count(),
                'Beiträge' => CommunityPost::count(),
                'Forenthemen' => ForumTopic::count(),
                'Nachrichten' => CommunityMessage::count(),
                'Heute aktiv' => CommunityProfile::where('last_seen_at', '>=', now()->startOfDay())->count(),
            ],
            'boards' => ForumBoard::orderBy('position')->orderBy('name')->get(),
            'reports' => CommunityReport::with('user')->whereNull('resolved_at')->latest()->get(),
            'banned' => CommunityProfile::with('user')->whereNotNull('banned_at')->get(),
            's' => fn ($k, $d = null) => Setting::get($k, $d),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'community_title' => ['nullable', 'string', 'max:60'],
            'community_visibility' => ['required', Rule::in(['public', 'members'])],
            'members_registration' => ['required', Rule::in(['off', 'approval', 'open'])],
        ]);
        Setting::putMany([
            'community_title' => $data['community_title'] ?: null,
            'community_visibility' => $data['community_visibility'],
            'members_registration' => $data['members_registration'],
            'community_pages' => $request->boolean('community_pages') ? '1' : '0',
            'community_mail' => $request->boolean('community_mail') ? '1' : '0',
            'community_footer_link' => $request->boolean('community_footer_link') ? '1' : '0',
        ]);

        return back()->with('status', 'Die Einstellungen der Community sind gespeichert.');
    }

    public function storeBoard(Request $request): RedirectResponse
    {
        $data = $this->validatedBoard($request);
        ForumBoard::create($data + ['slug' => ForumBoard::uniqueSlug($data['name']), 'is_locked' => $request->boolean('is_locked')]);

        return back()->with('status', 'Das Forum „'.$data['name'].'“ ist angelegt.')->withFragment('foren');
    }

    public function updateBoard(Request $request, ForumBoard $board): RedirectResponse
    {
        $data = $this->validatedBoard($request);
        $board->update($data + ['is_locked' => $request->boolean('is_locked')]);

        return back()->with('status', 'Das Forum ist gespeichert.')->withFragment('foren');
    }

    public function destroyBoard(ForumBoard $board): RedirectResponse
    {
        $board->delete();

        return back()->with('status', 'Das Forum und alle Themen darin wurden gelöscht.')->withFragment('foren');
    }

    private function validatedBoard(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['name.required' => 'Bitte geben Sie dem Forum einen Namen.']) + ['position' => 0];
    }

    /** Meldung erledigen: nur abhaken oder den Inhalt löschen */
    public function resolve(Request $request, CommunityReport $report): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['dismiss', 'delete'])]])['action'];
        $target = $report->target();
        if ($action === 'delete' && $target) {
            match ($report->type) {
                'post' => (function () use ($target) {
                    Images::delete($target->image);
                    $target->delete();
                })(),
                'comment' => (function () use ($target) {
                    $post = $target->post;
                    $target->delete();
                    $post?->forceFill(['comments_count' => $post->comments()->count()])->saveQuietly();
                })(),
                'topic' => (function () use ($target) {
                    $board = $target->board;
                    $target->delete();
                    $board?->recount();
                })(),
                'reply' => (function () use ($target) {
                    $topic = $target->topic;
                    $target->delete();
                    $topic?->forceFill(['replies_count' => $topic->replies()->count()])->save();
                    $topic?->board?->recount();
                })(),
                'profile' => $target->update(['bio' => null, 'headline' => null, 'page_sections' => [], 'page_published' => false]),
                default => $target->delete(),
            };
        }
        // Alle Meldungen zum selben Inhalt gleich mit erledigen
        CommunityReport::where('type', $report->type)->where('target_id', $report->target_id)->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return back()->with('status', $action === 'delete' ? 'Der Inhalt wurde entfernt.' : 'Die Meldung ist erledigt.')->withFragment('meldungen');
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 422, 'Administratoren können nicht gesperrt werden.');
        $profile = Community::profile($user);
        $ban = ! $profile->isBanned();
        $profile->forceFill(['banned_at' => $ban ? now() : null])->save();
        if ($ban) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        }

        return back()->with('status', $ban ? $user->name.' ist in der Community gesperrt. Beiträge sind ausgeblendet.' : $user->name.' ist wieder freigeschaltet.');
    }

    /** Beschreibung eines gemeldeten Inhalts für die Liste */
    public static function describe(CommunityReport $r): array
    {
        $t = $r->target();
        if (! $t) {
            return ['Inhalt wurde schon gelöscht', null, null];
        }

        return match ($r->type) {
            'post' => [Str::limit($t->body, 160), $t->url(), $t->user],
            'comment' => [Str::limit($t->body, 160), $t->post?->url().'#kommentar-'.$t->id, $t->user],
            'topic' => [$t->title, $t->url(), $t->user],
            'reply' => [Str::limit($t->body, 160), $t->topic?->url().'#antwort-'.$t->id, $t->user],
            'message' => [Str::limit($t->body, 160), null, $t->user],
            'profile' => ['Profil von '.$t->user?->name, $t->url(), $t->user],
            default => ['', null, null],
        };
    }
}
