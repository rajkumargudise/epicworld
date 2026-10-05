<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The comment moderation desk. Nothing here is public until a
 * moderator marks a comment Approved. Reachable only through the
 * admin group's access-admin gate (editors and admins).
 */
class CommentModerationController extends Controller
{
    private const STATUSES = [Comment::PENDING, Comment::APPROVED, Comment::REJECTED, Comment::SPAM];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : Comment::PENDING;

        return view('admin.comments', [
            'status' => $status,
            'comments' => Comment::query()->with('article')->where('status', $status)->latest()->paginate(25)->withQueryString(),
            'counts' => Comment::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:approve,reject,spam,delete'],
        ]);

        $query = Comment::query()->whereIn('id', $data['ids']);

        match ($data['action']) {
            'approve' => $query->update(['status' => Comment::APPROVED, 'approved_at' => now()]),
            'reject' => $query->update(['status' => Comment::REJECTED, 'approved_at' => null]),
            'spam' => $query->update(['status' => Comment::SPAM, 'approved_at' => null]),
            'delete' => $query->delete(),
        };

        return back()->with('status', 'Comments updated.');
    }
}
