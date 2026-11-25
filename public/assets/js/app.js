/*
 * Small progressive enhancements. Every page also works without JavaScript;
 * all validation is repeated on the server.
 */
(function () {
    'use strict';

    // Ask for confirmation before submitting forms marked with data-confirm
    // (delete reservation, change payment status, delete time slot).
    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Reload the page when a filter changes (court / date selection).
    document.querySelectorAll('form[data-auto-submit]').forEach(function (form) {
        form.addEventListener('change', function () {
            form.submit();
        });
    });

    // Phone numbers must contain 10 to 15 digits; show the browser's own
    // validation message instead of submitting an invalid number.
    document.querySelectorAll('input[data-phone-input]').forEach(function (input) {
        function validatePhone() {
            var digits = input.value.replace(/\D/g, '').length;
            var valid = input.value === '' || (digits >= 10 && digits <= 15 && /^\+?[0-9 ().\-]+$/.test(input.value));
            input.setCustomValidity(valid ? '' : 'Please enter a valid phone number (10–15 digits).');
        }
        input.addEventListener('input', validatePhone);
        validatePhone();
    });
})();
