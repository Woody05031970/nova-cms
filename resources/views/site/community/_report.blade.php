@if (\App\Support\Community\Community::me())
    <details class="group relative">
        <summary class="cursor-pointer list-none rounded-md px-2 py-1 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-800">Melden</summary>
        <form method="POST" action="{{ route('community.report') }}" class="absolute right-0 z-20 mt-1 w-72 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg">
            @csrf <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="id" value="{{ $id }}">
            <label class="text-sm font-medium" for="r-{{ $type }}-{{ $id }}">Was ist nicht in Ordnung?</label>
            <textarea id="r-{{ $type }}-{{ $id }}" name="reason" rows="2" maxlength="500" required class="nova-input mt-1 text-sm" placeholder="z. B. Werbung, Beleidigung"></textarea>
            <button class="mt-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">Meldung senden</button>
        </form>
    </details>
@endif
