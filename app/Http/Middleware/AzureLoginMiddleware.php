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
        if (!Auth::check()) {
            return redirect()->route('azure.login'); // Redirect to Azure login route
        }

        return $next($request);
    }
}
