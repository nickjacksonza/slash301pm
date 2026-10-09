// Applies the remembered theme before first paint and wires [data-theme-toggle]
// buttons. Loaded as a classic blocking script in <head> with data-cfasync="false".
(function () {
  var KEY = 's301-theme';
  var root = document.documentElement;
  function stored() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }
  var t = stored();
  var dark = t === 'dark' || (t === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  if (dark) { root.classList.add('dark'); }
  document.addEventListener('click', function (ev) {
    var target = ev.target instanceof Element ? ev.target.closest('[data-theme-toggle]') : null;
    if (!target) { return; }
    var nowDark = root.classList.toggle('dark');
    try { localStorage.setItem(KEY, nowDark ? 'dark' : 'light'); } catch (e) { /* private mode: not remembered */ }
  });
})();
