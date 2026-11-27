<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Modul „Kundenkonto“: Übersicht der registrierten Shop-Kunden */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.customers', [
            'customers' => User::where('role', 'customer')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('company', 'like', "%{$q}%")))
                ->withCount('orders')->withSum('orders', 'total_cents')->latest()->limit(500)->get(),
            'q' => $q,
            'total' => User::where('role', 'customer')->count(),
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404);
        $user->delete();

        return back()->with('status', 'Kundenkonto gelöscht. Die Bestellungen bleiben erhalten.');
    }
}
