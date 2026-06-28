<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => ['required', 'string', 'max:50', 'regex:/^\+?[0-9\s\-\(\)]{10,20}$/'],
            'role' => 'required|in:shipper,carrier',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'agree_to_terms' => ['accepted'],
            'agree_to_privacy' => ['accepted'],
            'agree_to_platform_role' => ['accepted'],
        ]);

        $acceptedAt = now();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'language_preference' => 'ru',
            'terms_accepted_at' => $acceptedAt,
            'privacy_accepted_at' => $acceptedAt,
            'platform_role_accepted_at' => $acceptedAt,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('freight.company.edit', absolute: false));
    }
}
