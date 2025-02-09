<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AzureLoginMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Menyimpan URL yang diminta ke session
        if (!auth()->check()) {
            return redirect()->route('azure.login'); // Redirect to Azure login route
        }

        // if (!auth()->check()) {
        //     session(['redirect_url' => url()->current()]);

        //     // Mengarahkan ke halaman login Azure
        //     return Socialite::driver('azure')->redirect();
        // }

        return $next($request);
    }
}
