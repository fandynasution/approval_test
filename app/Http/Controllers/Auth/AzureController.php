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
        return Socialite::driver('azure')
                        ->setTenantId(config('services.azure.tenant'))
                        ->redirect();
    }

    public function handleAzureCallback()
    {
        try {
            $azureUser = Socialite::driver('azure')->user();

            // Log the Azure user for debugging
            \Log::info('Azure User:', (array)$azureUser);
    
            // Find or create the user in your database
            $user = User::firstOrCreate(
                ['email' => $azureUser->email],
                ['name' => $azureUser->name]
            );

            // Log the user in
            Auth::login($user);

            return redirect()->intended('/');
        } catch (\Exception $e) {
            \Log::error('Azure Authentication Error: '.$e->getMessage());
            return redirect()->route('azure.login')->withErrors(['error' => 'Failed to authenticate.']);
        }
    }
}
