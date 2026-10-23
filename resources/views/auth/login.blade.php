@extends('layouts.app')
@section('content')
<div class="login-page">
    <section class="login-scene" aria-label="Study desk"><h2>A little practice.<br>A bigger future.</h2></section>
    <div class="login-form-wrap">
        <a href="{{ route('home') }}" class="brand-lockup"><img src="{{ asset('images/novax-logo.png') }}" alt="NOVA Z logo"><strong>NOVA Z<span>Your exam preparation companion</span></strong></a>
        <h1>Welcome back.</h1><p>Central government and bank exam preparation.<br>Your courses, practice tests, and progress in one place.</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <div class="mb-3"><label class="form-label" for="email">Email address</label><input id="email" class="form-control" type="email" name="email" autocomplete="username" value="{{ old('email', 'admin@nonax.test') }}" required></div>
            <div class="mb-3"><label class="form-label" for="password">Password</label><input id="password" class="form-control" type="password" name="password" autocomplete="current-password" value="password" required></div>
            <label class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember"><span>Keep me signed in</span></label>
            <button class="btn btn-primary w-100">Sign in <i data-lucide="arrow-right"></i></button>
        </form>
        <p class="demo-note">Demo accounts: admin@nonax.test, teacher@nonax.test, student@nonax.test.<br>Password: password.</p>
        <a href="{{ route('home') }}" class="small text-decoration-none"><i data-lucide="arrow-left"></i> Back to NOVA Z</a>
    </div>
</div>
@endsection
