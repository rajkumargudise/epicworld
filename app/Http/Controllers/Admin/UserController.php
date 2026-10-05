<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrator-only user management: see everyone with an account,
 * suspend/unsuspend, and change roles. An administrator can never
 * suspend or demote themselves (so the site can't lock itself out).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.users', [
            'users' => User::query()
                ->withCount(['articles as published_posts' => fn ($q) => $q->where('status', 'published')])
                ->orderByRaw("case role when 'admin' then 0 when 'editor' then 1 else 2 end")
                ->latest()
                ->paginate(30),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'role' => ['nullable', 'in:'.implode(',', [User::ROLE_CONTRIBUTOR, User::ROLE_EDITOR, User::ROLE_ADMIN])],
            'suspend' => ['nullable', 'boolean'],
        ]);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own role or suspend yourself.');
        }

        if (isset($data['role'])) {
            $user->role = $data['role'];
        }

        if ($request->has('suspend')) {
            $user->suspended_at = $request->boolean('suspend') ? now() : null;
        }

        $user->save();

        return back()->with('status', "Updated {$user->name}.");
    }
}
