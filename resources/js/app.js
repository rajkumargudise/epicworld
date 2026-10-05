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
