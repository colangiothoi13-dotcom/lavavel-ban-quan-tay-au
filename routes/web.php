<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorefrontController;

Route::get('/', [StorefrontController::class, 'home'])->name('home');

Route::get('/cua-hang', [StorefrontController::class, 'home'])->name('shop.home');
Route::get('/cua-hang/san-pham/{product}', [StorefrontController::class, 'show'])->name('shop.products.show');
Route::get('/cua-hang/san-pham/{product}/bien-the/{variant}', [StorefrontController::class, 'showVariant'])->name('shop.products.variant');
Route::get('/gio-hang', [StorefrontController::class, 'cart'])->name('cart.index');
Route::post('/gio-hang/them/{product}', [StorefrontController::class, 'addToCart'])->name('cart.add');
Route::patch('/gio-hang/{variant}', [StorefrontController::class, 'updateCart'])->name('cart.update');
Route::delete('/gio-hang/{variant}', [StorefrontController::class, 'removeFromCart'])->name('cart.remove');
Route::get('/thanh-toan', [StorefrontController::class, 'checkout'])->name('checkout');
Route::post('/thanh-toan', [StorefrontController::class, 'placeOrder'])->name('checkout.place');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.store');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->name('register.store');
        Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('password.email');
        Route::get('/verify-otp', [AuthController::class, 'showOtp'])->name('password.otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('password.verify');
        Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))->middleware('admin')->name('admin.dashboard');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ProfileController::class, 'save'])->name('profile.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::middleware('admin')->group(function () {
            Route::resource('categories', CategoryController::class);
            Route::resource('products', ProductController::class);
        });
    });
});
