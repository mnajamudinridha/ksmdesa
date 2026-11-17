<?php

use Illuminate\Support\Facades\Route;

// Master site only. Every other host under APP_DOMAIN belongs to a KSM (KsmPanelProvider).
Route::domain(config('app.domain'))->group(function (): void {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');
});
