<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\GHNController;
use App\Http\Controllers\MomoPaymentController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');

Route::get('/cua-hang', [StorefrontController::class, 'home'])->name('shop.home');
Route::get('/goi-y-san-pham', [StorefrontController::class, 'productSuggestions'])->name('shop.products.suggestions');
Route::get('/cua-hang/san-pham/{product}', [StorefrontController::class, 'show'])->name('shop.products.show');
Route::get('/cua-hang/san-pham/{product}/bien-the/{variant}', [StorefrontController::class, 'showVariant'])->name('shop.products.variant');
Route::get('/gio-hang', [StorefrontController::class, 'cart'])->name('cart.index');
Route::post('/gio-hang/them/{product}', [StorefrontController::class, 'addToCart'])->name('cart.add');
Route::patch('/gio-hang/{variant}', [StorefrontController::class, 'updateCart'])->name('cart.update');
Route::post('/gio-hang/{variant}/doi-bien-the', [StorefrontController::class, 'replaceVariant'])->name('cart.replace-variant');
Route::delete('/gio-hang/{variant}', [StorefrontController::class, 'removeFromCart'])->name('cart.remove');
Route::middleware('auth')->group(function () {
    Route::get('/thanh-toan', [StorefrontController::class, 'checkout'])->name('checkout');
    Route::post('/thanh-toan', [StorefrontController::class, 'placeOrder'])->name('checkout.place');
});
Route::get('/thanh-toan/momo/result', [MomoPaymentController::class, 'result'])->name('momo.result');
Route::post('/thanh-toan/momo/ipn', [MomoPaymentController::class, 'ipn'])
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('momo.ipn');
Route::prefix('ghn')->middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/provinces', [GHNController::class, 'provinces'])->name('ghn.provinces');
    Route::get('/districts', [GHNController::class, 'districts'])->name('ghn.districts');
    Route::get('/wards', [GHNController::class, 'wards'])->name('ghn.wards');
    Route::post('/fee', [GHNController::class, 'fee'])->name('ghn.fee');
});

Route::prefix('buyer')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.store');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->name('register.store');
        Route::get('/register/verify', [AuthController::class, 'showRegisterOtp'])->name('register.verify');
        Route::post('/register/verify', [AuthController::class, 'verifyRegistration'])->name('register.verify.submit');
        Route::post('/register/verify/resend', [AuthController::class, 'resendRegistrationOtp'])
            ->middleware('throttle:3,1')
            ->name('register.verify.resend');
        Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('password.email');
        Route::get('/verify-otp', [AuthController::class, 'showOtp'])->name('password.otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('password.verify');
        Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    });
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('buyer.logout');
});

Route::prefix('user')->middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('user.profile.show');
    Route::put('/profile', [ProfileController::class, 'save'])->name('user.profile.update');
    Route::get('/addresses', [AddressController::class, 'index'])->name('user.addresses.index');
    Route::get('/addresses/search', [AddressController::class, 'search'])->middleware('throttle:30,1')->name('user.addresses.search');
    Route::post('/addresses', [AddressController::class, 'store'])->name('user.addresses.store');
    Route::patch('/addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('user.addresses.default');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('user.addresses.destroy');
    Route::get('/don-mua', [OrderController::class, 'index'])->name('user.orders.index');
    Route::get('/don-mua/{order}', [OrderController::class, 'show'])->name('user.orders.show');
    Route::post('/don-mua/{order}/mua-lai', [OrderController::class, 'reorder'])->name('user.orders.reorder');
    Route::patch('/don-mua/{order}/huy', [OrderController::class, 'cancel'])->name('user.orders.cancel');
    Route::post('/don-mua/{order}/momo/lai', [MomoPaymentController::class, 'start'])->name('user.orders.momo.retry');
});

Route::prefix('admin')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::get('/', fn () => view('admin.dashboard'))->middleware('admin')->name('admin.dashboard');
        Route::get('/profile', [ProfileController::class, 'show'])->name('admin.profile.show');
        Route::put('/profile', [ProfileController::class, 'save'])->name('admin.profile.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
        Route::middleware('admin')->group(function () {
            Route::get('/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
            Route::patch('/orders/confirm-all', [AdminOrderController::class, 'confirmAll'])->name('admin.orders.confirm-all');
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('admin.orders.status');
            Route::patch('/orders/{order}/payment', [AdminOrderController::class, 'updatePayment'])->name('admin.orders.payment');
            Route::patch('/orders/{order}/cancellation-settlement', [AdminOrderController::class, 'settleCancellation'])->name('admin.orders.cancellation-settlement');
            Route::delete('/orders', [AdminOrderController::class, 'destroyAll'])->name('admin.orders.destroy-all');
            Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('admin.orders.destroy');
            Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports.index');
            Route::get('/reports/export', [ReportController::class, 'export'])->name('admin.reports.export');
            Route::resource('categories', CategoryController::class);
            Route::resource('products', ProductController::class);
        });
    });
});
