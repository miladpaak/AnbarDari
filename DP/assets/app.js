document.addEventListener('DOMContentLoaded', () => {

  document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const sidebar = button.closest('.sidebar');
      if (!sidebar) return;
      const isOpen = sidebar.classList.toggle('nav-open');
      button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });

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

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-barcode-toggle]');
    if (!trigger) {
      if (!event.target.closest('.barcode-card')) {
        document.querySelectorAll('.barcode-card.is-open').forEach((card) => card.classList.remove('is-open'));
      }
      return;
    }
    const card = trigger.closest('.barcode-card');
    if (!card) return;
    document.querySelectorAll('.barcode-card.is-open').forEach((openCard) => {
      if (openCard !== card) openCard.classList.remove('is-open');
    });
    card.classList.toggle('is-open');
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

  document.querySelectorAll('[data-print-svg]').forEach((button) => {
    button.addEventListener('click', () => {
      const svg = document.querySelector(button.dataset.printSvg);
      if (!svg) return;
      const win = window.open('', '_blank', 'width=520,height=420');
      if (!win) return;
      win.document.write('<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>چاپ بارکد</title><style>body{font-family:Tahoma,Arial,sans-serif;text-align:center;padding:24px}svg{max-width:100%;height:auto}</style></head><body>' + svg.outerHTML + '<script>window.onload=function(){window.print();};<\/script></body></html>');
      win.document.close();
    });
  });
});
