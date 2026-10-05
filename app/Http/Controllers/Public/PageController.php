<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The static trust pages a news site needs (About, Contact, Terms,
 * Editorial policy, Write for us) and the contact form.
 */
class PageController extends Controller
{
    private const PAGES = [
        'about' => ['About EPIC World', 'Who we are, what we cover and how we work.'],
        'editorial-policy' => ['Editorial policy', 'How EPIC World sources, writes, reviews and corrects its stories, including how AI is used.'],
        'terms' => ['Terms of use', 'The terms for using EPIC World, submitting posts and commenting.'],
        'write-for-us' => ['Write for EPIC World', 'Guidelines for contributing a post to EPIC World.'],
    ];

    public function show(string $page): View
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        [$title, $description] = self::PAGES[$page];

        return view('pages.'.$page, [
            'seoTitle' => $title,
            'seoDescription' => $description,
            'canonicalUrl' => url('/'.$page),
            'indexable' => true,
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'seoTitle' => 'Contact us',
            'seoDescription' => 'Get in touch with the EPIC World team.',
            'canonicalUrl' => route('contact'),
            'indexable' => true,
            'formToken' => \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', 'Thanks - your message has been received.');
        }

        try {
            $issued = (int) \Illuminate\Support\Facades\Crypt::decryptString((string) $request->input('ct'));
        } catch (\Throwable) {
            $issued = 0;
        }

        $elapsed = now()->timestamp - $issued;

        if ($elapsed < 3 || $elapsed > 7200) {
            return redirect()->route('contact')->withInput()->with('error', 'That was a bit quick - please check your message and submit again.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[^<>]+$/u'],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
            'subject' => ['required', 'string', 'min:3', 'max:150', 'regex:/^[^<>]+$/u'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        ContactMessage::create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'subject' => trim($data['subject']),
            'message' => $data['message'],
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
        ]);

        return redirect()->route('contact')->with('status', 'Thanks - your message has been received. We read every message.');
    }
}
