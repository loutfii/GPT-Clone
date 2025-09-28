<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserSettingsController extends Controller
{
    public function edit()
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $settings = $user->settings; // peut être null

        $json = $settings?->preferences ?? [];

        $prefs = [
            'tone'          => $settings?->tone          ?? ($json['tone']          ?? 'neutral'),
            'style'         => $settings?->style         ?? ($json['style']         ?? 'concise'),
            'context'       => $settings?->context       ?? ($json['context']       ?? ''),
            'custom_system' => $settings?->custom_system ?? ($json['custom_system'] ?? ''),
        ];

        return \Inertia\Inertia::render('Settings', [
            'preferences' => $prefs,
        ]);
    }


    public function update(Request $request)
    {
        $data = $request->validate([
            'tone'          => ['nullable','string','max:50'],
            'style'         => ['nullable','string','max:50'],
            'context'       => ['nullable','string','max:2000'],
            'custom_system' => ['nullable','string','max:8000'],
        ]);

        $user = Auth::user();

        $settings = UserSetting::firstOrNew(['user_id' => $user->id]);

        // On persiste dans les colonnes (cibles des tests)
        $settings->tone          = $data['tone']          ?? $settings->tone;
        $settings->style         = $data['style']         ?? $settings->style;
        $settings->context       = $data['context']       ?? $settings->context;
        $settings->custom_system = $data['custom_system'] ?? $settings->custom_system;

        // (Optionnel) garder à jour le JSON 'preferences' pour compat
        $settings->preferences = array_merge($settings->preferences ?? [], $data);

        $settings->user_id = $user->id;
        $settings->save();

        return back()->with('success', 'Preferences saved.');
    }
}
