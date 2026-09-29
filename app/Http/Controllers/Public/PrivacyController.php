<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Milestone 18: a single static page describing the application's
 * actual current data practices - not a legal compliance
 * certification (see resources/views/public/privacy.blade.php's own
 * docblock and the "legal review pending" notice on the page itself).
 * No model or database table backs this; the content is accurate
 * only because it is kept in lockstep with what the application
 * actually does, not because anything here is dynamically verified.
 */
class PrivacyController extends Controller
{
    public function index(): View
    {
        return view('public.privacy', [
            'seoTitle' => 'Privacy — '.config('app.name', 'EPIC World'),
            'seoDescription' => 'How EPIC World handles data today, and what governs any future advertising or third-party services.',
            'canonicalUrl' => route('privacy'),
            'indexable' => true,
        ]);
    }
}
