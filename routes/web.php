<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderLocationController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\RestaurantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->user()) {
        return redirect()->route('dashboard');
    }

    // A logged-in customer landing on '/' (e.g. the header's logo link)
    // shouldn't see the marketing page they already signed up from —
    // same "home is the restaurant listing" destination as a fresh
    // login (see Auth\AuthenticatedSessionController::store()).
    if (Auth::guard('customer')->check()) {
        return redirect()->route('restaurants.index');
    }

    return view('welcome');
})->name('home');

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/track/{order:tracking_token}', OrderTrackingController::class)->name('track.show');
Route::get('/track/{order:tracking_token}/location', [OrderLocationController::class, 'show'])->name('track.location');

Route::get('/restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');

require __DIR__.'/auth.php';

require __DIR__.'/customer.php';

require __DIR__.'/dashboard.php';
