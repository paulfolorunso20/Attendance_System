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
        button.setAttribute("aria-label", isDark ? "Switch to light theme" : "Switch to dark theme");
        button.setAttribute("title", isDark ? "Switch to light theme" : "Switch to dark theme");
        button.innerHTML = `
            <span class="theme-toggle-icon" aria-hidden="true">${isDark ? "L" : "D"}</span>
            <span class="theme-toggle-text">${isDark ? "Light" : "Dark"}</span>
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
