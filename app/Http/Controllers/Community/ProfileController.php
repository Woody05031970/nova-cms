<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityProfile;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Support\Blocks\VideoUrl;
use App\Support\Community\Community;
use App\Support\Community\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Community: Profilseite, Profil bearbeiten, eigene Seite gestalten, Konto löschen */
class ProfileController extends Controller
{
    /** Abschnitte der eigenen Seite */
    public const SECTIONS = ['text' => 'Text', 'image' => 'Bild', 'video' => 'Video', 'links' => 'Links', 'quote' => 'Zitat'];

    private const MAX_SECTIONS = 20;

    public function show(Request $request, CommunityProfile $profile): View
    {
        $me = $request->user();
        abort_if($profile->isBanned() && ! $me?->isAdmin(), 404);
        $user = $profile->user;
        $posts = CommunityPost::with(['user.communityProfile', 'comments.user.communityProfile'])->where('user_id', $user->id)->latest()->orderByDesc('id')->paginate(10);

        return view('site.community.profile', [
            'profile' => $profile, 'user' => $user, 'posts' => $posts,
            'liked' => $me ? DB::table('community_likes')->where('user_id', $me->id)->whereIn('post_id', $posts->pluck('id'))->pluck('post_id')->all() : [],
            'stats' => [
                'Beiträge' => CommunityPost::where('user_id', $user->id)->count(),
                'Folgen ihr' => DB::table('community_follows')->where('followed_id', $user->id)->count(),
                'Folgt' => DB::table('community_follows')->where('follower_id', $user->id)->count(),
                'Forenbeiträge' => ForumTopic::where('user_id', $user->id)->count() + ForumReply::where('user_id', $user->id)->count(),
            ],
            'isMe' => $me?->id === $user->id,
            'following' => $me ? Community::follows($me, $user) : false,
            'blocked' => $me ? Community::blocked($me, $user) : false,
        ]);
    }

    public function page(Request $request, CommunityProfile $profile): View
    {
        $me = $request->user();
        $owner = $me?->id === $profile->user_id || $me?->isAdmin();
        abort_unless(Community::pagesAllowed() && ! $profile->isBanned() && ($profile->page_published || $owner), 404);

        return view('site.community.page', ['profile' => $profile, 'preview' => ! $profile->page_published]);
    }

    public function edit(Request $request): View
    {
        return view('site.community.edit', ['profile' => Community::profile($request->user()), 'user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = Community::profile($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'handle' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/', Rule::unique('community_profiles', 'handle')->ignore($profile->id), Rule::notIn(Community::RESERVED)],
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'avatar' => Images::rule(),
            'cover' => Images::rule(),
        ], [
            'handle.regex' => 'Der Kurzname darf nur Kleinbuchstaben, Ziffern und Unterstriche enthalten.',
            'handle.unique' => 'Dieser Kurzname ist schon vergeben.', 'handle.not_in' => 'Dieser Kurzname ist reserviert.',
            'website.url' => 'Bitte geben Sie eine vollständige Adresse mit https:// an.',
        ]);
        $user->forceFill(['name' => $data['name']])->save();
        $values = collect($data)->only(['handle', 'headline', 'bio', 'location', 'website'])->all();
        $values['email_notifications'] = $request->boolean('email_notifications');
        $values['messages_open'] = $request->boolean('messages_open');
        foreach (['avatar' => [512, true], 'cover' => [1800, false]] as $field => [$max, $square]) {
            if ($request->hasFile($field)) {
                Images::delete($profile->{$field});
                $values[$field] = Images::store($request->file($field), $field, $max, $square);
            } elseif ($request->boolean('remove_'.$field)) {
                Images::delete($profile->{$field});
                $values[$field] = null;
            }
        }
        $profile->update($values);

        return redirect()->route('community.profile', $profile)->with('status', 'Ihr Profil ist gespeichert.');
    }

    public function pageEdit(Request $request): View
    {
        abort_unless(Community::pagesAllowed(), 404);

        return view('site.community.page-edit', ['profile' => Community::profile($request->user())]);
    }

    /**
     * Speichert die eigene Seite. Über „op“ werden Abschnitte hinzugefügt, verschoben oder gelöscht,
     * dafür braucht es kein JavaScript.
     */
    public function pageUpdate(Request $request): RedirectResponse
    {
        abort_unless(Community::pagesAllowed(), 404);
        $profile = Community::profile($request->user());
        $request->validate([
            'page_title' => ['nullable', 'string', 'max:120'],
            'page_color' => ['nullable', Rule::in(array_keys(CommunityProfile::COLORS))],
            'sections' => ['nullable', 'array', 'max:'.self::MAX_SECTIONS],
            'sections.*.type' => ['required', Rule::in(array_keys(self::SECTIONS))],
            'sections.*.heading' => ['nullable', 'string', 'max:160'],
            'sections.*.text' => ['nullable', 'string', 'max:6000'],
            'sections.*.caption' => ['nullable', 'string', 'max:200'],
            'sections.*.url' => ['nullable', 'string', 'max:255'],
            'sections.*.upload' => Images::rule(),
        ], ['sections.*.upload.mimes' => 'Bitte laden Sie ein JPG-, PNG-, WebP- oder GIF-Bild hoch.', 'sections.*.upload.max' => 'Das Bild ist zu groß (höchstens 8 MB).']);

        $old = collect($profile->page_sections ?? [])->pluck('image')->filter()->all();
        $sections = [];
        foreach (array_values($request->input('sections', [])) as $i => $s) {
            $section = $this->cleanSection($s);
            if ($request->hasFile("sections.$i.upload")) {
                $section['image'] = Images::store($request->file("sections.$i.upload"), "sections.$i.upload");
            }
            $sections[] = $section;
        }

        $op = (string) $request->input('op');
        if (preg_match('/^add:(\w+)$/', $op, $m) && isset(self::SECTIONS[$m[1]]) && count($sections) < self::MAX_SECTIONS) {
            $sections[] = ['type' => $m[1]];
        } elseif (preg_match('/^(up|down|remove):(\d+)$/', $op, $m) && isset($sections[(int) $m[2]])) {
            $i = (int) $m[2];
            if ($m[1] === 'remove') {
                array_splice($sections, $i, 1);
            } else {
                $j = $m[1] === 'up' ? $i - 1 : $i + 1;
                if (isset($sections[$j])) {
                    [$sections[$i], $sections[$j]] = [$sections[$j], $sections[$i]];
                }
            }
        }

        // Bilder, die nicht mehr vorkommen, aufräumen
        $kept = collect($sections)->pluck('image')->filter()->all();
        foreach (array_diff($old, $kept) as $gone) {
            Images::delete($gone);
        }

        $publish = match ($op) {
            'publish' => true, 'unpublish' => false, default => $profile->page_published
        };
        $profile->update([
            'page_title' => $request->input('page_title'), 'page_color' => $request->input('page_color') ?: 'petrol',
            'page_sections' => $sections, 'page_published' => $publish,
        ]);

        $status = match (true) {
            $op === 'publish' => 'Ihre Seite ist jetzt für alle sichtbar.',
            $op === 'unpublish' => 'Ihre Seite ist wieder privat.',
            str_starts_with($op, 'add:') => 'Abschnitt hinzugefügt.',
            default => 'Gespeichert.',
        };

        return redirect()->to(route('community.page.edit').(str_starts_with($op, 'add:') ? '#abschnitt-'.(count($sections) - 1) : ''))->with('status', $status);
    }

    /** Nur erlaubte Felder je Abschnittstyp behalten */
    private function cleanSection(array $s): array
    {
        $type = $s['type'];
        $str = fn ($k, $max) => mb_substr(trim((string) ($s[$k] ?? '')), 0, $max);
        $out = ['type' => $type];
        switch ($type) {
            case 'text':
                $out += ['heading' => $str('heading', 160), 'text' => $str('text', 6000)];
                break;
            case 'quote':
                $out += ['text' => $str('text', 1000), 'caption' => $str('caption', 200)];
                break;
            case 'image':
                $out += ['image' => Images::valid($s['image'] ?? null) ? $s['image'] : null, 'caption' => $str('caption', 200)];
                break;
            case 'video':
                $url = $str('url', 255);
                $v = $url !== '' ? VideoUrl::parse($url) : null;
                $out += ['url' => $url, 'provider' => $v['provider'] ?? null, 'video_id' => $v['id'] ?? null, 'caption' => $str('caption', 200)];
                break;
            case 'links':
                // Eine Zeile pro Link: Beschriftung | https://adresse
                $lines = [];
                foreach (preg_split('/\R/', $str('text', 3000)) as $line) {
                    [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                    if ($url === '' && filter_var($label, FILTER_VALIDATE_URL)) {
                        [$label, $url] = [preg_replace('#^https?://(www\.)?#', '', $label), $label];
                    }
                    if ($label !== '' && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
                        $lines[] = $label.' | '.$url;
                    }
                }
                $out += ['heading' => $str('heading', 160), 'text' => implode("\n", array_slice($lines, 0, 12))];
                break;
        }

        return $out;
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 422, 'Administratoren löschen ihr Konto in der Verwaltung.');
        $request->validate(['password' => ['required', 'string']], ['password.required' => 'Bitte geben Sie zur Sicherheit Ihr Passwort ein.']);
        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Das Passwort stimmt nicht.']);
        }
        $profile = $user->communityProfile;
        foreach (array_merge([$profile?->avatar, $profile?->cover], collect($profile?->page_sections ?? [])->pluck('image')->all(), CommunityPost::where('user_id', $user->id)->pluck('image')->all()) as $img) {
            Images::delete($img);
        }
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Ihr Konto und alle Ihre Beiträge wurden gelöscht.');
    }
}
