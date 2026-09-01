<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas base (grupo web)
|--------------------------------------------------------------------------
| Landing pública. As demais vivem em routes/web/** (auto-carregadas pelo
| RouteServiceProvider). Ver CLAUDE.md.
*/

Route::view('/login', 'web.landing')->name('landing');
