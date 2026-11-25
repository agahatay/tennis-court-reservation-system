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

    // Basic phone check before submitting (the server applies the full rules).
    document.querySelectorAll('input[data-phone-input]').forEach(function (input) {
        function validatePhone() {
            var value = input.value.trim();
            var digits = value.replace(/\D/g, '').length;
            var valid = value === '' || (digits >= 10 && digits <= 15 && /^\+?[0-9 ().\-]+$/.test(value));
            input.setCustomValidity(valid ? '' : 'Please enter a valid phone number, e.g. 0532 123 45 67.');
        }
        input.addEventListener('input', validatePhone);
        validatePhone();
    });

    // Booking summary: show the chosen time slot and its price.
    var summaryTime = document.querySelector('[data-summary-time]');
    var summaryPrice = document.querySelector('[data-summary-price]');
    function updateSummary() {
        var selected = document.querySelector('input[name="time_slot_id"]:checked');
        if (!selected || !summaryTime || !summaryPrice) {
            return;
        }
        summaryTime.textContent = selected.getAttribute('data-label');
        summaryPrice.textContent = selected.getAttribute('data-price');
    }
    document.querySelectorAll('input[name="time_slot_id"]').forEach(function (radio) {
        radio.addEventListener('change', updateSummary);
    });
    updateSummary();
})();
