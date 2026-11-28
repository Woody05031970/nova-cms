<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\CommunityNotification;
use App\Models\CommunityPost;
use App\Models\CommunityProfile;
use App\Models\CommunityReport;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Community\Community;
use App\Support\Community\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Community: Pinnwand mit Beiträgen, Kommentaren und „Gefällt mir“, dazu Leute, Benachrichtigungen, Melden */
class FeedController extends Controller
{
    public function home(Request $request): View
    {
        $me = $request->user()?->canUseCommunity() ? $request->user() : null;
        $tag = mb_strtolower(trim((string) $request->query('tag')));
        $following = $me && $request->query('ansicht') === 'folge';

        $posts = CommunityPost::with(['user.communityProfile', 'comments.user.communityProfile'])
            ->whereHas('user.communityProfile', fn ($q) => $q->whereNull('banned_at'))
            ->when($tag !== '', fn ($q) => $q->where('body', 'like', '%#'.str_replace(['%', '_'], ['\%', '\_'], $tag).'%'))
            ->when($following, fn ($q) => $q->whereIn('user_id', DB::table('community_follows')->where('follower_id', $me->id)->select('followed_id')))
            ->orderByDesc('is_pinned')->latest()->orderByDesc('id')->paginate(15)->withQueryString();

        return view('site.community.home', [
            'posts' => $posts, 'tag' => $tag, 'following' => $following,
            'liked' => $me ? DB::table('community_likes')->where('user_id', $me->id)->whereIn('post_id', $posts->pluck('id'))->pluck('post_id')->all() : [],
            'topics' => ForumTopic::with('board')->latest('updated_at')->take(5)->get(),
            'newcomers' => CommunityProfile::with('user')->whereNull('banned_at')->whereHas('user', fn ($q) => $q->whereIn('role', ['member', 'admin']))->latest()->take(8)->get(),
        ]);
    }

    public function post(Request $request, CommunityPost $post): View
    {
        abort_if($post->user?->communityProfile?->isBanned() && ! $request->user()?->isAdmin(), 404);
        $post->load(['user.communityProfile', 'comments.user.communityProfile']);
        $me = $request->user();

        return view('site.community.post', [
            'post' => $post,
            'liked' => $me ? DB::table('community_likes')->where('user_id', $me->id)->where('post_id', $post->id)->pluck('post_id')->all() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'image' => Images::rule(),
        ], ['body.required' => 'Schreiben Sie zuerst etwas.', 'body.max' => 'Ein Beitrag darf höchstens 5.000 Zeichen haben.']);
        $user = $request->user();
        Community::profile($user);
        $post = CommunityPost::create([
            'user_id' => $user->id, 'body' => trim($data['body']),
            'image' => $request->hasFile('image') ? Images::store($request->file('image'), 'image') : null,
        ]);
        Community::mentions($post->body, $user, 'in einem Beitrag', $post->url());

        return redirect()->route('community.home')->with('status', 'Ihr Beitrag ist veröffentlicht.');
    }

    public function destroy(Request $request, CommunityPost $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        Images::delete($post->image);
        $post->delete();

        return redirect()->route('community.home')->with('status', 'Der Beitrag wurde gelöscht.');
    }

    public function pin(Request $request, CommunityPost $post): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $post->forceFill(['is_pinned' => ! $post->is_pinned])->save();

        return back()->with('status', $post->is_pinned ? 'Der Beitrag steht jetzt oben.' : 'Der Beitrag ist nicht mehr angeheftet.');
    }

    public function like(Request $request, CommunityPost $post): RedirectResponse
    {
        $user = $request->user();
        $exists = DB::table('community_likes')->where('post_id', $post->id)->where('user_id', $user->id);
        if ($exists->exists()) {
            $exists->delete();
        } else {
            DB::table('community_likes')->insert(['post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now()]);
            if ($post->user) {
                Community::notify($post->user, $user, 'like', $user->name.' gefällt Ihr Beitrag.', $post->url());
            }
        }
        $post->forceFill(['likes_count' => DB::table('community_likes')->where('post_id', $post->id)->count()])->saveQuietly();

        return back()->withFragment('beitrag-'.$post->id);
    }

    public function comment(Request $request, CommunityPost $post): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Schreiben Sie zuerst einen Kommentar.']);
        $user = $request->user();
        Community::profile($user);
        $post->comments()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);
        $post->forceFill(['comments_count' => $post->comments()->count()])->saveQuietly();
        if ($post->user) {
            Community::notify($post->user, $user, 'comment', $user->name.' hat Ihren Beitrag kommentiert.', $post->url());
        }
        Community::mentions($data['body'], $user, 'in einem Kommentar', $post->url());

        return back()->withFragment('beitrag-'.$post->id);
    }

    public function commentDestroy(Request $request, CommunityComment $comment): RedirectResponse
    {
        abort_unless($comment->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $post = $comment->post;
        $comment->delete();
        $post?->forceFill(['comments_count' => $post->comments()->count()])->saveQuietly();

        return back()->with('status', 'Der Kommentar wurde gelöscht.');
    }

    public function people(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $profiles = CommunityProfile::with('user')->whereNull('banned_at')
            ->whereHas('user', fn ($u) => $u->whereIn('role', ['member', 'admin'])->when($q !== '', fn ($w) => $w->where('name', 'like', '%'.$q.'%')))
            ->orderByDesc('last_seen_at')->paginate(24)->withQueryString();

        return view('site.community.people', ['profiles' => $profiles, 'q' => $q]);
    }

    public function notifications(Request $request): View
    {
        $items = CommunityNotification::with('actor.communityProfile')->where('user_id', $request->user()->id)->latest()->take(60)->get();
        CommunityNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('site.community.notifications', ['items' => $items]);
    }

    public function report(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CommunityReport::TYPES))],
            'id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Bitte schreiben Sie kurz, was nicht in Ordnung ist.']);
        $report = new CommunityReport(['type' => $data['type'], 'target_id' => $data['id'], 'reason' => $data['reason']]);
        abort_unless($report->target(), 404);
        $report->user_id = $request->user()->id;
        $report->save();

        return back()->with('status', 'Danke! Wir sehen uns die Meldung an.');
    }

    public function follow(Request $request, CommunityProfile $profile): RedirectResponse
    {
        $me = $request->user();
        abort_if($profile->user_id === $me->id, 422);
        $q = DB::table('community_follows')->where('follower_id', $me->id)->where('followed_id', $profile->user_id);
        if ($q->exists()) {
            $q->delete();
        } else {
            DB::table('community_follows')->insert(['follower_id' => $me->id, 'followed_id' => $profile->user_id, 'created_at' => now()]);
            Community::notify($profile->user, $me, 'follow', $me->name.' folgt Ihnen jetzt.', Community::profile($me)->url());
        }

        return back();
    }

    public function block(Request $request, CommunityProfile $profile): RedirectResponse
    {
        $me = $request->user();
        abort_if($profile->user_id === $me->id, 422);
        $q = DB::table('community_blocks')->where('user_id', $me->id)->where('blocked_id', $profile->user_id);
        if ($q->exists()) {
            $q->delete();
            $msg = $profile->user->name.' ist nicht mehr blockiert.';
        } else {
            DB::table('community_blocks')->insert(['user_id' => $me->id, 'blocked_id' => $profile->user_id, 'created_at' => now()]);
            DB::table('community_follows')->where(fn ($w) => $w->where('follower_id', $me->id)->where('followed_id', $profile->user_id))
                ->orWhere(fn ($w) => $w->where('follower_id', $profile->user_id)->where('followed_id', $me->id))->delete();
            $msg = $profile->user->name.' ist blockiert und kann Ihnen keine Nachrichten mehr schicken.';
        }

        return back()->with('status', $msg);
    }

    /** Zählt für die Navigation */
    public static function badge(?User $user): array
    {
        return $user?->canUseCommunity() ? Community::counts($user) : ['messages' => 0, 'notifications' => 0];
    }
}
