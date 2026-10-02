<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')->name('login');
    Volt::route('forgot-password', 'pages.auth.forgot-password')->name('password.request');
    Volt::route('reset-password/{token}', 'pages.auth.reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', Logout::class)->name('logout');
});

// Opening /logout directly (refresh, back button, old bookmark) must not show an
// error or a blank page: send the visitor to the right place instead.
Route::get('logout', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));
