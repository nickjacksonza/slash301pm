// Keyboard movement in the job grid (table[data-grid]), Phase 3.
// Arrows, Home and End move between cells (td[data-cell], roving tabindex);
// Enter or F2 opens the cell's editor (td[data-edit]) or follows its link or
// button; the editor itself handles Enter (save), Escape (cancel) and blur.
// After a save or cancel re-renders the row, focus returns to the same cell.
(() => {
  let last = '';
  const grid = () => document.querySelector('table[data-grid]');
  const rows = () => [...(grid()?.querySelectorAll('tbody tr[data-row]') ?? [])].filter((r) => r.offsetParent !== null);
  const cells = (tr) => [...tr.querySelectorAll(':scope > td[data-cell]')];
  const isCell = (el) => el && el.matches && el.matches('table[data-grid] td[data-cell]') && el.dataset.cell !== 'editing';

  const focusCell = (td) => {
    if (!td) return;
    grid()?.querySelectorAll('td[data-cell][tabindex="0"]').forEach((c) => { c.tabIndex = -1; });
    td.tabIndex = 0;
    td.focus();
    td.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    last = td.id;
  };

  document.addEventListener('keydown', (e) => {
    const td = e.target;
    if (!isCell(td) || e.altKey || e.ctrlKey || e.metaKey) return;
    const tr = td.parentElement;
    const rs = rows();
    const ri = rs.indexOf(tr);
    const cs = cells(tr);
    const ci = cs.indexOf(td);
    let next = null;
    switch (e.key) {
      case 'ArrowRight': next = cs[ci + 1]; break;
      case 'ArrowLeft': next = cs[ci - 1]; break;
      case 'ArrowDown': next = rs[ri + 1] ? cells(rs[ri + 1])[ci] : null; break;
      case 'ArrowUp': next = rs[ri - 1] ? cells(rs[ri - 1])[ci] : null; break;
      case 'Home': next = cs[0]; break;
      case 'End': next = cs[cs.length - 1]; break;
      case 'Enter':
      case 'F2': {
        e.preventDefault();
        if (td.dataset.edit) {
          td.click();
        } else {
          const target = td.querySelector('a, button');
          if (target) target.click();
        }
        return;
      }
      default:
        return;
    }
    if (next) {
      e.preventDefault();
      focusCell(next);
    }
  });

  document.addEventListener('focusin', (e) => {
    if (isCell(e.target)) last = e.target.id;
    else {
      const td = e.target.closest && e.target.closest('table[data-grid] td[data-cell]');
      if (isCell(td)) last = td.id;
    }
  });

  document.addEventListener('datastar-fetch', (e) => {
    if (!e.detail || e.detail.type !== 'finished' || !last) return;
    requestAnimationFrame(() => {
      const a = document.activeElement;
      if (a && a !== document.body && document.contains(a)) return;
      const td = document.getElementById(last);
      if (isCell(td)) focusCell(td);
    });
  });

  const init = () => {
    const g = grid();
    if (!g || g.querySelector('td[data-cell][tabindex="0"]')) return;
    const first = g.querySelector('tbody tr[data-row] td[data-cell]');
    if (first) first.tabIndex = 0;
  };
  new MutationObserver(init).observe(document.body, { childList: true, subtree: true });
  init();
})();
