'use strict';
(() => {
  const normalize = s => String(s).replace(/[٠-٩]/g, c => String(c.charCodeAt(0) - 1632)).replace(/[۰-۹]/g, c => String(c.charCodeAt(0) - 1776)).replace(/٫/g, '.');
  const scaled = (s, precision) => {
    const v = normalize(s).trim();
    if (!new RegExp('^\\d{1,12}(?:\\.\\d{1,' + precision + '})?$').test(v)) return null;
    const [w, f = ''] = v.split('.');
    return BigInt(w) * 10n ** BigInt(precision) + BigInt(f.padEnd(precision, '0'));
  };
  const rounded = (n, d) => (n + d / 2n) / d;
  const quote = ({ grams, price, ownTransport, local, localRate, otherRate, minimum, bp, flat }) => {
    const subtotal = rounded(grams * price, 1000000n);
    const transport = ownTransport ? 0n : (() => { const v = rounded(grams * (local ? localRate : otherRate), 1000000n); return v > minimum ? v : minimum; })();
    const fee = rounded(subtotal * bp, 10000n) + flat;
    return { subtotal, transport, fee, total: subtotal + transport + fee };
  };
  const decimal = p => (p / 100n).toString() + '.' + (p % 100n).toString().padStart(2, '0');
  if (typeof module !== 'undefined') module.exports = { scaled, rounded, quote, decimal };
  if (typeof document === 'undefined') return;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const menu = $('.menu-toggle'), nav = $('#mobile-nav');
  if (menu && nav) menu.addEventListener('click', () => { nav.hidden = !nav.hidden; menu.setAttribute('aria-expanded', String(!nav.hidden)); });
  if ('IntersectionObserver' in window && !reduced) {
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); } }), { threshold: .12 });
    $$('.reveal').forEach(e => { e.classList.add('js-reveal'); io.observe(e); });
  }
  $$('[data-step-form]').forEach(form => {
    const steps = $$('[data-form-step]', form); let active = 0;
    const show = index => {
      active = index;
      steps.forEach((s, i) => { s.hidden = i !== index; s.disabled = i !== index; });
      $$('[data-progress]', form).forEach((p, i) => p.classList.toggle('active', i === index));
      if (index > 0) $('input:not([type=hidden]),select,textarea', steps[index])?.focus({ preventScroll: true });
    };
    $('[data-next-step]', form)?.addEventListener('click', () => {
      const fields = $$('input,select,textarea', steps[0]);
      for (const field of fields) if (!field.reportValidity()) return;
      show(1);
    });
    $('[data-prev-step]', form)?.addEventListener('click', () => show(0));
    form.addEventListener('submit', event => {
      // Re-enable the earlier step so its controls are submitted to PHP.
      steps.forEach(s => { s.disabled = false; });
      if (!form.checkValidity()) {
        event.preventDefault();
        const invalid = $$('input,select,textarea', form).find(e => !e.validity.valid);
        const index = steps.findIndex(s => s.contains(invalid));
        show(Math.max(0, index)); invalid?.reportValidity();
      }
    });
    $('[data-next-step]', form)?.removeAttribute('hidden');
    show(0);
  });
  $$('[data-quote-form]').forEach(form => {
    const value = name => $('[name="' + name + '"]', form).value;
    const update = () => {
      $('[name=quantity]', form).max = value('unit') === 'kg' ? '1000000' : '1000';
      const quantity = scaled(value('quantity'), 3);
      const grams = quantity === null ? null : quantity * (value('unit') === 'tonne' ? 1000n : 1n);
      const invalid = grams === null || grams < 1000n || grams > BigInt(form.dataset.available) || grams > 1000000000n;
      $('[data-quantity-warning]', form).hidden = !invalid;
      $('[data-order-submit]', form).disabled = invalid;
      if (invalid) { $('[name=expected_total]', form).value = ''; return; }
      const q = quote({ grams, price: BigInt(form.dataset.price), ownTransport: value('shipping_mode') === 'buyer', local: value('governorate') === form.dataset.origin, localRate: BigInt(form.dataset.localRate), otherRate: BigInt(form.dataset.otherRate), minimum: BigInt(form.dataset.shippingMin), bp: BigInt(form.dataset.feeBp), flat: BigInt(form.dataset.feeFlat) });
      const display = v => Number(decimal(v)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + form.dataset.currency;
      $('[data-quote-subtotal]', form).textContent = display(q.subtotal);
      $('[data-quote-shipping]', form).textContent = display(q.transport);
      $('[data-quote-fee]', form).textContent = display(q.fee);
      $('[data-quote-total]', form).textContent = display(q.total);
      $('[name=expected_total]', form).value = decimal(q.total);
    };
    $$('[name=quantity],[name=unit],[name=governorate],[name=shipping_mode]', form).forEach(el => { el.addEventListener('input', update); el.addEventListener('change', update); });
    update();
  });
  $$('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
  let toastTimer;
  const toast = message => { const box = $('.toast'); if (!box) return; box.textContent = message; box.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => { box.hidden = true; }, 2500); };
  $$('[data-copy]').forEach(button => button.addEventListener('click', async () => {
    try {
      if (navigator.clipboard) await navigator.clipboard.writeText(button.dataset.copy);
      else { const area = document.createElement('textarea'); area.value = button.dataset.copy; document.body.append(area); area.select(); const ok = document.execCommand('copy'); area.remove(); if (!ok) return; }
      toast(button.dataset.copyLabel);
    } catch { button.focus(); }
  }));
  const dialog = $('#story-dialog');
  if (dialog) {
    let stage = 0, timer, playing = false;
    const paint = () => { $$('[data-story-slide]', dialog).forEach((s, i) => { s.hidden = i !== stage; }); $$('[data-story-index]', dialog).forEach((d, i) => d.setAttribute('aria-pressed', String(i === stage))); };
    const toggle = on => {
      playing = on; dialog.classList.toggle('is-playing', on);
      const button = $('[data-story-toggle]', dialog); button.textContent = on ? button.dataset.pauseLabel : button.dataset.playLabel;
      clearInterval(timer);
      if (on) timer = setInterval(() => { if (stage < 3) { stage++; paint(); } else { toggle(false); } }, 7500);
    };
    $$('[data-open-story]').forEach(button => button.addEventListener('click', () => { stage = 0; paint(); dialog.showModal(); toggle(!reduced); }));
    $('[data-close-story]', dialog)?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => toggle(false));
    dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
    $('[data-story-toggle]', dialog)?.addEventListener('click', () => { if (!playing && stage === 3) { stage = 0; paint(); } toggle(!playing); });
    $$('[data-story-index]', dialog).forEach(button => button.addEventListener('click', () => { stage = Number(button.dataset.storyIndex); paint(); toggle(false); }));
  }
  // Prevent accidental double submissions; PHP additionally locks and checks state transitions.
  $$('form[method=post]').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented || !form.checkValidity()) return;
    $$('button[type=submit],button:not([type])', form).forEach(b => { b.disabled = true; b.setAttribute('aria-busy', 'true'); });
  }));
})();
