<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AzureController extends Controller
{
    public function redirectToAzure()
    {
        return Socialite::driver('microsoft')
                        ->setTenantId(config('services.microsoft.tenant'))
                        ->redirect();
    }

    public function handleAzureCallback()
    {
        try {
            $azureUser = Socialite::driver('microsoft')->user();

            // Find or create the user in your database
            $user = User::firstOrCreate(
                ['email' => $azureUser->getEmail()],
                ['name' => $azureUser->getName()]
            );

            // Log the user in
            Auth::login($user);

            return redirect()->intended('/');
        } catch (\Exception $e) {
            \Log::error('Azure Authentication Error:', ['error' => $e->getMessage()]);
            return redirect()->route('azure.login')->withErrors(['error' => 'Failed to authenticate.']);
        }
    }
}
