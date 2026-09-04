<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\TokenCookie;

class LogoutController extends Controller
{
    public function __invoke()
    {
        return redirect()->route('landing')->withCookie(TokenCookie::forget());
    }
}
