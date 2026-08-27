<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Tài khoản' }} - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #fff; color: #17233b; font-family: Arial, sans-serif; }
        .auth-shell { display: grid; grid-template-columns: minmax(0, 1fr) minmax(460px, 1fr); min-height: 100vh; }
        .auth-visual { position: sticky; top: 0; min-height: 100vh; height: 100vh; background: #26334a center / cover no-repeat; }
        .auth-visual::after { position: absolute; inset: 0; content: ''; background: linear-gradient(180deg, transparent 58%, rgb(9 20 38 / 32%)); }
        .auth-panel { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 48px clamp(32px, 7vw, 110px); background: #fff; }
        .auth-card { width: min(100%, 500px); }
        .auth-brand { margin-bottom: 36px; text-align: center; }
        .auth-monogram { display: inline-grid; width: 72px; height: 72px; margin-bottom: 14px; place-items: center; border: 2px solid #c3a064; border-radius: 50%; color: #172d4f; font-family: Georgia, serif; font-size: 30px; font-weight: 700; letter-spacing: -3px; }
        .auth-brand-name { margin: 0; color: #172d4f; font-family: Georgia, serif; font-size: 19px; letter-spacing: 4px; text-transform: uppercase; }
        h1 { margin: 0 0 14px; color: #151515; font-size: clamp(30px, 3vw, 42px); text-align: center; }
        .auth-intro { margin: 0 0 28px; color: #6b7280; line-height: 1.6; text-align: center; }
        label { display: block; margin: 17px 0 8px; color: #283245; font-size: 15px; font-weight: 600; }
        input, select { width: 100%; height: 50px; padding: 12px 15px; border: 1px solid #d7dce3; border-radius: 7px; background: #fff; font: inherit; outline: none; transition: border-color .2s ease, box-shadow .2s ease; }
        input:focus, select:focus { border-color: #19375e; box-shadow: 0 0 0 3px rgb(25 55 94 / 12%); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .password-field { position: relative; }
        .password-field input { padding-right: 52px; }
        .password-toggle { position: absolute; top: 50%; right: 8px; display: grid; width: 38px; height: 38px; margin: 0; padding: 0; place-items: center; border: 0; border-radius: 50%; background: transparent; color: #687386; opacity: 0; pointer-events: none; transform: translateY(-50%); transition: color .2s ease, background .2s ease, opacity .2s ease; }
        .password-field.has-value .password-toggle { opacity: 1; pointer-events: auto; }
        .password-toggle:hover { background: #edf1f6; color: #19375e; }
        .password-toggle svg { width: 21px; height: 21px; }
        .auth-options { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-top: 18px; font-size: 14px; }
        .remember-label { display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 500; cursor: pointer; }
        .remember-label input { width: 17px; height: 17px; margin: 0; accent-color: #19375e; }
        button[type='submit'] { width: 100%; height: 52px; margin-top: 28px; padding: 0 18px; border: 0; border-radius: 7px; background: #172f52; color: #fff; font-size: 15px; font-weight: 700; letter-spacing: .7px; text-transform: uppercase; cursor: pointer; transition: background .2s ease, transform .2s ease; }
        button[type='submit']:hover { background: #0f213d; }
        button[type='submit']:active { transform: translateY(1px); }
        .links { display: flex; justify-content: center; gap: 8px 16px; margin-top: 30px; flex-wrap: wrap; text-align: center; }
        a { color: #19375e; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
        button[type='submit'].link-button { width: auto; height: auto; margin: 0; padding: 0; border: 0; background: transparent; color: #19375e; font: inherit; font-weight: 600; text-transform: none; letter-spacing: normal; cursor: pointer; }
        button[type='submit'].link-button:hover { background: transparent; text-decoration: underline; }
        .error, .status { margin: 15px 0; padding: 11px 13px; border-radius: 6px; }
        .error { background: #fef2f2; color: #b91c1c; }
        .status { background: #ecfdf5; color: #047857; }
        @media (max-width: 900px) { .auth-shell { grid-template-columns: 38% 62%; } .auth-panel { padding: 42px 32px; } }
        @media (max-width: 680px) { .auth-shell { display: block; } .auth-visual { display: none; } .auth-panel { min-height: 100vh; padding: 32px 22px; } .auth-brand { margin-bottom: 28px; } .form-grid { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
    <main class="auth-shell">
        <aside class="auth-visual" data-auth-visual aria-label="Ảnh giới thiệu" style="background-image: url('{{ asset('images/login/slide-1.jpg') }}?v={{ filemtime(public_path('images/login/slide-1.jpg')) }}')"></aside>
        <section class="auth-panel">
            <div class="auth-card">
                @if($showBrand ?? false)
                    <header class="auth-brand" data-auth-brand>
                        <div class="auth-monogram" aria-hidden="true">MT</div>
                        <p class="auth-brand-name">Minh Trí Tailor</p>
                    </header>
                @endif
                @yield('content')
            </div>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-password-field]').forEach((field) => {
            const input = field.querySelector('input');
            const toggle = field.querySelector('[data-password-toggle]');
            if (!input || !toggle) return;
            const syncToggle = () => field.classList.toggle('has-value', input.value.length > 0);
            input.addEventListener('input', syncToggle);
            toggle.addEventListener('click', () => {
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                toggle.setAttribute('aria-pressed', String(!isVisible));
                toggle.setAttribute('aria-label', isVisible ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
                input.focus();
            });
            syncToggle();
        });
    </script>
</body>
</html>
