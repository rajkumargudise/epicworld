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
        ]);

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
