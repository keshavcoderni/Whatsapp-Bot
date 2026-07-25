/* Shared theme state for every admin page. Load this in the document head. */
(function () {
    const storageKey = 'theme';
    const root = document.documentElement;

    function updateIcons(isLight) {
        document.querySelectorAll('.theme-toggle-btn i, .theme-toggle i').forEach((icon) => {
            icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
        });
    }

    function apply(theme) {
        const isLight = theme === 'light';
        // Both names are retained so existing page styles work during the transition.
        root.classList.toggle('light-mode', isLight);
        root.classList.toggle('light-theme-vars', isLight);

        const applyToBody = () => {
            document.body.classList.toggle('light-mode', isLight);
            document.body.classList.toggle('light-theme-vars', isLight);
            updateIcons(isLight);
        };

        if (document.body) {
            applyToBody();
        } else {
            document.addEventListener('DOMContentLoaded', applyToBody, { once: true });
        }
        return isLight;
    }

    function current() {
        return localStorage.getItem(storageKey) === 'light' ? 'light' : 'dark';
    }

    window.InfoTagTheme = {
        apply,
        current,
        toggle() {
            const nextTheme = current() === 'light' ? 'dark' : 'light';
            localStorage.setItem(storageKey, nextTheme);
            return apply(nextTheme);
        }
    };

    apply(current());
    window.addEventListener('storage', (event) => {
        if (event.key === storageKey) apply(current());
    });
})();
