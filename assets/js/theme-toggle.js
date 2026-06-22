(function () {
    const storageKey = "smartattend-theme";
    const root = document.documentElement;

    function getStoredTheme() {
        try {
            return localStorage.getItem(storageKey);
        } catch (error) {
            return null;
        }
    }

    function setStoredTheme(theme) {
        try {
            localStorage.setItem(storageKey, theme);
        } catch (error) {
            return;
        }
    }

    function applyTheme(theme) {
        const nextTheme = theme === "dark" ? "dark" : "light";
        root.setAttribute("data-theme", nextTheme);
        root.style.colorScheme = nextTheme;
        return nextTheme;
    }

    function updateButton(button, theme) {
        const isDark = theme === "dark";
        button.setAttribute("aria-label", isDark ? "Switch to light theme" : "Switch to moon theme");
        button.setAttribute("title", isDark ? "Switch to light theme" : "Switch to moon theme");
        const sunIcon = `
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2"></path>
                <path d="M12 20v2"></path>
                <path d="m4.93 4.93 1.41 1.41"></path>
                <path d="m17.66 17.66 1.41 1.41"></path>
                <path d="M2 12h2"></path>
                <path d="M20 12h2"></path>
                <path d="m6.34 17.66-1.41 1.41"></path>
                <path d="m19.07 4.93-1.41 1.41"></path>
            </svg>
        `;
        const moonIcon = `
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                <path d="M20.99 12.74A8.5 8.5 0 1 1 11.26 3a6.5 6.5 0 0 0 9.73 9.74Z"></path>
            </svg>
        `;
        button.innerHTML = `
            <span class="theme-toggle-icon" aria-hidden="true">${isDark ? sunIcon : moonIcon}</span>
            <span class="theme-toggle-text">${isDark ? "Light" : "Moon"}</span>
        `;
    }

    const initialTheme = applyTheme(getStoredTheme() || "light");

    document.addEventListener("DOMContentLoaded", function () {
        if (document.querySelector(".theme-toggle")) {
            return;
        }

        const button = document.createElement("button");
        button.type = "button";
        button.className = "theme-toggle";
        updateButton(button, root.getAttribute("data-theme") || initialTheme);

        button.addEventListener("click", function () {
            const currentTheme = root.getAttribute("data-theme") === "dark" ? "dark" : "light";
            const nextTheme = applyTheme(currentTheme === "dark" ? "light" : "dark");
            setStoredTheme(nextTheme);
            updateButton(button, nextTheme);
        });

        document.body.appendChild(button);
    });
})();
