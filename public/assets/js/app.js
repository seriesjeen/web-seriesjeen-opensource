(function () {
  'use strict';

  // Debounced search input: submit form 400ms after last keystroke
  const searchInputs = document.querySelectorAll('input[type="search"][name="q"]');
  searchInputs.forEach((input) => {
    let timer = null;
    const form = input.closest('form');
    if (!form) return;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => form.submit(), 400);
    });
  });

  // Auto-submit on locale dropdown change in nav (legacy fallback — current nav uses static links)
  document.querySelectorAll('[data-auto-submit]').forEach((el) => {
    el.addEventListener('change', () => el.closest('form')?.submit());
  });
})();
