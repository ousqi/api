<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdultAccessController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'intended' => ['nullable', 'string', 'max:2048'],
        ]);
        $password = config('services.adult.password');

        if (! is_string($password) || $password === '' || ! hash_equals($password, $validated['password'])) {
            return back()
                ->withErrors(['password' => '密码错误，请重试。'])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();
        $request->session()->put('adult.authorized_until', now()->addHours(2)->getTimestamp());

        $intended = $validated['intended'] ?? $request->session()->pull('adult.intended_url');
        $request->session()->forget('adult.intended_url');

        return redirect()->to($this->adultUrl($intended));
    }

    private function adultUrl(mixed $url): string
    {
        if (! is_string($url) || ($url !== '/adult' && ! str_starts_with($url, '/adult/'))) {
            return route('adult.category');
        }

        return $url;
    }
}
