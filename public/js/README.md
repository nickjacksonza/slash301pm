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
