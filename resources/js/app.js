import './bootstrap';

// Mobile drawer. The drawer is a sibling of <header> (see layouts/app) so the header's backdrop-filter
// cannot become its containing block. One listener set per page; state lives in aria-expanded.
const menuToggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');
const menuBackdrop = document.querySelector('[data-menu-backdrop]');
const isDrawerOpen = () => menuToggle?.getAttribute('aria-expanded') === 'true';
const focusableIn = (root) => Array.from(root.querySelectorAll('a[href], button:not([disabled]), summary, input, select, textarea, [tabindex]:not([tabindex="-1"])'))
    .filter((el) => el.getClientRects().length > 0);

const setDrawer = (open, { restoreFocus = false } = {}) => {
    if (!menuToggle || !menu) return;
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    menu.hidden = !open;
    if (menuBackdrop) menuBackdrop.hidden = !open;
    menu.classList.toggle('is-open', open);
    document.body.classList.toggle('dj-drawer-open', open);
    if (open) {
        // Keep the drawer exactly below the real header height (the header can be taller than the token).
        const header = document.querySelector('[data-header]');
        if (header) document.documentElement.style.setProperty('--dj-drawer-top', `${Math.round(header.getBoundingClientRect().bottom)}px`);
        focusableIn(menu)[0]?.focus({ preventScroll: true });
    } else if (restoreFocus) {
        menuToggle.focus({ preventScroll: true });
    }
};

if (menuToggle && menu) {
    menuToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setDrawer(!isDrawerOpen());
    });
    menu.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a[href]')) setDrawer(false);
    });
    menuBackdrop?.addEventListener('click', () => setDrawer(false, { restoreFocus: true }));
    document.addEventListener('click', (event) => {
        if (!isDrawerOpen() || !(event.target instanceof Node)) return;
        if (!menu.contains(event.target) && !menuToggle.contains(event.target)) setDrawer(false);
    });
    document.addEventListener('keydown', (event) => {
        if (!isDrawerOpen()) return;
        if (event.key === 'Escape') {
            setDrawer(false, { restoreFocus: true });
        } else if (event.key === 'Tab') {
            // Focus trap: cycle between the toggle (close button) and the drawer's controls.
            const items = [menuToggle, ...focusableIn(menu)];
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            else if (!items.includes(document.activeElement)) { event.preventDefault(); first.focus(); }
        }
    });
    window.matchMedia('(min-width: 1080px)').addEventListener('change', (e) => { if (e.matches) setDrawer(false); });
    // Back/forward cache can restore a page with the drawer open: always start closed.
    window.addEventListener('pageshow', () => setDrawer(false));
}

// Products mega menu (attribute names kept for backwards compatibility with existing markup/tests).
const dropdowns = Array.from(document.querySelectorAll('[data-hermes-dropdown]'));
const hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)');

const setDropdown = (dropdown, open) => {
    dropdown.querySelector('[data-hermes-dropdown-toggle]')?.setAttribute('aria-expanded', String(open));
    dropdown.classList.toggle('is-open', open);
};

dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector('[data-hermes-dropdown-toggle]');
    let closeTimer;

    toggle?.addEventListener('click', (event) => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        // A mouse click on a hover-opened menu keeps it open; keyboard activation (detail 0) toggles.
        const next = hoverCapable.matches && event.detail > 0 ? true : !isOpen;
        dropdowns.forEach((d) => setDropdown(d, false));
        setDropdown(dropdown, next);
    });

    dropdown.addEventListener('mouseenter', () => {
        if (!hoverCapable.matches) return;
        clearTimeout(closeTimer);
        setDropdown(dropdown, true);
    });
    dropdown.addEventListener('mouseleave', () => {
        if (!hoverCapable.matches) return;
        closeTimer = setTimeout(() => setDropdown(dropdown, false), 200);
    });

    dropdown.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setDropdown(dropdown, false);
            toggle?.focus();
        }
    });
    dropdown.addEventListener('focusout', (event) => {
        if (event.relatedTarget instanceof Node && !dropdown.contains(event.relatedTarget)) setDropdown(dropdown, false);
    });
});

document.addEventListener('click', (event) => {
    dropdowns.forEach((dropdown) => {
        if (event.target instanceof Node && !dropdown.contains(event.target)) setDropdown(dropdown, false);
    });
});

const billingButtons = document.querySelectorAll('[data-billing-toggle]');

billingButtons.forEach((button) => {
    button.addEventListener('click', () => {
        const billing = button.dataset.billingToggle;

        billingButtons.forEach((option) => {
            const isActive = option === button;

            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-pressed', String(isActive));
        });

        document.querySelectorAll('[data-plan-card]').forEach((card) => {
            const price = card.querySelector('[data-monthly]');
            const billingNote = card.querySelector('[data-billing-note]');
            const annualTotal = card.querySelector('[data-annual-total-note]');
            const annualSavings = card.querySelector('[data-annual-savings-note]');
            const monthlyCompare = card.querySelector('[data-monthly-compare]');

            if (!price || !billingNote || !annualTotal) {
                return;
            }
            // Savings / comparison lines exist only when yearly is actually cheaper than 12 × monthly.
            const annual = billing === 'annual';
            price.textContent = annual ? price.dataset.annual : price.dataset.monthly;
            billingNote.textContent = annual ? 'Billed for 12 months upfront' : 'Billed monthly';
            annualTotal.hidden = !annual;
            if (annualSavings) annualSavings.hidden = !annual;
            if (monthlyCompare) monthlyCompare.hidden = !annual;
        });
    });
});

// Phase 1 UX: keep the cart form's billing interval in sync with the pricing toggle.
document.querySelectorAll('[data-billing-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const interval = button.dataset.billingToggle === 'annual' ? 'yearly' : 'monthly';
        document.querySelectorAll('[data-billing-input]').forEach((input) => {
            input.value = interval;
        });
    });
});

// Loading state: disable double submits and show a spinner on form buttons.
document.querySelectorAll('form[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach((btn) => {
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
        });
    });
});

// Scroll reveal (respects reduced motion; content stays visible without JS).
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const items = document.querySelectorAll('[data-reveal]');
    if (items.length) {
        document.documentElement.classList.add('reveal-ready');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        items.forEach((el) => observer.observe(el));
    }
}

// Password visibility toggle: swap input type and icon; accessible label follows the state.
const eyeOnSvg = '<svg class="dj-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
const eyeOffSvg = '<svg class="dj-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="m1 1 22 22"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/></svg>';

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const input = document.getElementById(toggle.dataset.passwordToggle);
    if (!input) return;

    toggle.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(reveal));
        toggle.innerHTML = reveal ? eyeOffSvg : eyeOnSvg;
        input.focus();
    });
});

// Customer dashboard: collapsible section menu on small screens.
const dashToggle = document.querySelector('[data-dash-toggle]');
const dashSections = document.querySelector('[data-dash-sections]');
if (dashToggle && dashSections) {
    dashToggle.addEventListener('click', () => {
        const open = !dashSections.classList.contains('is-open');
        dashSections.classList.toggle('is-open', open);
        dashToggle.setAttribute('aria-expanded', String(open));
    });
    dashSections.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dashSections.classList.remove('is-open');
            dashToggle.setAttribute('aria-expanded', 'false');
            dashToggle.focus();
        }
    });
}

// Checkout: show the provider + key fields only when "Add a new key" is chosen.
document.querySelectorAll('[data-ai-choice]').forEach((box) => {
    const select = box.querySelector('[data-ai-select]');
    const fields = box.querySelector('[data-ai-new]');
    if (!select || !fields) return;
    const sync = () => { fields.hidden = select.value !== 'new'; };
    select.addEventListener('change', sync);
    sync();
});

// Checkout: Razorpay shows the total with GST, wallet shows the GST-free total.
const payOptions = document.querySelector('[data-pay-options]');
if (payOptions) {
    const syncReview = () => {
        const route = payOptions.querySelector('input[name=payment_method]:checked')?.dataset.payRoute || 'online';
        document.querySelectorAll('[data-review]').forEach((dl) => { dl.hidden = dl.dataset.review !== route; });
    };
    payOptions.addEventListener('change', syncReview);
    syncReview();
}

// Customer app shell: off-canvas sidebar on small screens (menu button, overlay, close button, Escape).
const appMenu = document.querySelector('[data-app-menu]');
const appSide = document.getElementById('app-side');
const appOverlay = document.querySelector('[data-app-overlay]');
if (appMenu && appSide) {
    const setOpen = (open) => {
        document.body.classList.toggle('app-nav-open', open);
        appMenu.setAttribute('aria-expanded', String(open));
        if (appOverlay) appOverlay.hidden = !open;
        if (open) appSide.querySelector('a, button')?.focus();
    };
    appMenu.addEventListener('click', () => setOpen(!document.body.classList.contains('app-nav-open')));
    appOverlay?.addEventListener('click', () => setOpen(false));
    document.querySelector('[data-app-close]')?.addEventListener('click', () => { setOpen(false); appMenu.focus(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.body.classList.contains('app-nav-open')) { setOpen(false); appMenu.focus(); }
    });
}

// Admin: off-canvas sidebar below 961px (overlay, Escape, outside click, focus management) and persisted theme.
const adminMenuButton = document.querySelector('[data-admin-menu]');
const adminSidebar = document.getElementById('admin-sidebar');
const adminOverlay = document.querySelector('[data-admin-overlay]');
if (adminMenuButton && adminSidebar) {
    const setAdminNav = (open, restoreFocus = false) => {
        adminSidebar.classList.toggle('is-open', open);
        adminMenuButton.setAttribute('aria-expanded', String(open));
        adminMenuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        if (adminOverlay) adminOverlay.hidden = !open;
        document.body.classList.toggle('admin-nav-open', open);
        if (open) {
            (adminSidebar.querySelector('[aria-current="page"]') || adminSidebar.querySelector('a'))?.focus({ preventScroll: true });
        } else if (restoreFocus) {
            adminMenuButton.focus({ preventScroll: true });
        }
    };
    adminMenuButton.addEventListener('click', () => setAdminNav(!adminSidebar.classList.contains('is-open')));
    adminOverlay?.addEventListener('click', () => setAdminNav(false, true));
    adminSidebar.querySelector('[data-admin-close]')?.addEventListener('click', () => setAdminNav(false, true));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && adminSidebar.classList.contains('is-open')) setAdminNav(false, true);
    });
    window.matchMedia('(min-width: 961px)').addEventListener('change', (e) => { if (e.matches) setAdminNav(false); });
    window.addEventListener('pageshow', () => setAdminNav(false));
}

document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('dj-admin-theme', next); } catch (e) { /* storage unavailable */ }
});

// Responsive tables: copy header text into data-label so CSS can render rows as cards on small screens.
document.querySelectorAll('.data-table, .admin-table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    if (!heads.length) return;
    table.querySelectorAll('tbody tr').forEach((row) => {
        [...row.children].forEach((cell, i) => {
            if (!cell.hasAttribute('data-label') && heads[i] && row.children.length === heads.length) {
                cell.setAttribute('data-label', heads[i]);
            }
        });
    });
});

// ---------------------------------------------------------------------------------------------------------------
// Documentation centre: instant search, collapsed mobile nav, code copy buttons and "On this page" highlighting.
// Everything is progressive enhancement — the search form, <details> navigation and anchors work without JS.
// ---------------------------------------------------------------------------------------------------------------
document.querySelectorAll('[data-doc-search]').forEach((form) => {
    const input = form.querySelector('input[type=search]');
    const list = form.querySelector('[role=listbox]');
    let index = [];
    try { index = JSON.parse(form.querySelector('[data-doc-index]')?.textContent || '[]'); } catch (e) { index = []; }
    if (!input || !list || !index.length) return;

    let active = -1;
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const escapeHtml = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const render = () => {
        const words = input.value.toLowerCase().split(/\s+/).filter((w) => w.length > 1);
        list.innerHTML = '';
        active = -1;
        if (!words.length) { close(); return; }
        const hits = index
            .map((a) => {
                const hay = `${a.t} ${a.e} ${a.c}`.toLowerCase();
                if (!words.every((w) => hay.includes(w))) return null;
                return { a, score: words.reduce((s, w) => s + (a.t.toLowerCase().includes(w) ? 10 : 1), 0) };
            })
            .filter(Boolean).sort((x, y) => y.score - x.score).slice(0, 7);

        if (!hits.length) {
            list.innerHTML = '<li class="is-empty">No quick matches — press Enter to search full article text.</li>';
        } else {
            hits.forEach(({ a }, i) => {
                const li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.id = `${list.id}-${i}`;
                li.innerHTML = `<a href="${escapeHtml(a.u)}" tabindex="-1"><small>${escapeHtml(a.c)}</small><strong>${escapeHtml(a.t)}</strong>${a.e ? `<span>${escapeHtml(a.e)}</span>` : ''}</a>`;
                list.appendChild(li);
            });
        }
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const move = (step) => {
        const options = [...list.querySelectorAll('[role=option]')];
        if (!options.length) return;
        active = (active + step + options.length) % options.length;
        options.forEach((o, i) => o.setAttribute('aria-selected', String(i === active)));
        input.setAttribute('aria-activedescendant', options[active].id);
        options[active].scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('input', render);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) render(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Escape') { close(); }
        else if (e.key === 'Enter' && active >= 0) {
            const link = list.querySelectorAll('[role=option] a')[active];
            if (link) { e.preventDefault(); window.location.href = link.href; }
        }
    });
    document.addEventListener('click', (e) => { if (!form.contains(e.target)) close(); });
    form.addEventListener('focusout', (e) => { if (!form.contains(e.relatedTarget)) close(); });
});

// "/" focuses the docs search (unless typing in a field).
if (document.querySelector('[data-doc-search]')) {
    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || e.target.closest('input, textarea, select, [contenteditable]')) return;
        e.preventDefault();
        document.querySelector('[data-doc-search] input[type=search]')?.focus();
    });
}

// On article pages, collapse the category navigation on small screens so the article comes first.
document.querySelectorAll('[data-doc-nav]').forEach((nav) => {
    if (nav.querySelector('[aria-current=page]:not(.dj-doc-nav__home)') && window.matchMedia('(max-width: 899px)').matches) {
        nav.open = false;
    }
});

// Copy buttons on code blocks.
document.querySelectorAll('.dj-doc-body pre').forEach((pre) => {
    if (!navigator.clipboard) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'dj-doc-copy';
    btn.textContent = 'Copy';
    btn.setAttribute('aria-label', 'Copy code to clipboard');
    btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(pre.querySelector('code')?.innerText ?? pre.innerText);
            btn.textContent = 'Copied';
        } catch (e) { btn.textContent = 'Press Ctrl+C'; }
        setTimeout(() => { btn.textContent = 'Copy'; }, 1800);
    });
    pre.appendChild(btn);
});

// Highlight the current section in "On this page".
const docToc = document.querySelector('[data-doc-toc]');
if (docToc && 'IntersectionObserver' in window) {
    const links = new Map([...docToc.querySelectorAll('a')].map((a) => [decodeURIComponent(a.hash.slice(1)), a]));
    const headings = [...links.keys()].map((id) => document.getElementById(id)).filter(Boolean);
    const setActive = (id) => links.forEach((a, key) => a.classList.toggle('is-active', key === id));
    const observer = new IntersectionObserver((entries) => {
        const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
        if (visible.length) setActive(visible[0].target.id);
    }, { rootMargin: '-80px 0px -65% 0px' });
    headings.forEach((h) => observer.observe(h));
}

// Admin redesign: desktop sidebar collapse (persisted), '/' focuses admin search, bell closes on outside click.
const adminCollapseBtn = document.querySelector('[data-admin-collapse]');
try { if (localStorage.getItem('dj-admin-sidebar-collapsed') === '1') document.body.classList.add('admin-nav-collapsed'); } catch (e) {}
adminCollapseBtn?.addEventListener('click', () => { const collapsed = document.body.classList.toggle('admin-nav-collapsed'); adminCollapseBtn.setAttribute('aria-expanded', String(!collapsed)); try { localStorage.setItem('dj-admin-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {} });
document.addEventListener('keydown', (e) => { if (e.key === '/' && !e.target.closest('input, textarea, select, [contenteditable]')) { const s = document.querySelector('.admin-search input'); if (s) { e.preventDefault(); s.focus(); } } });
const adminBell = document.querySelector('.admin-bell');
document.addEventListener('click', (e) => { if (adminBell && adminBell.open && !adminBell.contains(e.target)) adminBell.open = false; });

// Customer service setup timeline: poll the DB-backed progress endpoint until the setup reaches a terminal state.
// No WebSocket server — plain fetch every 5s, paused implicitly when the tab is hidden (fetch on refocus).
const setupStages = document.querySelector('[data-setup-stages][data-poll-url]');
if (setupStages) {
    const pollUrl = setupStages.dataset.pollUrl;
    const summaryEl = document.querySelector('[data-setup-summary]');
    const barEl = document.querySelector('[data-setup-bar]');
    const barWrap = barEl?.parentElement;
    let timer = null;

    const applyProgress = (data) => {
        let done = 0;
        (data.stages || []).forEach((stage) => {
            const li = setupStages.querySelector(`[data-stage-key="${stage.key}"]`);
            if (!li) return;
            li.dataset.stageState = stage.state;
            if (stage.state === 'done') done += 1;
            let detail = li.querySelector('.setup-detail');
            if (stage.detail) {
                if (!detail) { detail = document.createElement('span'); detail.className = 'setup-detail'; li.appendChild(detail); }
                detail.textContent = stage.detail;
            } else if (detail) {
                detail.remove();
            }
        });
        if (summaryEl && typeof data.summary === 'string') summaryEl.textContent = data.summary;
        if (barEl && barWrap && setupStages.children.length) {
            barEl.style.width = `${Math.round((done / setupStages.children.length) * 100)}%`;
            barWrap.setAttribute('aria-valuenow', String(done));
        }
        if (!data.poll && timer) {
            clearInterval(timer);
            timer = null;
            // Ready: reload once so the full active panel (application link, controls, invoices) renders server-side.
            if (data.ready) window.location.reload();
        }
    };

    const poll = async () => {
        try {
            const res = await fetch(pollUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) applyProgress(await res.json());
        } catch (e) { /* transient network error: next tick retries */ }
    };

    timer = setInterval(poll, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden && timer) poll(); });
}

// Plan carousel (products with more than 3 plans): arrows scroll one card, counter + progress follow the scroll.
document.querySelectorAll('[data-plan-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-carousel-track]');
    const prev = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    const range = carousel.querySelector('[data-carousel-range]');
    const progress = carousel.querySelector('[data-carousel-progress]');
    if (!track) return;
    const cards = () => [...track.querySelectorAll(':scope > .dj-plan')];
    const pad = (n) => String(n).padStart(2, '0');
    const step = () => {
        const first = cards()[0];
        if (!first) return track.clientWidth;
        const gap = parseFloat(getComputedStyle(track).columnGap) || 24;
        return first.getBoundingClientRect().width + gap;
    };
    const update = () => {
        const all = cards();
        const max = track.scrollWidth - track.clientWidth;
        const w = step();
        const start = Math.round(track.scrollLeft / w);
        const visible = Math.max(1, Math.round(track.clientWidth / w));
        const end = Math.min(all.length, start + visible);
        if (range) range.textContent = `${pad(Math.min(start + 1, all.length))}–${pad(end)} / ${all.length} plans`;
        if (progress) {
            progress.style.width = `${Math.min(100, (visible / all.length) * 100)}%`;
            progress.style.marginLeft = `${max > 0 ? (track.scrollLeft / max) * (100 - Math.min(100, (visible / all.length) * 100)) : 0}%`;
        }
        if (prev) prev.disabled = track.scrollLeft <= 2;
        if (next) next.disabled = track.scrollLeft >= max - 2;
    };
    prev?.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    next?.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
    track.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') { e.preventDefault(); next?.click(); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); prev?.click(); }
    });
    track.addEventListener('scroll', () => window.requestAnimationFrame(update), { passive: true });
    window.addEventListener('resize', update);
    update();
});

// Deep links like /n8n#plan-n8n-edge (used by AmbitionHost "Setup" buttons): bring that plan card into view
// inside its carousel and highlight it briefly.
if (location.hash.startsWith('#plan-')) {
    const heading = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    const card = heading?.closest('.dj-plan');
    if (card) {
        window.addEventListener('load', () => {
            card.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'start' });
            card.classList.add('is-targeted');
            setTimeout(() => card.classList.remove('is-targeted'), 2600);
        });
    }
}

// Admin staff: show which sidebar menus the selected role unlocks.
document.querySelectorAll('select[data-role-preview]').forEach((select) => {
    const out = document.getElementById(select.dataset.rolePreview);
    if (!out) return;
    const show = () => { out.textContent = `Sidebar mein dikhega: ${select.selectedOptions[0]?.dataset.menus || '—'}`; };
    select.addEventListener('change', show);
    show();
});

// Admin: a link like <a data-open-details="id"> opens and scrolls to that <details> panel.
document.querySelectorAll('[data-open-details]').forEach((link) => {
    link.addEventListener('click', (e) => {
        const panel = document.getElementById(link.dataset.openDetails);
        if (!panel) return;
        e.preventDefault();
        panel.open = true;
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        panel.querySelector('input:not([type=hidden])')?.focus({ preventScroll: true });
    });
});

// Admin wallet: searchable customer picker (name / email / phone). Server returns at most 8 rows, so it scales to
// tens of thousands of customers. Without JS the field is a plain email input and the server still validates it.
document.querySelectorAll('[data-customer-picker]').forEach((picker) => {
    const input = picker.querySelector('input');
    const list = picker.querySelector('[role=listbox]');
    const hint = picker.querySelector('[data-picker-hint]');
    const url = picker.dataset.url;
    if (!input || !list || !url) return;

    const defaultHint = hint ? hint.textContent : '';
    let items = [];
    let active = -1;
    let timer = null;
    let seq = 0;

    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; };
    const setHint = (text) => { if (hint) hint.textContent = text; };

    const choose = (i) => {
        const c = items[i];
        if (!c) return;
        input.value = c.email;
        setHint(`Chuna gaya: ${c.name} · ${c.email} · Wallet balance ${c.balance}${c.active ? '' : ' · (account inactive)'}`);
        close();
    };

    const render = () => {
        list.innerHTML = '';
        active = -1;
        if (!items.length) {
            const li = document.createElement('li');
            li.className = 'is-empty';
            li.textContent = 'Koi customer nahi mila.';
            list.appendChild(li);
        }
        items.forEach((c, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.id = `${list.id}-${i}`;
            const name = document.createElement('strong');
            name.textContent = c.name;
            const email = document.createElement('span');
            email.textContent = c.email;
            const bal = document.createElement('small');
            bal.textContent = `Balance ${c.balance}${c.active ? '' : ' · inactive'}`;
            li.append(name, email, bal);
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(i); });
            list.appendChild(li);
        });
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const search = async () => {
        const q = input.value.trim();
        if (q.length < 2) { items = []; close(); setHint(defaultHint); return; }
        const mine = ++seq;
        try {
            const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok || mine !== seq) return;
            items = (await res.json()).data || [];
            render();
        } catch (e) { /* network hiccup: the next keystroke retries */ }
    };

    const move = (step) => {
        const options = [...list.querySelectorAll('[role=option]')];
        if (!options.length) return;
        active = (active + step + options.length) % options.length;
        options.forEach((o, i) => o.setAttribute('aria-selected', String(i === active)));
        input.setAttribute('aria-activedescendant', options[active].id);
        options[active].scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('input', () => { setHint(defaultHint); clearTimeout(timer); timer = setTimeout(search, 250); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden && items.length) render(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Escape') { close(); }
        else if (e.key === 'Enter' && !list.hidden && active >= 0) { e.preventDefault(); choose(active); }
    });
    input.addEventListener('blur', () => setTimeout(close, 120));
});


// Admin order page: picking a server that is already in inventory hides the "new VPS" fields.
document.querySelectorAll('[data-setup-form]').forEach((form) => {
    const select = form.querySelector('[data-setup-server]');
    if (!select) return;
    const sync = () => {
        const existing = select.value !== '';
        form.querySelectorAll('[data-new-vps]').forEach((el) => { el.hidden = existing; });
    };
    select.addEventListener('change', sync);
    sync();
});

// Customer server login: show/hide and copy the root password, copy IP and commands.
document.querySelectorAll('[data-reveal]').forEach((btn) => {
    const input = btn.closest('.secret-field')?.querySelector('[data-secret]');
    if (!input) return;
    btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
    });
});
const copyWithFeedback = async (btn, text) => {
    try { await navigator.clipboard.writeText(text); btn.textContent = 'Copied'; } catch (e) { btn.textContent = 'Press Ctrl+C'; }
    setTimeout(() => { btn.textContent = 'Copy'; }, 1800);
};
document.querySelectorAll('[data-copy-text]').forEach((btn) => btn.addEventListener('click', () => copyWithFeedback(btn, btn.dataset.copyText)));
document.querySelectorAll('[data-copy-from-secret]').forEach((btn) => btn.addEventListener('click', () => {
    const input = btn.closest('.secret-field')?.querySelector('[data-secret]');
    if (input) copyWithFeedback(btn, input.value);
}));

// Admin toasts: close button, success messages hide themselves after a few seconds.
document.querySelectorAll('[data-toast]').forEach((toast) => {
    const close = () => { toast.classList.add('is-leaving'); setTimeout(() => toast.remove(), 300); };
    toast.querySelector('[data-toast-close]')?.addEventListener('click', close);
    if (toast.hasAttribute('data-autohide')) setTimeout(close, 6000);
});

// Admin install terminal: reveal lines one by one, then follow new lines while the install is running.
document.querySelectorAll('[data-terminal]').forEach((term) => {
    const body = term.querySelector('[data-term-body]');
    const statusEl = term.querySelector('[data-term-status]');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const lines = [...body.querySelectorAll('[data-ln]')];
    if (!reduce && lines.length) {
        lines.forEach((ln) => { ln.style.display = 'none'; });
        lines.forEach((ln, i) => setTimeout(() => { ln.style.display = ''; body.scrollTop = body.scrollHeight; }, 90 * (i + 1)));
    } else {
        body.scrollTop = body.scrollHeight;
    }
    if (term.dataset.running !== '1') return;
    let lastId = Number(term.dataset.lastId || 0);
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const tick = async () => {
        try {
            const res = await fetch(`${term.dataset.feed}?after=${lastId}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            const wait = body.querySelector('[data-term-wait]');
            data.lines.forEach((l) => {
                lastId = Math.max(lastId, l.id);
                const cls = ({ info: 'ok', warn: 'warn', error: 'error', step: 'step' })[l.level] || 'muted';
                const html = `<span class="ln ln-${cls}"><time>${esc(l.time)}</time><span class="tag">[${esc(l.step)}]</span>${esc(l.message)}</span>`;
                if (wait) wait.insertAdjacentHTML('beforebegin', html); else body.insertAdjacentHTML('beforeend', html);
            });
            body.scrollTop = body.scrollHeight;
            if (data.finished) {
                if (statusEl) statusEl.textContent = data.status.replace(/_/g, ' ');
                setTimeout(() => window.location.reload(), 1200);
                return;
            }
        } catch (e) { /* network hiccup: try again */ }
        setTimeout(tick, 2000);
    };
    setTimeout(tick, 1500);
});

// One-click protection: after the first submit the button is disabled and says what is happening.
document.querySelectorAll('form[data-submit-once]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.sent === '1') { event.preventDefault(); return; }
        if (event.defaultPrevented) return; // the confirm() dialog was cancelled
        form.dataset.sent = '1';
        form.querySelectorAll('button[type="submit"]').forEach((btn) => {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.textContent = form.dataset.submitOnce || 'Working…';
        });
    });
});
