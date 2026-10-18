<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings', [
            'fields' => Setting::FIELDS,
            'values' => collect(Setting::FIELDS)->mapWithKeys(fn (array $field, string $key): array => [$key => Setting::get($key)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            collect(Setting::FIELDS)->mapWithKeys(fn (array $field, string $key): array => [$key => ['nullable', 'string', 'max:2000']])->all()
        );

        foreach ($validated as $key => $value) {
            Setting::put($key, $value);
        }

        return back()->with('status', 'Instellingen opgeslagen.');
    }
}
