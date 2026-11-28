{{-- Name mit Link und Abzeichen --}}
@if ($u?->communityProfile)
    <a href="{{ $u->communityProfile->url() }}" class="font-semibold text-slate-900 hover:underline">{{ $u->name }}</a>@if ($u->isAdmin())<span class="ml-1.5 rounded bg-nova-50 px-1.5 py-0.5 align-middle text-[10px] font-semibold tracking-wide text-nova-700 uppercase">Team</span>@endif
@else
    <span class="font-semibold text-slate-500">{{ $u?->name ?? 'Gelöschtes Konto' }}</span>
@endif
