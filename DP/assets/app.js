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


  const initSearchableSelect = (root = document) => {
    root.querySelectorAll('select[data-searchable-select]').forEach((select) => {
      if (select.dataset.searchReady === '1') return;
      select.dataset.searchReady = '1';
      const search = document.createElement('input');
      search.type = 'search';
      search.className = 'select-search';
      search.placeholder = select.dataset.searchPlaceholder || 'جستجوی نام یا کد کالا...';
      search.autocomplete = 'off';
      const allOptions = [...select.options].map((option) => ({
        value: option.value,
        text: option.textContent,
        dataset: {...option.dataset},
        disabled: option.disabled,
      }));
      select.parentNode.insertBefore(search, select);
      const renderOptions = (term = '') => {
        const current = select.value;
        const normalized = term.trim().toLowerCase();
        select.innerHTML = '';
        allOptions.forEach((data, index) => {
          if (index > 0 && normalized && !data.text.toLowerCase().includes(normalized)) return;
          const option = document.createElement('option');
          option.value = data.value;
          option.textContent = data.text;
          option.disabled = data.disabled;
          Object.entries(data.dataset).forEach(([key, value]) => { option.dataset[key] = value; });
          select.appendChild(option);
        });
        if ([...select.options].some((option) => option.value === current)) {
          select.value = current;
        }
      };
      search.addEventListener('input', () => renderOptions(search.value));
    });
  };

  const applyInvoiceDefaults = (select) => {
    const form = select.closest('[data-invoice-form]');
    if (!form) return;
    const row = select.closest('[data-invoice-row]');
    const option = select.selectedOptions[0];
    if (!row || !option) return;
    const unitPrice = row.querySelector('[data-unit-price]');
    const costPrice = row.querySelector('[data-cost-price]');
    if (form.dataset.invoiceType === 'sale') {
      if (unitPrice) unitPrice.value = option.dataset.salePrice || '';
      if (costPrice) costPrice.value = option.dataset.costPrice || '';
    }
  };

  const reindexInvoiceRows = (form) => {
    form.querySelectorAll('[data-invoice-row]').forEach((row, index) => {
      row.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
      });
    });
  };

  initSearchableSelect();

  document.addEventListener('change', (event) => {
    const itemSelect = event.target.closest('[data-invoice-item]');
    if (itemSelect) applyInvoiceDefaults(itemSelect);
  });

  document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-invoice-row]');
    if (addButton) {
      const form = addButton.closest('[data-invoice-form]');
      const rows = form.querySelector('[data-invoice-rows]');
      const template = form.querySelector('[data-invoice-row-template]');
      const index = rows.querySelectorAll('[data-invoice-row]').length;
      rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
      const newRow = rows.querySelector('[data-invoice-row]:last-child');
      initSearchableSelect(newRow);
      return;
    }
    const removeButton = event.target.closest('[data-remove-invoice-row]');
    if (removeButton) {
      const form = removeButton.closest('[data-invoice-form]');
      const rows = form.querySelectorAll('[data-invoice-row]');
      if (rows.length > 1) {
        removeButton.closest('[data-invoice-row]').remove();
        reindexInvoiceRows(form);
      }
    }
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

document.querySelectorAll('[data-issue-form]').forEach((form) => {
  const body = form.querySelector('.issue-items-table tbody');
  const template = document.getElementById('issue-item-template');
  let index = body.querySelectorAll('tr').length;
  form.querySelector('[data-add-issue-item]')?.addEventListener('click', () => {
    body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index++)));
  });
  body.addEventListener('click', (event) => {
    if (event.target.closest('[data-remove-issue-item]') && body.querySelectorAll('tr').length > 1) event.target.closest('tr').remove();
  });
});

document.querySelectorAll('[data-reject-issue]').forEach((button) => {
  button.addEventListener('click', (event) => {
    const notes = button.form.querySelector('[data-review-notes]');
    if (!notes.value.trim()) {
      event.preventDefault();
      alert('برای رد حواله، توضیحات را وارد کنید.');
      notes.focus();
    }
  });
});

(() => {
  const triggers = document.querySelectorAll('[data-lightbox-image]');
  if (!triggers.length) return;
  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.hidden = true;
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.setAttribute('aria-label', 'نمایش بزرگ تصویر کالا');
  const image = document.createElement('img');
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'lightbox-close';
  close.setAttribute('aria-label', 'بستن تصویر');
  close.textContent = '×';
  lightbox.append(close, image);
  document.body.appendChild(lightbox);
  const closeLightbox = () => { lightbox.hidden = true; image.removeAttribute('src'); };
  triggers.forEach((trigger) => trigger.addEventListener('click', () => {
    image.src = trigger.dataset.lightboxImage;
    image.alt = trigger.dataset.lightboxAlt || 'تصویر کالا';
    lightbox.hidden = false;
    close.focus();
  }));
  close.addEventListener('click', closeLightbox);
  lightbox.addEventListener('click', (event) => { if (event.target === lightbox) closeLightbox(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !lightbox.hidden) closeLightbox(); });
})();
