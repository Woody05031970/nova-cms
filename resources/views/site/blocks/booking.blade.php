@if (\App\Support\Booking\BookingCalendar::on())
@php
    $services = \App\Models\Service::active()->get();
    if (! empty($d['service'])) { $services = $services->where('id', (int) $d['service'])->values(); }
@endphp
<section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" data-booking-block>
    @include('site.blocks.partials.heading')
    @if ($services->isEmpty())
        <p class="text-center text-slate-600">Zurzeit sind keine Termine online buchbar.</p>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $s)
                <li class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold">{{ $s->name }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ $s->durationLabel() }}@if($s->priceLabel()) · {{ $s->priceLabel() }}@endif</p>
                    @if ($s->description)<p class="mt-3 text-slate-600">{{ $s->description }}</p>@endif
                    <a href="{{ route('booking.show', ['leistung' => $s->id]) }}#buchung" class="mt-5 inline-flex items-center gap-2 self-start rounded-xl bg-nova-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-nova-700">{{ $d['button_label'] ?? 'Termin wählen' }}<span class="sr-only">: {{ $s->name }}</span> <x-icon name="arrow" class="size-4" /></a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif
