import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// x-reveal: fades an element up into place the first time it scrolls into
// view. No-ops (element stays fully visible) when the visitor has requested
// reduced motion, or when IntersectionObserver isn't available.
Alpine.directive('reveal', (el) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || typeof IntersectionObserver === 'undefined') {
        return;
    }

    el.classList.add('opacity-0', 'translate-y-3');
    el.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                el.classList.remove('opacity-0', 'translate-y-3');
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.1 });

    observer.observe(el);
});

Alpine.start();
