<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

// Páginas públicas do site. Páginas estáticas usam Route::view; as com lógica, um Controller.
Route::view('/', 'web.home')->name('site.home');
Route::view('/platform', 'web.platform')->name('site.platform');
Route::view('/pricing', 'web.pricing')->name('site.pricing');
Route::view('/about', 'web.about')->name('site.about');

Route::get('/contact', [ContactController::class, 'show'])->name('site.contact');
Route::post('/contact', [ContactController::class, 'store'])->name('site.contact.store');
