@if (session('status'))
    <div class="mb-6 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
        <x-icon name="check" class="mt-0.5 size-5 shrink-0" />
        <span>{{ session('status') }}</span>
    </div>
@endif
@if ($errors->any())
    <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
        <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
        <div>
            @if ($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <p class="font-medium">Bitte prüfen Sie folgende Angaben:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
