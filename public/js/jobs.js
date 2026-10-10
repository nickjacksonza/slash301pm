// Job grid and board helpers (Phase 3). Plain JS, no Datastar Pro.
// 1. Keeps the address bar in step with the filters: GET /jobs/rows and
//    /jobs/board/columns re-render #jobs-body / #board-columns with a data-url,
//    which is mirrored into history.replaceState (the initial page URL is kept).
// 2. s301Jobs.place(jobId, stage): the optimistic board move (the server answer
//    re-renders both columns from the database, so a refused move snaps back).
// 3. Closes the filter <details> menus on an outside click or Escape.
(() => {
  const sync = () => {
    const el = document.querySelector('#jobs-body[data-url], #board-columns[data-url]');
    const url = el && el.getAttribute('data-url');
    if (url && url !== location.pathname + location.search) {
      history.replaceState(history.state, '', url);
    }
  };
  new MutationObserver((records) => {
    for (const r of records) {
      const t = r.target;
      if (r.type === 'attributes' || (t.id === 'jobs-body' || t.id === 'board-columns') || [...r.addedNodes].some((n) => n.id === 'jobs-body' || n.id === 'board-columns')) {
        sync();
        return;
      }
    }
  }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['data-url'] });

  window.s301Jobs = {
    place(jobId, stage) {
      const card = document.getElementById('card-' + jobId);
      const target = document.querySelector('[data-cards="' + CSS.escape(String(stage)) + '"]');
      if (!card || !target) return;
      const empty = target.querySelector('[data-empty]');
      if (empty) empty.remove();
      target.prepend(card);
      card.dataset.stage = stage;
    },
  };

  const closeMenus = (except) => {
    document.querySelectorAll('details[data-filter-menu][open]').forEach((d) => {
      if (!except || !d.contains(except)) d.open = false;
    });
  };
  document.addEventListener('click', (e) => closeMenus(e.target));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenus(null);
  });
})();
