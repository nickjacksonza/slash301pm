// Review page helpers (GET /jobs/{id}/review). Plain JS, no Datastar Pro, no inline script.
// Previous / Next buttons and the keyboard (j or ArrowDown/ArrowRight: next post;
// k or ArrowUp/ArrowLeft: previous post) move between the post cards that the
// current filter shows, scroll the card under the sticky header, mark it with
// data-current and say "Post N of M" in [data-review-pos]. Keys are ignored
// while typing in a field or when a modifier key is held.
(() => {
  const cards = () => [...document.querySelectorAll('[data-review-card]')].filter((c) => c.offsetParent !== null || c.getClientRects().length > 0);
  let currentId = '';

  const label = () => {
    const pos = document.querySelector('[data-review-pos]');
    if (!pos) return;
    const list = cards();
    const i = list.findIndex((c) => c.id === currentId);
    const text = list.length === 0 ? 'No posts shown' : (i < 0 ? list.length + (list.length === 1 ? ' post shown' : ' posts shown') : 'Showing ' + (i + 1) + ' of ' + list.length);
    // Only on change: writing the text is itself a mutation the observer below sees.
    if (pos.textContent !== text) pos.textContent = text;
  };

  const mark = (card) => {
    document.querySelectorAll('[data-review-card][data-current]').forEach((c) => c.removeAttribute('data-current'));
    if (!card) return;
    card.setAttribute('data-current', '');
    currentId = card.id;
  };

  const go = (step) => {
    const list = cards();
    if (list.length === 0) return;
    let i = list.findIndex((c) => c.id === currentId);
    if (i < 0) {
      // Start from the first card whose top is below the sticky header.
      const header = document.querySelector('#review > .sticky');
      const top = header ? header.getBoundingClientRect().bottom : 0;
      i = list.findIndex((c) => c.getBoundingClientRect().top >= top - 4);
      i = i < 0 ? list.length - 1 : (step > 0 ? i - 1 : i);
    }
    const next = list[Math.max(0, Math.min(list.length - 1, i + step))];
    mark(next);
    next.scrollIntoView({ behavior: 'smooth', block: 'start' });
    next.focus({ preventScroll: true });
    label();
  };

  document.addEventListener('click', (e) => {
    const t = e.target instanceof Element ? e.target : null;
    if (!t) return;
    if (t.closest('[data-review-next]')) go(1);
    else if (t.closest('[data-review-prev]')) go(-1);
    else {
      const card = t.closest('[data-review-card]');
      if (card) {
        mark(card);
        label();
      }
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.altKey || e.ctrlKey || e.metaKey || e.defaultPrevented) return;
    const t = e.target instanceof Element ? e.target : null;
    if (t && (t.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"]'))) return;
    if (!document.querySelector('[data-review-card]')) return;
    if (e.key === 'j' || e.key === 'ArrowDown' || e.key === 'ArrowRight') {
      e.preventDefault();
      go(1);
    } else if (e.key === 'k' || e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
      e.preventDefault();
      go(-1);
    }
  });

  // Cards are re-rendered by Datastar after a decision, and the filter hides or shows them: keep the mark and the label right.
  new MutationObserver(() => {
    const card = currentId ? document.getElementById(currentId) : null;
    if (card && !card.hasAttribute('data-current')) card.setAttribute('data-current', '');
    label();
  }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['style'] });

  label();
})();
