<?php

namespace App\Providers;

use Laravel\Socialite\SocialiteManager;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;

class AzureSocialiteProvider extends AbstractProvider
{
    const IDENTIFIER = 'microsoft';

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://login.microsoftonline.com/' . config('services.microsoft.tenant') . '/oauth2/v2.0/authorize',
            $state
        );
    }

    protected function getTokenUrl()
    {
        return 'https://login.microsoftonline.com/' . config('services.microsoft.tenant') . '/oauth2/v2.0/token';
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        return (new \Laravel\Socialite\Two\User())->setRaw($user)->map([
            'id'    => $user['id'],
            'email' => $user['userPrincipalName'],
            'name'  => $user['displayName'],
        ]);
    }

    protected function getCodeFields($state)
    {
        $fields = parent::getCodeFields($state);

        $fields['code_challenge'] = base64_encode(hash('sha256', session('pkce_code_verifier'), true));
        $fields['code_challenge_method'] = 'S256';

        return $fields;
    }

    protected function getTokenFields($code)
    {
        $fields = parent::getTokenFields($code);

        $fields['code_verifier'] = session('pkce_code_verifier');

        return $fields;
    }
}
