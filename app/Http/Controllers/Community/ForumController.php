<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\ForumBoard;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Community\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Community: Foren mit Themen und Antworten */
class ForumController extends Controller
{
    public function index(): View
    {
        $boards = ForumBoard::orderBy('position')->orderBy('name')->get();
        $latest = ForumTopic::with(['lastUser', 'user'])->whereIn('board_id', $boards->pluck('id'))
            ->orderByRaw('COALESCE(last_reply_at, created_at) DESC')->get()->unique('board_id')->keyBy('board_id');

        return view('site.community.forums', ['boards' => $boards, 'latest' => $latest]);
    }

    public function board(ForumBoard $board): View
    {
        $topics = $board->topics()->with(['user.communityProfile', 'lastUser'])
            ->orderByDesc('is_pinned')->orderByRaw('COALESCE(last_reply_at, created_at) DESC')->paginate(25);

        return view('site.community.board', ['board' => $board, 'topics' => $topics]);
    }

    public function create(Request $request, ForumBoard $board): View
    {
        abort_if($board->is_locked && ! $request->user()->isAdmin(), 403, 'In diesem Forum schreiben nur die Betreiber.');

        return view('site.community.topic-create', ['board' => $board]);
    }

    public function store(Request $request, ForumBoard $board): RedirectResponse
    {
        abort_if($board->is_locked && ! $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
        ], ['title.required' => 'Bitte geben Sie dem Thema einen Titel.', 'title.min' => 'Der Titel ist etwas kurz.', 'body.required' => 'Bitte schreiben Sie Ihren Beitrag.']);
        $user = $request->user();
        Community::profile($user);
        $topic = $board->topics()->create([
            'user_id' => $user->id, 'title' => trim($data['title']), 'slug' => Str::limit(Str::slug($data['title']), 80, '') ?: 'thema', 'body' => trim($data['body']),
            'last_reply_at' => now(), 'last_user_id' => $user->id,
        ]);
        $board->increment('topics_count');
        Community::mentions($topic->body, $user, 'im Forum', $topic->url());

        return redirect()->to($topic->url())->with('status', 'Ihr Thema ist eröffnet.');
    }

    public function topic(Request $request, ForumTopic $topic, ?string $slug = null): View|RedirectResponse
    {
        if ($slug !== $topic->slug) {
            return redirect()->to($topic->url(), 301);
        }
        if ($request->user()?->id !== $topic->user_id) {
            $topic->increment('views_count');
        }
        $topic->load(['board', 'user.communityProfile']);

        return view('site.community.topic', [
            'topic' => $topic,
            'replies' => $topic->replies()->with('user.communityProfile')->paginate(30),
        ]);
    }

    public function reply(Request $request, ForumTopic $topic): RedirectResponse
    {
        $user = $request->user();
        abort_if($topic->is_locked && ! $user->isAdmin(), 403, 'Dieses Thema ist geschlossen.');
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']], ['body.required' => 'Bitte schreiben Sie Ihre Antwort.']);
        Community::profile($user);
        $reply = $topic->replies()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);
        $topic->forceFill(['replies_count' => $topic->replies()->count(), 'last_reply_at' => now(), 'last_user_id' => $user->id])->save();
        $topic->board->increment('replies_count');

        $page = (int) ceil($topic->replies_count / 30);
        $url = $topic->url().($page > 1 ? '?page='.$page : '').'#antwort-'.$reply->id;
        // Alle, die im Thema geschrieben haben, bekommen einen Hinweis (Ersteller zusätzlich per E-Mail)
        $ids = $topic->replies()->where('user_id', '!=', $user->id)->distinct()->pluck('user_id')->push($topic->user_id)->unique()->take(30);
        foreach (User::whereIn('id', $ids)->get() as $to) {
            Community::notify($to, $user, 'reply', $user->name.' hat im Thema „'.Str::limit($topic->title, 60).'“ geantwortet.', $url, $to->id === $topic->user_id);
        }
        Community::mentions($reply->body, $user, 'im Forum', $url);

        return redirect()->to($url);
    }

    /** Anheften, schließen (nur Administratoren) */
    public function moderate(Request $request, ForumTopic $topic): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $field = $request->input('toggle') === 'lock' ? 'is_locked' : 'is_pinned';
        $topic->forceFill([$field => ! $topic->{$field}])->save();

        return back()->with('status', match ($field) {
            'is_locked' => $topic->is_locked ? 'Das Thema ist geschlossen.' : 'Das Thema ist wieder offen.',
            default => $topic->is_pinned ? 'Das Thema steht jetzt oben.' : 'Das Thema ist nicht mehr angeheftet.',
        });
    }

    public function destroy(Request $request, ForumTopic $topic): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($topic->user_id === $user->id && $topic->replies_count === 0), 403);
        $board = $topic->board;
        $topic->delete();
        $board->recount();

        return redirect()->to($board->url())->with('status', 'Das Thema wurde gelöscht.');
    }

    public function destroyReply(Request $request, ForumReply $reply): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $reply->user_id === $request->user()->id, 403);
        $topic = $reply->topic;
        $reply->delete();
        $topic->forceFill(['replies_count' => $topic->replies()->count()])->save();
        $topic->board->recount();

        return back()->with('status', 'Die Antwort wurde gelöscht.');
    }
}
