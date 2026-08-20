<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Tài khoản' }} - {{ config('app.name') }}</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;padding:32px;color:#1f2937}.box{max-width:520px;margin:30px auto;background:#fff;padding:28px;border-radius:8px;box-shadow:0 4px 18px #0001}h1{margin-top:0}label{display:block;margin:14px 0 6px;font-weight:600}input,select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:4px;box-sizing:border-box}button{margin-top:20px;padding:11px 18px;background:#111827;color:#fff;border:0;border-radius:4px;cursor:pointer}.links{margin-top:18px;display:flex;gap:14px;flex-wrap:wrap}.error{color:#b91c1c;margin:8px 0}.status{color:#047857;margin:8px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:560px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body><main class="box">@yield('content')</main></body>
</html>
