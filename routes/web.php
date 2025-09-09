<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UgpassController;
use App\Http\Controllers\UgpassSigningController;


Route::get('/', function () {
    return view('welcome');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/ugpass/login', [UgpassController::class, 'start'])->name('ugpass.start');
Route::get('/callback', [UgpassController::class, 'callback'])->name('ugpass.callback');
Route::get('/dashboard', function () {
    $user = session('ugpass_user');
    return view('dashboard', ['user' => $user]);
})->name('dashboard');


Route::get('/upload', function () {
    return "Here is where you will upload and sign documents.";
})->name('upload');

Route::get('/ugpass/logout', [UgpassController::class, 'logout'])->name('ugpass.logout');

Route::get('/sign-ui', function () {
    return view('sign-ui');
})->name('sign.ui');
Route::get('/signed/{file}', function ($file) {
    $path = storage_path('app/signed/' . $file);

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->download($path);
})->name('download.signed');



Route::post('/sign-single', [UgpassSigningController::class, 'signSingle'])->name('sign.single');
Route::post('/sign-bulk', [UgpassSigningController::class, 'bulkSign'])->name('sign.bulk');
Route::post('/sign-qr', [UgpassSigningController::class, 'embedQr'])->name('sign.qr');
require __DIR__.'/auth.php';


