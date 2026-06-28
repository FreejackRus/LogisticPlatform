<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Complaint::class);

        return Inertia::render('Freight/Complaints', [
            'complaints' => Complaint::where('reporter_id', $request->user()->id)->latest()->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Complaint::class);

        Complaint::create([
            ...$request->validate([
                'target_user_id' => ['nullable', 'exists:users,id'],
                'load_id' => ['nullable', 'exists:loads,id'],
                'bid_id' => ['nullable', 'exists:bids,id'],
                'dispatcher_connection_id' => ['nullable', 'exists:dispatcher_connections,id'],
                'type' => ['required', 'in:fraud,spam,wrong_contacts,no_show,payment_issue,rude_behavior,other'],
                'message' => ['required', 'string', 'max:3000'],
            ]),
            'reporter_id' => $request->user()->id,
            'status' => 'new',
        ]);

        return back()->with('status', 'Жалоба отправлена.');
    }
}
