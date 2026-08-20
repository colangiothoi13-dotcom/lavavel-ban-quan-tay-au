<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->withInput();
        }

        $request->session()->regenerate();

        return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'home')
            ->with('status', 'Đăng nhập thành công.');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:Nam,Nữ,Khác'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create($data + ['role' => 'user']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('status', 'Đăng ký tài khoản thành công.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'exists:users,email']]);
        $user = User::where('email', $data['email'])->firstOrFail();
        $otp = (string) random_int(100000, 999999);
        $user->forceFill(['reset_otp' => $otp, 'reset_otp_expires_at' => now()->addMinutes(10)])->save();

        try {
            Mail::raw("Mã xác minh đặt lại mật khẩu của bạn là: {$otp}. Mã có hiệu lực trong 10 phút.", function ($message) use ($user) {
                $message->to($user->email)->subject('Mã xác minh đặt lại mật khẩu');
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['email' => 'Không thể gửi email. Bạn hãy kiểm tra cấu hình SMTP trong file .env.'])->withInput();
        }

        return redirect()->route('password.otp')->with('email', $user->email)->with('status', 'Mã xác minh đã được gửi đến email của bạn.');
    }

    public function showOtp()
    {
        return view('auth.verify-otp');
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);
        $user = User::where('email', $data['email'])->first();

        if (! $user || $user->reset_otp !== $data['otp'] || ! $user->reset_otp_expires_at || $user->reset_otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'Mã xác minh không đúng hoặc đã hết hạn.'])->withInput();
        }

        return redirect()->route('password.reset')->with('reset_token', Str::random(40))->with('email', $user->email);
    }

    public function showResetPassword()
    {
        return view('auth.reset-password');
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        $user = User::where('email', $data['email'])->firstOrFail();
        abort_unless($user->reset_otp && $user->reset_otp_expires_at?->isFuture(), 422, 'Phiên xác minh đã hết hạn.');
        $user->update(['password' => $data['password'], 'reset_otp' => null, 'reset_otp_expires_at' => null]);

        return redirect()->route('login')->with('status', 'Đổi mật khẩu thành công.');
    }
}