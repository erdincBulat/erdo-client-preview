<p align="center">
  <img src="https://ps.w.org/erdo-client-preview/assets/banner-1544x500.png" alt="Erdo Client Preview" width="100%">
</p>

<h1 align="center">Erdo Client Preview</h1>

<p align="center">
  More than a coming soon page — give clients a private magic link to preview the live site, then collect feedback and on-page annotations.
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/erdo-client-preview/"><img src="https://img.shields.io/wordpress/plugin/v/erdo-client-preview.svg" alt="WordPress Plugin Version"></a>
  <a href="https://wordpress.org/plugins/erdo-client-preview/"><img src="https://img.shields.io/wordpress/plugin/dt/erdo-client-preview.svg" alt="WordPress Plugin Downloads"></a>
  <a href="https://wordpress.org/plugins/erdo-client-preview/"><img src="https://img.shields.io/wordpress/plugin/rating/erdo-client-preview.svg" alt="WordPress Plugin Rating"></a>
  <img src="https://img.shields.io/wordpress/v/erdo-client-preview.svg" alt="Tested up to WordPress version">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg" alt="Requires PHP 7.4+">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPLv2%2B-blue.svg" alt="License: GPLv2+"></a>
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/erdo-client-preview/">WordPress.org Listing</a> ·
  <a href="https://wordpress.org/plugins/erdo-client-preview/#developers">Changelog</a> ·
  <a href="#installation">Installation</a> ·
  <a href="https://github.com/erdincBulat/erdo-client-preview/issues">Report a Bug</a>
</p>

---

Most "coming soon" plugins stop at hiding your site behind a countdown timer. **Erdo Client Preview** adds the three things agencies and freelancers actually need when working with clients on a live site:

- **Magic links** — a private, password-free URL per person that bypasses the gate
- **Visitor feedback** — clients leave messages directly on the page they're viewing, no email back-and-forth
- **Live annotations** — clients click any element on the site and pin an exact note about it, like a built-in design-review tool

Everyone without a link sees your custom coming soon / under construction page. Everyone with one browses the real, live site — and can tell you exactly what they think while they're there.

## Screenshots

| Magic links | Live annotations |
|---|---|
| ![Magic links panel](https://ps.w.org/erdo-client-preview/assets/screenshot-1.png) | ![Visual annotation mode](https://ps.w.org/erdo-client-preview/assets/screenshot-2.png) |

| Feedback tab | Settings panel |
|---|---|
| ![Feedback tab](https://ps.w.org/erdo-client-preview/assets/screenshot-3.png) | ![Settings panel](https://ps.w.org/erdo-client-preview/assets/screenshot-4.png) |

<p align="center"><img src="https://ps.w.org/erdo-client-preview/assets/screenshot-5.png" alt="Visitor-facing coming soon page" width="70%"></p>

## Features

**Magic Links — per-person access, not a shared password**
- Label each link ("John - Client", "Anna - Designer") with individual expiry (24h, 48h, 7d, 30d, or never)
- Per-link redirect URL, revoke a single link without affecting others
- Usage counter, access history, and optional email notification on use
- 32-character cryptographic tokens stored only as HMAC-SHA256 hashes — the raw token is never saved, and the bypass cookie is signed, `HttpOnly`, and verified with `hash_equals()`

**Visitor Feedback — messages straight from the page being reviewed**
- No-account-required widget on the coming soon page and on the live site for magic-link visitors
- Reply from the admin, visitor sees the reply automatically; "Past Feedback" history; email alerts; bulk actions

**Live Annotations — pinpoint feedback on the exact element**
- Magic-link visitors click any element on the live site and pin a note directly on it
- Admins see a persistent numbered pin on every page they visit, reply inline, track status, bulk actions

**Two Modes**
- *Maintenance* (HTTP 503 + dynamic `Retry-After`) for deployments and migrations
- *Coming Soon* (HTTP 200, indexable) for new launches, with a built-in email subscription form

**Scheduled Activation** — one-time window or recurring weekly schedule, cron-driven with a real-time fallback so the site is never accidentally left open or stuck gated.

**Access Control** — IP whitelist, admin bypass, page/post-type exclusions, and automatic bypass for XML-RPC, REST, WP-Cron, WP-CLI, and `wp-login.php`.

**White Label** — replace the plugin's name/logo with your agency's on the settings page and admin menu.

**Emergency Rescue URL** — a secret link that disables maintenance instantly, no login required.

## Who is this for?

- **Web agencies and freelancers** — gate a client site during updates, share a private link so the client can review progress and leave feedback/annotations, revoke the moment the project ships
- **Site owners launching a new project** — collect subscribers in Coming Soon mode, no third-party service needed
- **Developers and DevOps** — schedule maintenance windows for migrations and deployments without manual intervention

## Installation

1. Upload the `erdo-client-preview` folder to `/wp-content/plugins/`, or install directly from **Plugins → Add New** by searching "Erdo Client Preview"
2. Activate through the **Plugins** menu
3. Go to **Client Preview** in the admin menu
4. Choose Maintenance or Coming Soon mode and enable
5. Generate a magic link and share it with your client

Full FAQ and changelog are on the [WordPress.org plugin page](https://wordpress.org/plugins/erdo-client-preview/).

## Try it instantly (no install)

[![Try it in WordPress Playground](https://img.shields.io/badge/WordPress%20Playground-Try%20it%20live-3858e9.svg)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/erdincBulat/erdo-client-preview/master/.wordpress-org/blueprint.json)

Runs entirely in your browser, no server or account needed.

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](.github/CONTRIBUTING.md). This repository mirrors the plugin shipped on WordPress.org; releases are cut from the `Stable tag` in `readme.txt`.

## Security

This plugin never stores raw magic-link tokens (HMAC-SHA256 hashes only), signs the bypass cookie, and uses `hash_equals()` for constant-time comparison throughout. If you find a security issue, please open an issue or contact the author directly rather than disclosing it publicly.

## License

GPLv2 or later — see [LICENSE](LICENSE).
