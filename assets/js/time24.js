(function () {
  'use strict';

  function valid24(value) {
    return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);
  }

  function formatDigits(value) {
    var digits = String(value || '').replace(/\D/g, '').slice(0, 4);
    if (digits.length <= 2) return digits;
    return digits.slice(0, 2) + ':' + digits.slice(2);
  }

  document.querySelectorAll('input.time-24').forEach(function (input) {
    input.type = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = 'off';
    input.maxLength = 5;
    input.placeholder = 'HH:mm';
    input.pattern = '(?:[01]\\d|2[0-3]):[0-5]\\d';

    input.addEventListener('input', function () {
      var old = input.value;
      var formatted = formatDigits(old);
      if (old !== formatted) input.value = formatted;
      input.setCustomValidity(input.value === '' && !input.required || valid24(input.value) ? '' : 'Gunakan format 24 jam HH:mm, contoh 06:30 atau 13:50.');
    });

    input.addEventListener('blur', function () {
      if (input.value === '' && !input.required) {
        input.setCustomValidity('');
        return;
      }
      input.setCustomValidity(valid24(input.value) ? '' : 'Gunakan format 24 jam HH:mm, contoh 06:30 atau 13:50.');
    });
  });
})();
