<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', function (int $id, string $hash) {
    $user = User::findOrFail($id);

    abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return redirect(rtrim(config('app.frontend_url'), '/').'/profile?verified=1');
})->middleware('signed')->name('verification.verify');
