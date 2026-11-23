@extends('layouts.app')

@section('title', 'Reset password')

@section('content')
    <section class="auth-page auth-page-forgot section-shell">
        <div class="auth-story">
            <span class="eyebrow">A FRESH START</span>
            <h1>Choose a new password.</h1>
            <p>Use at least 8 characters with letters and numbers.</p>
        </div>
        <x-auth-card mode="reset" :token="$token" :email="$email" />
    </section>
@endsection
