<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrator-only AI configuration. API keys are write-only: the
 * form never shows a stored key, only whether one is set, and a blank
 * key field leaves the stored key untouched.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('admin.settings', [
            'provider' => Setting::read('ai_provider') ?? config('ai.default'),
            'openaiModel' => Setting::read('openai_model') ?? config('ai.providers.openai.model'),
            'openaiKeySet' => Setting::read('openai_api_key') !== null || filled(config('ai.providers.openai.api_key')),
            'geminiKeySet' => Setting::read('gemini_api_key') !== null || filled(config('ai.providers.gemini.api_key')),
            'youtubeKeySet' => Setting::read('youtube_api_key') !== null || filled(config('newswire.youtube_api_key')),
            'pexelsKeySet' => Setting::read('pexels_api_key') !== null,
            'unsplashKeySet' => Setting::read('unsplash_access_key') !== null,
            'dailyCap' => Setting::read('ai_daily_article_cap') ?? config('editorial.daily_article_cap'),
            'geminiModel' => Setting::read('gemini_model') ?? config('ai.providers.gemini.model'),
            'ga4' => Setting::read('ga4_measurement_id'),
            'gsc' => Setting::read('gsc_verification'),
            'adsensePublisher' => Setting::read('adsense_publisher_id'),
            'adsenseEnabled' => Setting::read('adsense_enabled') === '1',
            'commentsEnabled' => Setting::read('comments_enabled') !== '0',
            'contactEmail' => Setting::read('contact_email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'ai_provider' => ['required', 'in:openai,gemini'],
            'openai_model' => ['nullable', 'string', 'max:100'],
            'openai_api_key' => ['nullable', 'string', 'max:300'],
            'gemini_api_key' => ['nullable', 'string', 'max:300'],
            'clear_openai_api_key' => ['nullable', 'boolean'],
            'clear_gemini_api_key' => ['nullable', 'boolean'],
            'youtube_api_key' => ['nullable', 'string', 'max:300'],
            'clear_youtube_api_key' => ['nullable', 'boolean'],
            'gemini_model' => ['nullable', 'string', 'max:100'],
            'pexels_api_key' => ['nullable', 'string', 'max:200'],
            'unsplash_access_key' => ['nullable', 'string', 'max:200'],
            'clear_pexels_api_key' => ['nullable', 'boolean'],
            'clear_unsplash_access_key' => ['nullable', 'boolean'],
            'ai_daily_article_cap' => ['nullable', 'integer', 'min:0', 'max:500'],
            'ga4_measurement_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{6,14}$/'],
            'gsc_verification' => ['nullable', 'string', 'max:500'],
            'adsense_publisher_id' => ['nullable', 'string', 'max:60'],
            'adsense_enabled' => ['nullable', 'boolean'],
            'comments_enabled' => ['nullable', 'boolean'],
            'contact_email' => ['nullable', 'email', 'max:150'],
        ], [
            'ga4_measurement_id.regex' => 'Google Analytics IDs look like G-ABC123DEF4.',
        ]);

        // Search Console: accept either the bare token or the whole pasted <meta> tag.
        $gsc = trim((string) ($data['gsc_verification'] ?? ''));
        if (preg_match('/content=(?:"([^"]+)"|\'([^\']+)\')/', $gsc, $m)) {
            $m[1] = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
            $gsc = $m[1];
        }
        if ($gsc !== '' && ! preg_match('/^[A-Za-z0-9_\-]{20,100}$/', $gsc)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['gsc_verification' => 'That does not look like a Search Console verification token.']);
        }

        // AdSense: accept "ca-pub-123..." or "pub-123..." or just the digits.
        $adsense = trim((string) ($data['adsense_publisher_id'] ?? ''));
        if ($adsense !== '') {
            $adsense = 'ca-pub-'.preg_replace('/^(ca-)?(pub-)?/i', '', $adsense);
            if (! preg_match('/^ca-pub-\d{10,20}$/', $adsense)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['adsense_publisher_id' => 'AdSense publisher IDs look like ca-pub-1234567890123456.']);
            }
        }

        Setting::write('gemini_model', $data['gemini_model'] ?? null);
        if ($request->has('ai_daily_article_cap')) {
            Setting::write('ai_daily_article_cap', isset($data['ai_daily_article_cap']) ? (string) $data['ai_daily_article_cap'] : null);
        }
        foreach (['pexels_api_key', 'unsplash_access_key'] as $imageKey) {
            if ($request->boolean('clear_'.$imageKey)) {
                Setting::write($imageKey, null);
            } elseif (filled($data[$imageKey] ?? null)) {
                Setting::write($imageKey, trim($data[$imageKey]));
            }
        }
        Setting::write('ga4_measurement_id', $data['ga4_measurement_id'] ?? null);
        Setting::write('gsc_verification', $gsc ?: null);
        Setting::write('adsense_publisher_id', $adsense ?: null);
        Setting::write('contact_email', $data['contact_email'] ?? null);

        // Toggles are only touched when the form actually sent them.
        if ($request->has('adsense_enabled')) {
            Setting::write('adsense_enabled', $request->boolean('adsense_enabled') ? '1' : '0');
        }
        if ($request->has('comments_enabled')) {
            Setting::write('comments_enabled', $request->boolean('comments_enabled') ? '1' : '0');
        }

        Setting::write('ai_provider', $data['ai_provider']);
        Setting::write('openai_model', $data['openai_model'] ?? null);

        foreach (['openai', 'gemini', 'youtube'] as $name) {
            if ($request->boolean("clear_{$name}_api_key")) {
                Setting::write("{$name}_api_key", null);
            } elseif (filled($data["{$name}_api_key"] ?? null)) {
                Setting::write("{$name}_api_key", trim($data["{$name}_api_key"]));
            }
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }

    public function editPassword(): View
    {
        return view('admin.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ]);

        $request->user()->update(['password' => $request->input('password')]);

        return redirect()->route('admin.password.edit')->with('status', 'Password updated.');
    }
}
