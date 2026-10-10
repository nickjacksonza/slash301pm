# Vendored JavaScript

## datastar.js

- Source: https://github.com/starfederation/datastar, tag `v1.0.4`
  (commit `1efcdc3cb336ec3e9139491604e770e0329657bc`), file `bundles/datastar.js`
  (the plain free bundle, not `datastar-rocket.js`).
- Copied unmodified. Its sha256 is pinned in `datastar.js.sha256`;
  `tools/predeploy.php` (Phase 5) and `/admin/system` compare against it.
- License: MIT, Copyright (c) Star Federation.
- Loaded by `app/View/layout.php` as
  `<script type="module" data-cfasync="false" src="...datastar.js?v=<hash>">`.

To upgrade: copy the new bundle, update the sha256 file and this note in the
same commit, then rerun the `/system/spike` page on live.

## theme.js

Ours. Applies the remembered light/dark theme to `<html>` before first paint.

## jobs.js and grid-keys.js

Ours (Phase 3). `jobs.js` mirrors the job grid and board filters into the
address bar (history.replaceState from the patched `data-url`), moves a board
card on screen while its move is posted (`s301Jobs.place`), and closes the
filter menus. `grid-keys.js` adds arrow, Home, End, Enter and F2 movement
between grid cells and puts focus back on the cell after an inline edit.
