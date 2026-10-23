<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>NOVA Z | Learning Portal</title><meta name="theme-color" content="#1d4ed8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/novax.css') }}?v=20260922" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/novax-favicon.png') }}">
</head>
<body>
@auth
@php
    $navItems = [['dashboard', 'Dashboard', 'layout-dashboard']];
    if(in_array(auth()->user()->role, ['super_admin','teacher'])) $navItems = array_merge($navItems, [['students', 'Students', 'users'], ['performance', 'Performance', 'chart-column']]);
    if(auth()->user()->role === 'super_admin') $navItems = array_merge($navItems, [['teachers','Teachers','graduation-cap'],['courses','Courses','book-open'],['subjects','Subjects','library']]);
    $navItems = array_merge($navItems, [['exams','Exams','clipboard-list'],['attendance','Attendance','calendar-check'],['materials','Materials','folder-open'],['materials?tab=previous','Previous Papers','archive']]);
    $pageLabel = ucfirst(request()->segment(1) ?: 'Dashboard');
@endphp
<div class="app-shell">
    <button class="nav-overlay" aria-label="Close navigation" onclick="toggleNavigation(false)"></button>
    <aside class="sidebar" id="portalNavigation">
        <a class="brand-lockup" href="{{ route('dashboard') }}"><img src="{{ asset('images/novax-logo.png') }}" alt="NOVA Z logo"><strong>NOVA Z<span>Learn. Practice. Progress.</span></strong></a>
        <div class="nav-caption">{{ auth()->user()->role === 'student' ? 'My learning' : 'Workspace' }}</div>
        <nav aria-label="Main navigation">
            @foreach($navItems as [$path,$label,$icon])
                <a class="{{ request()->is($path, $path.'/*') ? 'active' : '' }}" @if(request()->is($path, $path.'/*')) aria-current="page" @endif href="{{ url($path) }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-note"><strong>Your next chapter starts here.</strong>A little progress, every day.</div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="signout"><i data-lucide="log-out" aria-hidden="true"></i>Sign out</button></form>
        </div>
    </aside>
    <div class="workspace">
        <header class="topbar">
            <div class="breadcrumbs"><button class="icon-button menu-button" aria-label="Open navigation" aria-controls="portalNavigation" aria-expanded="false" onclick="toggleNavigation()"><i data-lucide="menu"></i></button><span>My workspace</span><span>/</span><strong>{{ $pageLabel }}</strong></div>
            <div class="topbar-actions">
                <a class="topbar-home" href="{{ route('home') }}">Institute website <i data-lucide="arrow-up-right"></i></a>
                <a class="icon-button" href="{{ auth()->user()->role === 'student' ? route('dashboard').'#exam-notifications' : route('exams.index') }}" title="Exam updates" aria-label="Exam updates"><i data-lucide="bell"></i></a>
                <div class="account-chip"><div class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_',' ',auth()->user()->role) }}</small></div></div>
            </div>
        </header>
        <main class="content">@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif @yield('content')</main>
    </div>
</div>
@else
    @yield('content')
@endauth
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/lucide.min.js') }}"></script>
<script>
window.lucide?.createIcons();
function toggleNavigation(open) {
    const expanded = open ?? !document.body.classList.contains('nav-open');
    document.body.classList.toggle('nav-open', expanded);
    document.querySelector('.menu-button')?.setAttribute('aria-expanded', String(expanded));
}
document.addEventListener('keydown', event => { if(event.key === 'Escape') toggleNavigation(false); });
document.querySelectorAll('.sidebar nav a').forEach(link => link.addEventListener('click', () => toggleNavigation(false)));
document.addEventListener('click', function(event) {
    const opener = event.target.closest('[data-bs-toggle="modal"][data-bs-target]');
    if(opener && !window.bootstrap) {
        event.preventDefault();
        const modal = document.querySelector(opener.dataset.bsTarget);
        if(!modal) return;
        modal.classList.add('show'); modal.style.display = 'block';
        modal.removeAttribute('aria-hidden'); modal.setAttribute('aria-modal','true');
        document.body.classList.add('modal-open');
        const backdrop = document.createElement('div'); backdrop.className = 'modal-backdrop fade show';
        backdrop.dataset.fallbackBackdrop = 'true'; document.body.appendChild(backdrop);
    }
    if(event.target.closest('[data-bs-dismiss="modal"], [data-fallback-backdrop]') && !window.bootstrap) {
        document.querySelectorAll('.modal.show').forEach(modal => { modal.classList.remove('show'); modal.style.display='none'; modal.setAttribute('aria-hidden','true'); });
        document.querySelectorAll('[data-fallback-backdrop]').forEach(node => node.remove());
        document.body.classList.remove('modal-open');
    }
});
</script>
</body>
</html>
