<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NOVA Z Institute | Central Government & Bank Exam Preparation</title>
    <meta name="description" content="NOVA Z prepares you for central government competitive exams and bank exams: SSC, RRB, IBPS, SBI and RBI. Study subject notes, take timed practice tests, and follow your weekly progress.">
    <meta name="theme-color" content="#1d4ed8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/novax.css') }}?v=20260922" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/novax-favicon.png') }}">
</head>
<body>
<header class="public-nav">
    <a class="brand-lockup" href="{{ route('home') }}"><img src="{{ asset('images/novax-logo.png') }}" alt="NOVA Z logo"><strong>NOVA Z<span>Exam preparation institute</span></strong></a>
    <nav class="public-links" aria-label="Public navigation"><a href="#programs">Our programs</a><a href="#exams">Exams we cover</a><a href="#learning">The learning journey</a><div><a class="btn btn-primary text-white" href="{{ auth()->check() ? route('dashboard') : route('login') }}">{{ auth()->check() ? 'My dashboard' : 'Student login' }} <i data-lucide="arrow-up-right"></i></a></div></nav>
</header>
<main>
    <section class="public-hero">
        <div class="hero-inner"><span class="eyebrow">Central government &amp; bank exam preparation</span><h1>NOVA Z<br>Institute.</h1><div class="hero-phrase">Small steps today.<br>A government career tomorrow.</div><p>Exam preparation for central government competitive exams and bank recruitment. Build your understanding. Practice with purpose. See how far you have come.</p>
            <div class="hero-actions"><a href="#programs" class="btn btn-primary">Explore programs <i data-lucide="arrow-right"></i></a><a href="{{ route('login') }}" class="btn btn-outline-secondary">Open learning portal</a></div>
        </div>
    </section>
    <div class="public-band"><div class="public-benefits">
        <div><i data-lucide="book-open"></i>Subject-wise learning</div><div><i data-lucide="timer"></i>Timed mock exams</div><div><i data-lucide="chart-no-axes-combined"></i>Weekly progress</div><div><i data-lucide="smartphone"></i>Website & Android app</div>
    </div></div>
    <section class="public-section" id="programs">
        <span class="eyebrow">Find your focus</span><h2>Three paths. One goal: your selection.</h2><p class="intro">Focused preparation for central government competitive exams and bank exams, with subject notes, regular practice, and a clear view of your progress.</p>
        <div class="program-grid">
            <article class="public-program"><div class="course-cover"><i data-lucide="landmark"></i><b>SSC</b></div><div class="course-body"><span class="eyebrow">Staff Selection Commission</span><h3>SSC exam preparation</h3><p>Reasoning, quantitative aptitude, English, and general awareness for SSC CGL and other SSC exams. Practice Tier 1 and Tier 2 Paper 1 with structured, timed mocks.</p><a class="btn btn-outline-primary" href="{{ route('login') }}">Explore SSC learning <i data-lucide="arrow-right"></i></a></div></article>
            <article class="public-program"><div class="course-cover yellow"><i data-lucide="train-front"></i><b>RRB</b></div><div class="course-body"><span class="eyebrow">Railway Recruitment Board</span><h3>RRB exam preparation</h3><p>Build confidence in mathematics, general awareness, and reasoning for RRB NTPC, with CBT 1 and CBT 2 practice and detailed score breakdowns.</p><a class="btn btn-outline-primary" href="{{ route('login') }}">Explore RRB learning <i data-lucide="arrow-right"></i></a></div></article>
            <article class="public-program"><div class="course-cover violet"><i data-lucide="piggy-bank"></i><b>BANK</b></div><div class="course-body"><span class="eyebrow">IBPS &middot; SBI &middot; RBI</span><h3>Bank exam preparation</h3><p>Quantitative aptitude, reasoning ability, English language, and banking awareness for PO and Clerk exams. Learn topic by topic and practise against the clock.</p><a class="btn btn-outline-primary" href="{{ route('login') }}">Explore bank learning <i data-lucide="arrow-right"></i></a></div></article>
        </div>
    </section>
    <div class="public-band"><section class="public-section" id="exams"><span class="eyebrow">Exams we cover</span><h2>Central government competitive exams and bank exams.</h2><p class="intro">One institute for the exams that open the door to a central government or banking career.</p>
        <div class="exam-groups">
            <article><h3><i data-lucide="landmark"></i>Central government</h3><ul class="exam-chips"><li>SSC CGL</li><li>SSC CHSL</li><li>SSC MTS</li><li>RRB NTPC</li><li>RRB Group D</li></ul></article>
            <article><h3><i data-lucide="piggy-bank"></i>Banking</h3><ul class="exam-chips"><li>IBPS PO</li><li>IBPS Clerk</li><li>SBI PO</li><li>SBI Clerk</li><li>RBI Assistant</li></ul></article>
        </div>
    </section></div>
    <section class="public-section" id="learning"><span class="eyebrow">A rhythm that works for you</span><h2>Learn. Put it into practice. Grow.</h2>
        <div class="learning-steps"><article><b>01 / LEARN</b><h3>Build your foundation</h3><p>Keep your course subjects and downloadable notes together. Return to the topics that need another look.</p></article><article><b>02 / PRACTICE</b><h3>Make every test count</h3><p>Take scheduled mocks with timed sections and negative marking. Review the answers after you submit.</p></article><article><b>03 / PROGRESS</b><h3>Understand your growth</h3><p>Follow weekly accuracy and section results. Use each result to decide where to focus next.</p></article></div>
    </section>
    <div class="public-band"><section class="public-section"><span class="eyebrow">Your next chapter</span><h2>Ready for your next study session?</h2><p class="intro">Sign in to continue your courses and see your upcoming practice tests.</p><a href="{{ route('login') }}" class="btn btn-primary mt-3">Go to learning portal <i data-lucide="arrow-up-right"></i></a></section></div>
</main>
<footer class="public-footer"><strong>NOVA Z Institute</strong><span>Central government competitive exams &amp; bank exam preparation</span><span>&copy; {{ now()->year }} NOVA Z</span></footer>
<script src="{{ asset('js/lucide.min.js') }}"></script><script>window.lucide?.createIcons();</script>
</body>
</html>
