<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Public sign-up for community contributors. A new account can only
 * ever be a contributor: it may submit posts for moderation but has no
 * access to the CMS, and nothing it writes is public until approved.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'seoTitle' => 'Join EPIC World',
            'seoDescription' => 'Create a contributor account to submit your own posts.',
            'canonicalUrl' => route('register'),
            'indexable' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real people never see or fill this field.
        if (filled($request->input('company_website'))) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[^<>]+$/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Please accept the Terms and Privacy Policy to continue.',
            'name.regex' => 'The name may not contain < or > characters.',
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => User::ROLE_CONTRIBUTOR,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('status', 'Welcome! Your account is ready — write your first post below.');
    }
}
