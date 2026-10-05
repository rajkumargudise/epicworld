<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function editPassword(): View
    {
        return view('account.password', [
            'seoTitle' => 'Change password',
            'seoDescription' => 'Change your password.',
            'canonicalUrl' => route('account.password.edit'),
            'indexable' => false,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return redirect()->route('account.password.edit')->with('status', 'Password updated.');
    }
}
