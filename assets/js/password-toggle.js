document.querySelectorAll('input[type="password"]').forEach((input) => {
    if (input.closest(".password-toggle-field")) {
        return;
    }

    input.type = "password";

    const wrapper = document.createElement("div");
    wrapper.className = "password-toggle-field";
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const button = document.createElement("button");
    button.type = "button";
    button.className = "password-toggle-button";
    button.setAttribute("aria-label", "Show password");
    button.setAttribute("title", "Show password");
    button.innerHTML = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>
    `;

    button.addEventListener("click", (event) => {
        event.preventDefault();

        const isHidden = input.type === "password";
        input.type = isHidden ? "text" : "password";
        button.classList.toggle("is-visible", isHidden);
        button.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
        button.setAttribute("title", isHidden ? "Hide password" : "Show password");
        input.focus({ preventScroll: true });
    });

    wrapper.appendChild(button);
});

window.addEventListener("pageshow", () => {
    document.querySelectorAll(".password-toggle-field input").forEach((input) => {
        input.type = "password";
    });

    document.querySelectorAll(".password-toggle-button").forEach((button) => {
        button.classList.remove("is-visible");
        button.setAttribute("aria-label", "Show password");
        button.setAttribute("title", "Show password");
    });
});
