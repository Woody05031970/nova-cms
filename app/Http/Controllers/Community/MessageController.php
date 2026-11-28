<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityConversation;
use App\Models\CommunityProfile;
use App\Support\Community\Community;
use App\Support\Community\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Community: persönliche Nachrichten zwischen zwei Mitgliedern */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $conversations = CommunityConversation::for($me)->whereNotNull('last_message_at')
            ->with(['userOne.communityProfile', 'userTwo.communityProfile', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')->get();

        return view('site.community.messages', ['conversations' => $conversations, 'me' => $me]);
    }

    public function show(Request $request, CommunityProfile $profile): View|RedirectResponse
    {
        $me = $request->user();
        if ($profile->user_id === $me->id) {
            return redirect()->route('community.messages');
        }
        $other = $profile->user;
        [$one, $two] = $me->id < $other->id ? [$me->id, $other->id] : [$other->id, $me->id];
        $conversation = CommunityConversation::where('user_one_id', $one)->where('user_two_id', $two)->first();
        $messages = $conversation ? $conversation->messages()->with('user.communityProfile')->latest('id')->take(200)->get()->reverse()->values() : collect();
        $conversation?->markRead($me);

        return view('site.community.conversation', [
            'profile' => $profile, 'other' => $other, 'messages' => $messages, 'me' => $me,
            'reason' => $this->cannotSend($me, $profile),
        ]);
    }

    public function store(Request $request, CommunityProfile $profile): RedirectResponse
    {
        $me = $request->user();
        if ($reason = $this->cannotSend($me, $profile)) {
            return back()->withErrors(['body' => $reason]);
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Schreiben Sie zuerst eine Nachricht.']);
        $other = $profile->user;
        $conversation = CommunityConversation::between($me, $other);
        $wasRead = ! $conversation->isUnreadFor($other);
        $conversation->messages()->create(['user_id' => $me->id, 'body' => trim($data['body'])]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        $conversation->markRead($me);
        // E-Mail nur für die erste ungelesene Nachricht, damit niemand mit Mails überschüttet wird
        Community::notify($other, $me, 'message', 'Neue Nachricht von '.$me->name.': „'.Text::excerpt($data['body'], 80).'“', route('community.conversation', Community::profile($me)), $wasRead);

        return redirect()->route('community.conversation', $profile);
    }

    private function cannotSend($me, CommunityProfile $profile): ?string
    {
        $other = $profile->user;

        return match (true) {
            $other->id === $me->id => 'Sie können sich nicht selbst schreiben.',
            ! $other->canUseCommunity() => 'Diese Person ist in der Community nicht mehr aktiv.',
            Community::blocked($other, $me) || Community::blocked($me, $other) => 'Sie können dieser Person keine Nachrichten schicken.',
            ! $profile->messages_open && ! $me->isAdmin() => $other->name.' möchte keine persönlichen Nachrichten bekommen.',
            default => null,
        };
    }
}
