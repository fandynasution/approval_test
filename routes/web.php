<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\EnvController;

use App\Http\Controllers\Auth\AzureController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/env', [EnvController::class, 'index']);

Route::get('auth/azure', [AzureController::class, 'redirectToAzure'])->name('azure.login');
Route::get('auth/azure/callback', [AzureController::class, 'handleAzureCallback'])->name('azure.callback');

Route::get('/callback', function () {
    $user = Socialite::driver('azure')->user();

    // Menyimpan data pengguna ke session atau database jika diperlukan
    auth()->login($user);

    // Mengambil URL yang disimpan di session dan mengarahkan pengguna kembali ke sana
    $redirectUrl = session('redirect_url', '/default-url');
    return redirect($redirectUrl);
});
