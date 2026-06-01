(function () {
    function formatMatric(value) {
        var digits = value.replace(/\D/g, "").slice(0, 9);

        if (digits.length <= 4) {
            return digits;
        }

        return digits.slice(0, 4) + "/" + digits.slice(4);
    }

    document.querySelectorAll("[data-matric-format]").forEach(function (input) {
        input.addEventListener("input", function () {
            var formatted = formatMatric(input.value);
            if (input.value !== formatted) {
                input.value = formatted;
            }
        });
    });
})();
