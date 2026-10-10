<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Newsletter sign-up. Stores the address only (no tracking); a honeypot and the
 * "contact" rate limit keep bots out. Repeat sign-ups are treated as success so
 * the form never reveals who is already subscribed.
 */
class SubscribeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $back = url()->previous(route('home')).'#subscribe';

        if (filled($request->input('website'))) {
            return redirect($back)->with('subscribe_status', 'Thanks for subscribing!');
        }

        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:150']]);

        Subscriber::firstOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            ['source' => $request->input('source') === 'article' ? 'article' : 'home'],
        );

        return redirect($back)->with('subscribe_status', 'Thanks for subscribing! We will email you our best new guides.');
    }
}
