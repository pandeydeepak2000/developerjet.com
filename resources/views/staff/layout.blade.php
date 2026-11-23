<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Staff sign in') · DeveloperJet Staff</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/css/refine.css', 'resources/css/site.css', 'resources/js/app.js'])
</head>
<body class="admin-body admin-login-body">
    <main class="admin-login-card">
        <img src="{{ asset('images/logo.png') }}" alt="DeveloperJet" width="160">
        <h1>@yield('heading')</h1>
        @if (session('status'))<div class="flash flash-success" role="status">{{ session('status') }}</div>@endif
        @yield('content')
    </main>
</body>
</html>
