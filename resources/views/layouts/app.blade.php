<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B1736">
    <meta name="description" content="@yield('meta_description', 'DeveloperJet runs AI agents, automation and developer tools on dedicated VPS servers, set up for you with full root access, clear billing and real support. Powered by AmbitionHost Pvt. Ltd.')">
    <title>@yield('title', 'DeveloperJet') · DeveloperJet</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    @vite(['resources/css/app.css', 'resources/css/refine.css', 'resources/css/site.css', 'resources/js/app.js'])
    <script>document.documentElement.classList.add('js');</script>
    @stack('head')
</head>
<body class="dj-body">
    <a class="skip-link" href="#main-content">Skip to content</a>
    @php
        $navProducts = config('navigation.products');
        $navUrl = fn (array $l) => route($l[1], $l[2] ?? []).($l[5] ? '#'.$l[5] : '');
        $productsActive = request()->routeIs('hermes*', 'n8n', 'ai-all-access', 'product.show', 'services.show', 'digital-services', 'compare.*', 'business-automation*', 'ai-app-hosting');
    @endphp

    <header class="dj-header" data-header>
        <div class="dj-container dj-header__bar">
            <a class="dj-brand" href="{{ route('home') }}" aria-label="DeveloperJet home">
                <img src="{{ asset('images/logo.png') }}" alt="DeveloperJet" width="142" height="40">
            </a>

            <nav class="dj-nav" aria-label="Main navigation">
                <ul class="dj-nav__list">
                    <li class="dj-nav__item" data-hermes-dropdown>
                        <button class="dj-nav__link dj-nav__trigger" type="button" aria-expanded="false" aria-controls="mega-products" data-hermes-dropdown-toggle @if ($productsActive) aria-current="page" @endif>
                            Products <x-icon name="chevron-down" :size="16" />
                        </button>
                        <div class="dj-mega" id="mega-products" data-hermes-dropdown-menu>
                            <div class="dj-mega__grid">
                                @foreach ($navProducts as $group => $def)
                                    <div class="dj-mega__col">
                                        <p class="dj-mega__heading">{{ $group }}</p>
                                        <ul>
                                            @foreach ($def['links'] as $l)
                                                <li>
                                                    <a class="dj-mega__link" href="{{ $navUrl($l) }}" @if (url()->current() === $navUrl($l)) aria-current="page" @endif>
                                                        <span class="dj-mega__icon"><x-icon :name="$l[4]" :size="18" /></span>
                                                        <span><strong>{{ $l[0] }}</strong><small>{{ $l[3] }}</small></span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                            <div class="dj-mega__foot">
                                {{-- No links here: every destination already appears once in the columns or the top bar. --}}
                                <span><x-icon name="info" :size="16" /> Every service is prepared by our team after checkout. Delivery is not instant.</span>
                            </div>
                        </div>
                    </li>
                    <li><a class="dj-nav__link" href="{{ route('plans') }}" @if (request()->routeIs('plans')) aria-current="page" @endif>Pricing</a></li>
                    <li><a class="dj-nav__link" href="{{ route('docs.index') }}" @if (request()->routeIs('docs.*')) aria-current="page" @endif>Documentation</a></li>
                    <li><a class="dj-nav__link" href="{{ route('contact') }}" @if (request()->routeIs('contact')) aria-current="page" @endif>Support</a></li>
                </ul>
            </nav>

            <div class="dj-header__actions">
                <a class="dj-cart" href="{{ route('cart.index') }}" aria-label="Cart, {{ $cartCount }} {{ \Illuminate\Support\Str::plural('item', $cartCount) }}">
                    <x-icon name="cart" :size="20" />
                    <span class="dj-cart__label">Cart</span>
                    @if ($cartCount > 0)<span class="dj-cart__count">{{ $cartCount }}</span>@endif
                </a>
                @auth
                    <a class="dj-nav__link dj-hide-md" href="{{ route('dashboard') }}">Dashboard</a>
                    <form class="dj-hide-md" method="POST" action="{{ route('logout') }}" data-loading-form>
                        @csrf
                        <button class="dj-btn dj-btn--secondary dj-btn--sm" type="submit">Log out</button>
                    </form>
                @else
                    <a class="dj-nav__link dj-login" href="{{ route('login') }}">Login</a>
                    <a class="dj-btn dj-btn--primary dj-btn--sm dj-hide-sm" href="{{ route('register') }}">Get Started</a>
                @endauth
                <button class="dj-burger" type="button" aria-expanded="false" aria-controls="primary-menu" aria-label="Open menu" data-menu-toggle>
                    <x-icon name="menu" :size="24" class="dj-burger__open" />
                    <x-icon name="x" :size="24" class="dj-burger__close" />
                </button>
            </div>
        </div>
    </header>

    {{-- The drawer must live OUTSIDE <header>: the header uses backdrop-filter, which makes it the containing
         block for position:fixed children and collapsed the drawer to 0px height (the "dj-drawer-open" bug). --}}
    <div class="dj-drawer-backdrop" data-menu-backdrop hidden></div>
    <div class="dj-drawer" id="primary-menu" data-menu role="dialog" aria-modal="true" aria-label="Site menu" hidden>
        <div class="dj-drawer__inner">
            @foreach ($navProducts as $group => $def)
                <details class="dj-drawer__group" @if ($loop->first) open @endif>
                    <summary>{{ $group }} <x-icon name="chevron-down" :size="18" /></summary>
                    <ul>
                        @foreach ($def['links'] as $l)
                            <li><a href="{{ $navUrl($l) }}" @if (url()->current() === $navUrl($l)) aria-current="page" @endif><x-icon :name="$l[4]" :size="18" /> {{ $l[0] }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
            <ul class="dj-drawer__plain">
                <li><a href="{{ route('plans') }}">Pricing</a></li>
                <li><a href="{{ route('docs.index') }}">Documentation</a></li>
                <li><a href="{{ route('contact') }}">Support</a></li>
                <li><a href="{{ route('cart.index') }}">Cart @if ($cartCount > 0)({{ $cartCount }})@endif</a></li>
            </ul>
            <div class="dj-drawer__actions">
                @auth
                    <a class="dj-btn dj-btn--secondary" href="{{ route('dashboard') }}">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" data-loading-form>
                        @csrf
                        <button class="dj-btn dj-btn--ghost" type="submit">Log out</button>
                    </form>
                @else
                    <a class="dj-btn dj-btn--secondary" href="{{ route('login') }}"><x-icon name="user" :size="18" /> Customer Login</a>
                    <a class="dj-btn dj-btn--primary" href="{{ route('register') }}">Get Started</a>
                @endauth
            </div>
        </div>
    </div>

    <main id="main-content">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="dj-footer">
        <div class="dj-container">
            <div class="dj-footer__top">
                <div class="dj-footer__brand">
                    <a href="{{ route('home') }}" aria-label="DeveloperJet home"><img src="{{ asset('images/logo.png') }}" alt="DeveloperJet" width="156" height="44"></a>
                    <p>AI agents, automation and developer tools on dedicated VPS servers — set up by our team, billed and supported in one place.</p>
                    <p class="dj-footer__company">Powered by AmbitionHost Pvt. Ltd.</p>
                </div>
                @foreach (['AI Products', 'Developer Hosting', 'Digital Services'] as $group)
                    <div class="dj-footer__col">
                        <h2>{{ $group }}</h2>
                        <ul>
                            @foreach ($navProducts[$group]['links'] as $l)
                                <li><a href="{{ $navUrl($l) }}">{{ $l[0] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                <div class="dj-footer__col">
                    <h2>Company</h2>
                    <ul>
                        <li><a href="{{ route('about') }}">About</a></li>
                        <li><a href="{{ route('contact') }}">Contact &amp; Support</a></li>
                        <li><a href="{{ route('docs.index') }}">Documentation</a></li>
                        <li><a href="{{ route('plans') }}">Pricing</a></li>
                        @auth
                            <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        @else
                            <li><a href="{{ route('login') }}">Customer Login</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
            <div class="dj-footer__bottom">
                <span>© {{ date('Y') }} DeveloperJet · AmbitionHost Pvt. Ltd. All rights reserved.</span>
                <span class="dj-footer__legal">
                    <a href="{{ route('terms') }}">Terms</a>
                    <a href="{{ route('privacy') }}">Privacy</a>
                    <a href="{{ route('refund') }}">Refund policy</a>
                </span>
            </div>
        </div>
    </footer>
</body>
</html>
