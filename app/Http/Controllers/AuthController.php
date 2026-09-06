<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const OTP_LIFETIME_MINUTES = 15;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_SEND_LIMIT = 3;
    private const AUTH_MAX_ATTEMPTS = 5;
    private const REGISTRATION_SEND_LIMIT = 3;

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

        $loginKey = $this->authRateLimitKey($request, strtolower($credentials['email']));
        if (RateLimiter::tooManyAttempts($loginKey, self::AUTH_MAX_ATTEMPTS)) {
            return back()->withErrors(['email' => 'Thông tin đăng nhập không đúng hoặc bạn đã thử quá nhiều lần. Vui lòng thử lại sau.'])->withInput();
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($loginKey, self::OTP_LIFETIME_MINUTES * 60);
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->withInput();
        }

        RateLimiter::clear($loginKey);
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
            'phone' => ['required', 'regex:/^(0|\+84)(3|5|7|8|9)[0-9]{8}$/'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $data['email'] = strtolower($data['email']);
        $registrationSendKey = $this->registrationRateLimitKey($request, $data['email']).':send';
        if (RateLimiter::tooManyAttempts($registrationSendKey, self::REGISTRATION_SEND_LIMIT)) {
            return back()->withErrors(['email' => 'Bạn đã yêu cầu quá nhiều mã xác minh. Vui lòng thử lại sau.'])->withInput();
        }
        RateLimiter::hit($registrationSendKey, 60);
        if (User::where('email', $data['email'])->exists()) {
            return redirect()->route('password.request')
                ->withErrors(['email' => 'Email này đã tồn tại. Bạn hãy dùng chức năng quên mật khẩu.']);
        }

        $request->session()->put('registration', [
            'data' => $data,
        ]);

        try {
            $this->sendRegistrationOtp($request);
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget('registration');

            return back()->withErrors(['email' => 'Không thể gửi email xác minh. Bạn hãy kiểm tra lại email hoặc cấu hình SMTP.'])->withInput();
        }

        return redirect()->route('register.verify')->with('status', 'Mã xác minh đã được gửi đến email của bạn.');
    }

    public function resendRegistrationOtp(Request $request)
    {
        $email = (string) $request->session()->get('registration.data.email', '');
        abort_unless($email !== '', 404);
        $registrationSendKey = $this->registrationRateLimitKey($request, $email).':send';
        if (RateLimiter::tooManyAttempts($registrationSendKey, self::REGISTRATION_SEND_LIMIT)) {
            return back()->withErrors(['otp' => 'Bạn đã yêu cầu quá nhiều mã xác minh. Vui lòng thử lại sau.']);
        }
        RateLimiter::hit($registrationSendKey, 60);

        try {
            $this->sendRegistrationOtp($request);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['otp' => 'Không thể gửi lại mã xác minh. Bạn hãy thử lại sau hoặc kiểm tra cấu hình SMTP.']);
        }

        return back()->with('status', 'Mã xác minh mới đã được gửi lại. Mã cũ không còn hiệu lực.');
    }

    public function showRegisterOtp()
    {
        abort_unless(session()->has('registration'), 404);

        return view('auth.verify-register');
    }

    public function verifyRegistration(Request $request)
    {
        $registration = $request->session()->get('registration');
        abort_unless($registration, 404);

        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $registrationKey = $this->registrationRateLimitKey($request, (string) $registration['data']['email']).':verify';
        if (RateLimiter::tooManyAttempts($registrationKey, self::AUTH_MAX_ATTEMPTS)) {
            return back()->withErrors(['otp' => 'Bạn đã nhập sai quá số lần cho phép. Vui lòng gửi lại mã mới.']);
        }
        if (now()->timestamp >= (int) $registration['expires_at']) {
            return back()->withErrors(['otp' => 'Mã xác minh đã hết hạn. Vui lòng bấm gửi lại để nhận mã mới.']);
        }

        if (! hash_equals((string) $registration['otp'], (string) $data['otp'])) {
            RateLimiter::hit($registrationKey, self::OTP_LIFETIME_MINUTES * 60);
            return back()->withErrors(['otp' => 'Mã xác minh không đúng. Vui lòng dùng mã mới nhất trong email.']);
        }

        RateLimiter::clear($registrationKey);
        if (User::where('email', $registration['data']['email'])->exists()) {
            $request->session()->forget('registration');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Email này đã tồn tại. Bạn hãy dùng chức năng quên mật khẩu.']);
        }

        $user = User::create($registration['data'] + ['role' => 'user']);
        $user->markEmailAsVerified();
        $request->session()->forget('registration');
        Auth::login($user);
        $request->session()->regenerate();
        $this->syncCartToUser($request, $user);

        return redirect()->route('home')->with('status', 'Đăng ký tài khoản thành công.');
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $this->syncCartToUser($request, $request->user());
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function syncCartToUser(Request $request, User $user): void
    {
        foreach ($request->session()->get('cart', []) as $variantId => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity < 1) {
                continue;
            }

            $item = $user->cartItems()->firstOrNew(['product_variant_id' => $variantId]);
            $item->quantity = $quantity;
            $item->save();
        }

        $request->session()->put('cart', $user->cartItems()->latest('updated_at')->pluck('quantity', 'product_variant_id')->all());
    }

    private function sendRegistrationOtp(Request $request): void
    {
        $registration = $request->session()->get('registration');
        abort_unless(isset($registration['data']['email']), 404);

        $otp = (string) random_int(100000, 999999);
        $registration['otp'] = $otp;
        $registration['expires_at'] = now()->addMinutes(self::OTP_LIFETIME_MINUTES)->timestamp;
        $request->session()->put('registration', $registration);

        Mail::raw("Mã xác minh đăng ký tài khoản của bạn là: {$otp}. Mã có hiệu lực trong ".self::OTP_LIFETIME_MINUTES.' phút.', function ($message) use ($registration) {
            $message->to($registration['data']['email'])->subject('Xác minh đăng ký tài khoản');
        });
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);
        $sendKey = $this->passwordResetRateLimitKey($request, $email).':send';
        if (RateLimiter::tooManyAttempts($sendKey, self::OTP_SEND_LIMIT)) {
            return redirect()->route('password.otp')
                ->with('email', $email)
                ->with('status', 'Nếu email hợp lệ, mã xác minh sẽ được gửi đến địa chỉ đó.');
        }
        RateLimiter::hit($sendKey, 60);

        $user = User::where('email', $email)->first();
        if (! $user) {
            return redirect()->route('password.otp')
                ->with('email', $email)
                ->with('status', 'Nếu email hợp lệ, mã xác minh sẽ được gửi đến địa chỉ đó.');
        }

        $otp = (string) random_int(100000, 999999);
        $user->forceFill([
            'reset_otp' => $otp,
            'reset_otp_expires_at' => now()->addMinutes(self::OTP_LIFETIME_MINUTES),
        ])->save();

        try {
            Mail::raw("Mã xác minh đặt lại mật khẩu của bạn là: {$otp}. Mã có hiệu lực trong ".self::OTP_LIFETIME_MINUTES.' phút.', function ($message) use ($user) {
                $message->to($user->email)->subject('Mã xác minh đặt lại mật khẩu');
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('password.otp')
                ->with('email', $email)
                ->with('status', 'Nếu email hợp lệ, mã xác minh sẽ được gửi đến địa chỉ đó.');
        }

        return redirect()->route('password.otp')
            ->with('email', $user->email)
            ->with('status', 'Nếu email hợp lệ, mã xác minh sẽ được gửi đến địa chỉ đó.');
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
        $email = strtolower($data['email']);
        $attemptKey = $this->passwordResetRateLimitKey($request, $email).':verify';
        if (RateLimiter::tooManyAttempts($attemptKey, self::OTP_MAX_ATTEMPTS)) {
            return back()->withErrors(['otp' => 'Bạn đã nhập sai quá số lần cho phép. Vui lòng gửi lại mã mới sau ít phút.'])->withInput();
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->reset_otp_expires_at || $user->reset_otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'Mã xác minh không hợp lệ hoặc đã hết hạn. Vui lòng gửi lại mã mới.'])->withInput();
        }

        if (! hash_equals((string) $user->reset_otp, (string) $data['otp'])) {
            RateLimiter::hit($attemptKey, self::OTP_LIFETIME_MINUTES * 60);
            return back()->withErrors(['otp' => 'Mã xác minh không hợp lệ hoặc đã hết hạn. Vui lòng gửi lại mã mới.'])->withInput();
        }

        RateLimiter::clear($attemptKey);
        $request->session()->put('password_reset', [
            'email' => $user->email,
            'token' => Str::random(40),
            'expires_at' => now()->addMinutes(self::OTP_LIFETIME_MINUTES)->timestamp,
        ]);
        $user->forceFill(['reset_otp' => null, 'reset_otp_expires_at' => null])->save();

        return redirect()->route('password.reset');
    }

    public function showResetPassword()
    {
        return view('auth.reset-password');
    }

    public function resetPassword(Request $request)
    {
        $reset = $request->session()->get('password_reset');
        abort_unless(
            is_array($reset)
                && isset($reset['email'], $reset['token'], $reset['expires_at'])
                && (int) $reset['expires_at'] >= now()->timestamp,
            422,
            'Phiên xác minh đã hết hạn.'
        );

        $data = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string', 'size:40'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        abort_unless(hash_equals((string) $reset['email'], strtolower($data['email'])), 422, 'Phiên xác minh không hợp lệ.');
        abort_unless(hash_equals((string) $reset['token'], $data['reset_token']), 422, 'Phiên xác minh không hợp lệ.');
        $user = User::where('email', $reset['email'])->firstOrFail();
        $user->update(['password' => $data['password']]);
        $request->session()->forget('password_reset');

        return redirect()->route('login')->with('status', 'Đổi mật khẩu thành công.');
    }

    private function passwordResetRateLimitKey(Request $request, string $email): string
    {
        return 'password-reset:'.sha1($email.'|'.$request->ip());
    }

    private function authRateLimitKey(Request $request, string $email): string
    {
        return 'auth:'.sha1($email.'|'.$request->ip());
    }

    private function registrationRateLimitKey(Request $request, string $email): string
    {
        return 'registration:'.sha1($email.'|'.$request->ip());
    }
}
