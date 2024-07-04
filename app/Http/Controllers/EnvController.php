<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EnvController extends Controller
{
    public function index()
    {
        $envVariables = [
            'APP_ENV' => env('APP_ENV'),
            'APP_DEBUG' => env('APP_DEBUG'),
            'DB_CONNECTION' => env('DB_CONNECTION3'),
            'DB_HOST' => env('DB_HOST3'),
            'DB_PORT' => env('DB_PORT3'),
            'DB_DATABASE' => env('DB_DATABASE3'),
            'DB_USERNAME' => env('DB_USERNAME3'),
            // Add more variables as needed
        ];

        var_dump($envVariables);
    }
}
