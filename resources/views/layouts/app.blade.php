<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f4ef">
    <meta name="description" content="@yield('meta_description', 'DeveloperJet makes powerful developer tools feel effortless. Discover managed Hermes and n8n plans, backed by people who are here to help.')">
    <title>@yield('title', 'DeveloperJet') · DeveloperJet</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f4ef] text-slate-900 antialiased">
    <div class="announcement-bar">
        <span class="announcement-dot"></span>
        More flow. Less setup. Meet your new developer toolkit.
        <a href="{{ route('hermes.cli') }}#pricing">Explore plans <span aria-hidden="true">→</span></a>
    </div>

    <header class="site-header">
        <nav class="nav-shell" aria-label="Main navigation">
            <a class="brand" href="{{ route('home') }}" aria-label="DeveloperJet home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M17.3 3.5c4.4 3.4 7.3 8.2 8.6 14.2l-7.5 7.5c-6-1.3-10.8-4.2-14.2-8.6C8.5 8.7 13.4 5.2 17.3 3.5Z" fill="currentColor"/>
                        <path d="m9.3 19.5-4 7.2 7.2-4" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="19.3" cy="12.7" r="2.4" fill="#fff"/>
                    </svg>
                </span>
                <span class="brand-word">developer<span>jet</span></span>
            </a>

            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" data-menu-toggle>
                <span class="sr-only">Toggle navigation</span>
                <span></span><span></span>
            </button>

            <div class="nav-content" id="primary-menu" data-menu>
                <div class="nav-links">
                    <div class="nav-dropdown" data-hermes-dropdown>
                        <button class="nav-dropdown-trigger" type="button" aria-expanded="false" aria-controls="hermes-menu" data-hermes-dropdown-toggle @if (request()->routeIs('hermes*')) aria-current="page" @endif>
                            Hermes <span aria-hidden="true">⌄</span>
                        </button>
                        <div class="nav-dropdown-menu" id="hermes-menu" data-hermes-dropdown-menu>
                            <a href="{{ route('hermes') }}" @if (request()->routeIs('hermes') && !request()->routeIs('hermes.*')) aria-current="page" @endif>Hermes overview</a>
                            <a href="{{ route('hermes.cli') }}" @if (request()->routeIs('hermes.cli')) aria-current="page" @endif>Hermes CLI</a>
                            <a href="{{ route('hermes.workspace') }}" @if (request()->routeIs('hermes.workspace')) aria-current="page" @endif>Hermes Workspace</a>
                        </div>
                    </div>
                    <a href="{{ route('n8n') }}" @if (request()->routeIs('n8n')) aria-current="page" @endif>n8n</a>
                    <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
                    <a href="{{ route('contact') }}" @if (request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
                    <a href="{{ route('login') }}" @if (request()->routeIs('login')) aria-current="page" @endif>Login</a>
                </div>
                <a class="nav-cta" href="{{ route('register') }}">
                    Get started
                    <span aria-hidden="true">↗</span>
                </a>
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-shell">
            <div class="footer-cta">
                <div>
                    <span class="footer-cta-kicker">BUILD SOMETHING GOOD</span>
                    <h2>Find the right tools<br>for your next big idea.</h2>
                </div>
                <a class="footer-cta-button" href="{{ route('contact') }}">Talk to our team <span aria-hidden="true">↗</span></a>
            </div>
            <div class="footer-main">
                <div class="footer-brand-block">
                    <a class="brand brand-footer" href="{{ route('home') }}" aria-label="DeveloperJet home">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M17.3 3.5c4.4 3.4 7.3 8.2 8.6 14.2l-7.5 7.5c-6-1.3-10.8-4.2-14.2-8.6C8.5 8.7 13.4 5.2 17.3 3.5Z" fill="currentColor"/>
                                <path d="m9.3 19.5-4 7.2 7.2-4" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="19.3" cy="12.7" r="2.4" fill="#fff"/>
                            </svg>
                        </span>
                        <span class="brand-word">developer<span>jet</span></span>
                    </a>
                    <p>Managed developer tools, VPS plans for Hermes and n8n, and helpful people—so your team can spend more time building.</p>
                </div>
                <div class="footer-column">
                    <h2>OUR PRODUCTS</h2>
                    <a href="{{ route('hermes') }}">Hermes overview</a>
                    <a href="{{ route('hermes.cli') }}">Hermes CLI plans</a>
                    <a href="{{ route('hermes.workspace') }}">Hermes Workspace plans</a>
                    <a href="{{ route('n8n') }}">n8n Managed</a>
                </div>
                <div class="footer-column">
                    <h2>COMPANY</h2>
                    <a href="{{ route('about') }}">About us</a>
                    <a href="{{ route('contact') }}">Contact</a>
                    <a href="{{ route('contact') }}#support">Talk to sales</a>
                    <a href="{{ route('login') }}">Login</a>
                    <a href="{{ route('register') }}">Create account</a>
                </div>
                <div class="footer-column">
                    <h2>HELP & POLICIES</h2>
                    <a href="{{ route('terms') }}">Terms</a>
                    <a href="{{ route('privacy') }}">Privacy</a>
                    <a href="{{ route('refund') }}">Refund policy</a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© {{ date('Y') }} DeveloperJet. Managed tools for people who build.</span>
                <span>Thoughtful VPS setup. Human support.</span>
            </div>
        </div>
    </footer>
</body>
</html>
