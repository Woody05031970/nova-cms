<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Stats\Stats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $days = in_array((int) $request->query('tage'), [7, 30, 90], true) ? (int) $request->query('tage') : 30;
        $titles = Page::published()->get(['title', 'slug', 'is_home'])->mapWithKeys(fn ($p) => [$p->is_home ? '/' : '/'.$p->slug => $p->title]);

        return view('admin.stats', Stats::report($days) + ['days' => $days, 'titles' => $titles]);
    }
}
