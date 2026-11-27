<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\Hooks;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Hooks $hooks): View
    {
        $hints = collect($hooks->applyFilters('dashboard.hints', []))->map(function (array $hint) {
            try {
                $hint['done'] = (bool) ($hint['check'])();
            } catch (Throwable) {
                $hint['done'] = false;
            }

            return $hint;
        });

        $total = max($hints->count(), 1);
        $score = (int) round($hints->where('done', true)->count() / $total * 100);

        $widgets = collect($hooks->applyFilters('dashboard.widgets', []))
            ->sortBy('order')
            ->map(function (array $w) {
                try {
                    $w['html'] = (string) ($w['render'])();
                } catch (Throwable $e) {
                    report($e);
                    $w['html'] = '<p class="text-sm text-red-700">Diese Kachel konnte nicht geladen werden.</p>';
                }

                return $w;
            });

        return view('admin.dashboard', [
            'score' => $score,
            'open' => $hints->where('done', false)->values(),
            'doneCount' => $hints->where('done', true)->count(),
            'total' => $hints->count(),
            'widgets' => $widgets,
        ]);
    }
}
