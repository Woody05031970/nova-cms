@extends('site.community._layout')
@section('ctitle', 'Profil bearbeiten')
@section('cnoindex', '1')
@section('main')
    <h1 class="text-2xl font-semibold tracking-tight">Profil bearbeiten</h1>
    <form method="POST" action="{{ route('community.update') }}" enctype="multipart/form-data" class="mt-5 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" data-profile-form>
        @csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="nova-label" for="name">Name</label><input class="nova-input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120"></div>
            <div><label class="nova-label" for="handle">Kurzname</label><div class="flex items-center"><span class="rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 py-2 text-slate-500">@</span><input class="nova-input rounded-l-none" id="handle" name="handle" value="{{ old('handle', $profile->handle) }}" required pattern="[a-z0-9_]{3,30}" maxlength="30"></div><p class="mt-1 text-xs text-slate-500">Für Ihre Adresse und Erwähnungen. Kleinbuchstaben, Ziffern, _</p></div>
        </div>
        <div><label class="nova-label" for="headline">Kurz über mich</label><input class="nova-input" id="headline" name="headline" value="{{ old('headline', $profile->headline) }}" maxlength="120" placeholder="z. B. Hobbygärtnerin aus Freiburg"></div>
        <div><label class="nova-label" for="bio">Über mich</label><textarea class="nova-input" id="bio" name="bio" rows="5" maxlength="2000">{{ old('bio', $profile->bio) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="nova-label" for="location">Ort</label><input class="nova-input" id="location" name="location" value="{{ old('location', $profile->location) }}" maxlength="80"></div>
            <div><label class="nova-label" for="website">Website</label><input class="nova-input" type="url" id="website" name="website" value="{{ old('website', $profile->website) }}" placeholder="https://"></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <fieldset class="rounded-xl border border-slate-200 p-4"><legend class="px-1 text-sm font-medium">Profilbild</legend>
                <div class="flex items-center gap-3">@include('site.community._avatar', ['p' => $profile, 'size' => 'size-14'])<input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="text-sm" aria-label="Profilbild hochladen"></div>
                @if ($profile->avatar)<label class="mt-2 flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_avatar" value="1" class="accent-nova-600"> Profilbild entfernen</label>@endif
            </fieldset>
            <fieldset class="rounded-xl border border-slate-200 p-4"><legend class="px-1 text-sm font-medium">Titelbild</legend>
                <input type="file" name="cover" accept="image/png,image/jpeg,image/webp,image/gif" class="text-sm" aria-label="Titelbild hochladen">
                <p class="mt-1 text-xs text-slate-500">Breites Bild, z. B. 1800 × 500 Pixel.</p>
                @if ($profile->cover)<label class="mt-2 flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_cover" value="1" class="accent-nova-600"> Titelbild entfernen</label>@endif
            </fieldset>
        </div>
        <fieldset class="space-y-2"><legend class="nova-label">Einstellungen</legend>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="messages_open" value="1" class="mt-1 accent-nova-600" @checked($profile->messages_open)><span>Andere Mitglieder dürfen mir persönliche Nachrichten schicken</span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="email_notifications" value="1" class="mt-1 accent-nova-600" @checked($profile->email_notifications)><span>Bei neuen Nachrichten, Antworten und Erwähnungen eine E-Mail bekommen</span></label>
        </fieldset>
        <button class="nova-btn">Speichern</button>
    </form>

    @unless ($user->isAdmin())
        <details class="mt-8 rounded-2xl border border-red-200 bg-white p-5">
            <summary class="cursor-pointer font-medium text-red-800">Konto löschen</summary>
            <p class="mt-2 text-sm text-slate-600">Ihr Konto, Ihr Profil, Ihre Beiträge, Kommentare, Nachrichten und Forenbeiträge werden endgültig gelöscht.</p>
            <form method="POST" action="{{ route('community.account.destroy') }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm('Wirklich alles endgültig löschen?')">
                @csrf @method('DELETE')
                <div><label class="nova-label" for="pw">Passwort zur Bestätigung</label><input class="nova-input" type="password" id="pw" name="password" required autocomplete="current-password"></div>
                <button class="rounded-xl bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800">Endgültig löschen</button>
            </form>
        </details>
    @endunless
@endsection
