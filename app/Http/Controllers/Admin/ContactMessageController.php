<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Anfragen aus dem Kontaktformular lesen und löschen */
class ContactMessageController extends Controller
{
    public function index(): View
    {
        return view('admin.messages', ['messages' => ContactMessage::with('page')->latest('id')->paginate(30)]);
    }

    public function read(ContactMessage $message): RedirectResponse
    {
        $message->update(['read_at' => $message->read_at ? null : now()]);

        return back();
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return back()->with('status', 'Die Anfrage wurde gelöscht.');
    }
}
