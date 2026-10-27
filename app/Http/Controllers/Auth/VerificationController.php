<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function show(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect($this->redirectTo)
            : view('auth.verify');
    }

    public function verify(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect($this->redirectTo);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($request->user()));
        }

        return redirect($this->redirectTo)->with('verified', true);
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect($this->redirectTo);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('resent', true);
    }
}
