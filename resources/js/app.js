// Consent-gated Google scripts (GA4 / AdSense). Nothing from Google loads
// until the visitor accepts; "Essential only" loads nothing.
const consentMeta = document.querySelector('meta[name="epic-consent"]');
if (consentMeta) {
    const banner = document.getElementById('consent-banner');
    const read = () => { try { return localStorage.getItem('consent'); } catch (e) { return null; } };
    const write = (v) => { try { localStorage.setItem('consent', v); } catch (e) { /* storage unavailable */ } };

    const loadScript = (src, attrs = {}) => {
        const s = document.createElement('script');
        s.async = true;
        s.src = src;
        Object.entries(attrs).forEach(([k, v]) => s.setAttribute(k, v));
        document.head.appendChild(s);
    };

    const activate = () => {
        const ga = document.querySelector('meta[name="epic-ga4"]')?.content;
        if (ga && /^G-[A-Z0-9]{6,14}$/.test(ga)) {
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };
            window.gtag('js', new Date());
            window.gtag('config', ga, { anonymize_ip: true });
            loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga));
        }
        const ads = document.querySelector('meta[name="epic-adsense"]')?.content;
        if (ads && /^ca-pub-\d{10,20}$/.test(ads)) {
            loadScript('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(ads), { crossorigin: 'anonymous' });
        }
    };

    const choice = read();
    if (choice === 'granted') {
        activate();
    } else if (choice === null && banner) {
        banner.classList.remove('hidden');
    }

    document.querySelectorAll('[data-consent]').forEach((button) => {
        button.addEventListener('click', () => {
            write(button.dataset.consent);
            banner?.classList.add('hidden');
            if (button.dataset.consent === 'granted') activate();
        });
    });

    document.querySelectorAll('[data-consent-reset]').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            try { localStorage.removeItem('consent'); } catch (err) { /* ignore */ }
            banner?.classList.remove('hidden');
        });
    });
}

// Mobile menu
const toggle = document.getElementById('menu-toggle');
const menu = document.getElementById('mobile-menu');

if (toggle && menu) {
    toggle.addEventListener('click', () => {
        const open = menu.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', String(open));
    });
}

// Theme toggle (dark default; the initial theme is applied by an inline
// script in <head> before first paint to avoid a flash)
document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) { /* storage unavailable */ }
    });
});

// Scroll reveal
const revealItems = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window && revealItems.length) {
    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px' });
    revealItems.forEach((el) => io.observe(el));
} else {
    revealItems.forEach((el) => el.classList.add('is-visible'));
}

// Table of contents scroll-spy
const tocLinks = document.querySelectorAll('[data-toc-link]');
const sections = document.querySelectorAll('[data-story-section]');
if (tocLinks.length && sections.length && 'IntersectionObserver' in window) {
    const spy = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                tocLinks.forEach((a) => a.classList.toggle('is-active', a.dataset.tocLink === entry.target.id));
            }
        });
    }, { rootMargin: '-20% 0px -65% 0px' });
    sections.forEach((s) => spy.observe(s));
}

// Copy link buttons
document.querySelectorAll('[data-copy-link]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copyLink);
            const original = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => { button.textContent = original; }, 1800);
        } catch (e) { /* clipboard unavailable */ }
    });
});

// Home / live-desk tabs
document.querySelectorAll('[data-tabs]').forEach((root) => {
    const tabs = root.querySelectorAll('[data-tab]');
    const panels = root.querySelectorAll('[data-tab-panel]');
    tabs.forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            tabs.forEach((t) => {
                const on = t === tab;
                t.setAttribute('aria-selected', String(on));
                t.classList.toggle('!border-accent', on);
                t.classList.toggle('!text-ink', on);
            });
            panels.forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== tab.dataset.tab));
        });
    });
});

// Click-to-play embeds (YouTube privacy-enhanced domain)
document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-embed-play]');
    if (!button) return;
    const holder = button.closest('[data-embed]');
    if (!holder) return;
    const frame = document.createElement('iframe');
    frame.src = holder.dataset.embed;
    frame.className = 'absolute inset-0 h-full w-full';
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.allowFullscreen = true;
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.title = button.getAttribute('aria-label') || 'Video player';
    holder.replaceChildren(frame);
});

// Live auto-refresh: re-fetch fragments every minute while the tab is visible
const polls = document.querySelectorAll('[data-live-poll]');
if (polls.length) {
    const refresh = async () => {
        if (document.hidden) return;
        for (const el of polls) {
            try {
                const res = await fetch(el.dataset.livePoll, { headers: { Accept: 'text/html' } });
                if (!res.ok) continue;
                const html = (await res.text()).trim();
                if (html && html !== el.dataset.lastHtml) {
                    el.innerHTML = html;
                    el.dataset.lastHtml = html;
                }
            } catch (e) { /* network hiccup - try again next cycle */ }
        }
    };
    setInterval(refresh, 60000);
}

// Article reading progress
const bar = document.getElementById('read-progress');
if (bar) {
    const update = () => {
        const h = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.width = (h > 0 ? Math.min(100, (window.scrollY / h) * 100) : 0) + '%';
    };
    window.addEventListener('scroll', update, { passive: true });
    update();
}
