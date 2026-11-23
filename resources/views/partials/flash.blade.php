@if (session('status') || session('success') || session('error') || $errors->any())
    <div class="flash-wrap section-shell" role="status" aria-live="polite">
        @if (session('status'))
            <div class="flash flash-success">{{ session('status') }}</div>
        @endif
        @if (session('success'))
            <div class="flash flash-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="flash flash-error" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any() && ! request()->routeIs('login', 'register', 'password.*', 'verification.*', 'staff.*'))
            <div class="flash flash-error" role="alert">{{ $errors->first() }}</div>
        @endif
    </div>
@endif
