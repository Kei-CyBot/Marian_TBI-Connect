<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\View\View;

class EventsController extends Controller
{
    public function index(): View
    {
        $broadcastContacts = Contact::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'side']);

        return view('events', [
            'broadcastContacts' => $broadcastContacts,
            'mailConfigured' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }
}
