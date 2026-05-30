document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-autosave]').forEach((form) => {
    const key = 'dp-autosave-' + (form.dataset.autosave || location.pathname);
    try {
      const saved = JSON.parse(localStorage.getItem(key) || '{}');
      [...form.elements].forEach((el) => {
        if (!el.name || el.type === 'hidden' || el.type === 'password') return;
        if (saved[el.name] !== undefined && !el.value) el.value = saved[el.name];
      });
    } catch (e) {}
    form.addEventListener('input', () => {
      const data = {};
      [...form.elements].forEach((el) => {
        if (!el.name || el.type === 'hidden' || el.type === 'password') return;
        data[el.name] = el.value;
      });
      localStorage.setItem(key, JSON.stringify(data));
    });
    form.addEventListener('submit', () => localStorage.removeItem(key));
  });

  document.querySelectorAll('[data-download-svg]').forEach((button) => {
    button.addEventListener('click', () => {
      const svg = document.querySelector(button.dataset.downloadSvg);
      if (!svg) return;
      const blob = new Blob([svg.outerHTML], {type: 'image/svg+xml'});
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = (button.dataset.filename || 'barcode') + '.svg';
      link.click();
      URL.revokeObjectURL(link.href);
    });
  });
});
