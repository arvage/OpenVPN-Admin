# OpenVPN-Admin Version History

## 1.1.4

### Fixes
- **Public IP never applied to client `.ovpn` files** — `install.sh` patched the placeholder `remote xxx.xxx.xxx.xxx 1194/443`, but the shipped templates actually use `remote 10.10.100.27 1194`. The `sed` pattern never matched, so every fresh install (including the one-liner installer) shipped `.ovpn` files still pointing at the example IP instead of the detected/entered public IP. The installer now matches the real placeholder.
- **Fail2Ban page always shows the "grant sudo access" warning, even after following it** — on Ubuntu 26.04+, `apache2.service` ships with `InaccessiblePaths=-/etc/sudoers` and `-/etc/sudoers.d`, which hides sudo's config from Apache and everything it spawns (PHP included) as a defense against a compromised web app escalating to root. This silently broke the web UI's `sudo fail2ban-client` / `sudo easyrsa` calls regardless of what was in `/etc/sudoers.d` — adding the suggested sudoers entry had no effect. `install.sh` now drops a systemd override (`apache2.service.d/openvpn-admin-sudo.conf`) that re-declares `InaccessiblePaths` without those two entries, when the packaged unit has them.

### Changes
- **Client Configuration Editor simplified** — the GNU/Linux and macOS/Viscosity tabs are removed from the admin Configs page; only the shared `.ovpn` template remains, under a single "Editor" tab.

---

## 1.1.3

### Fixes
- **All admin buttons missing / Fail2Ban page stuck on spinner** — the `Content-Security-Policy` header (`script-src 'self'`) silently blocked the inline `<script>` block that set `window.ADMIN_ROLE`, `window.CURRENT_PAGE`, and `window.CSRF_TOKEN`. As a result `isSuperAdmin` was always `false` (hiding every edit/delete/reset button on the Users, Admins, and Certificates pages) and `currentPage` defaulted to `'dashboard'` (so `loadFail2Ban()` was never called, leaving the Fail2Ban page frozen on the loading spinner). Fixed by moving the three variables into `js/config.php`, a PHP-served external script that satisfies the `'self'` CSP directive.
- **Sudoers file missing after `update.sh`** — when running an older copy of `update.sh` the script git-pulls itself mid-run, but the already-loaded (old) process does not re-read the new version; the sudoers block introduced in v1.1.0 was therefore never written. `update.sh` now always writes `/etc/sudoers.d/openvpn-admin`.

### Improvements
- **Asset cache busting** — `grids.js` and `index.css` are now loaded with a `?v=<filemtime>` query string so future updates take effect immediately without requiring a hard refresh.

---

## 1.1.2

- **Ban/unban notifications** — banning or unbanning an IP via the Fail2Ban page now creates an in-app notification for super-admins; the bell refreshes immediately after the action
- Two new toggles in Settings → Notifications (Admin Notifications section): **IP Banned** (default on) and **IP Unbanned** (default on); backed by `notify_admin_ban` / `notify_admin_unban` columns (schema-13)
- Bell dropdown shows a red shield (🛡✕) for bans and a green shield (🛡✓) for unbans

---

## 1.1.1

### New Features
- **Admin email editing from the Admins grid** — each row in the Web Admins table now has a pencil button that opens a modal pre-filled with the admin's current email; super-admins can update any other admin's email without needing that admin to log in and use their own Profile button
- **Per-type admin notification toggles** — Settings → Notifications now has a second section ("Admin Notifications") with three toggle switches controlling which user events create in-app bell notifications for super-admins: User Added (default on), User Edited (default off), User Deleted (default on); backed by new `smtp_settings` columns (schema-12)

### Fixes
- **Notifications not appearing after user actions** — `refreshNotifications()` was never called after add/edit/delete user, so the bell badge only updated on the 30-second background poll; now refreshes immediately after each action
- **Bell stuck on "Loading…" when notification table missing** — `?select=notifications` catch block returned `is_super:false` on DB error, causing JS to bail silently; now returns `is_super:true` with a `setup_needed` flag so the dropdown shows an actionable message instead
- **Bell dropdown always fresh on open** — clicking the bell now fetches notifications immediately regardless of when the 30-second poll last ran

### Updater
- **`update.sh` now pulls from git automatically** — running `sudo ./update.sh /var/www` is the only command needed to go from any state to fully up-to-date; a separate `git pull` is no longer required

---

## 1.1.0

### New Features
- **Fail2Ban integration** — new Fail2Ban page in the admin sidebar (super-admin only):
  - Live jail status cards with currently banned count, currently failing count, and lifetime total
  - Per-jail banned IP list with one-click unban (confirm dialog)
  - Manual Ban IP modal: enter any IPv4/IPv6 address and select the target jail
  - Auto-refreshes every 10 seconds; manual refresh button
  - `install.sh` and `update.sh` now install fail2ban, write `/etc/fail2ban/filter.d/openvpn.conf` and `/etc/fail2ban/jail.d/openvpn-admin.conf` (SSH + OpenVPN jails, 5 attempts → 1-hour ban), and extend `/etc/sudoers.d/openvpn-admin` to grant the web server passwordless access to `fail2ban-client`
- **In-app notifications** — bell icon in the topbar for super-admins:
  - Unread badge count; dropdown shows last 50 events with timestamps
  - Notification fired on every user add, edit, and delete (records which admin performed the action)
  - Click bell or "Mark all read" to clear unread state per-admin
  - Polls every 30 seconds; backed by new `notification` database table (schema-11)
- **Admin profile editing** — Profile button in the topbar lets any logged-in admin update their own email address; email pre-fills from the database when the modal opens
- **Dashboard auto-refresh** — user table now also refreshes every 10 seconds while the Users page is open (was manual-only)

### Security Fixes (7 vulnerabilities)
- **Path traversal → RCE** — `update_config` handler now validates `config_file` against an explicit allowlist of 4 permitted paths; arbitrary file writes via `..` sequences are no longer possible
- **Password hashes in API responses** — `user_pass` and `admin_pass` are no longer returned by `?select=user` or `?select=admin`; hashes never leave the server
- **Authorization bypass on certificate download** — `cert_download` handler now calls `requireSuperAdmin()` (was the only sensitive endpoint missing it); read-only admins can no longer download private key material
- **CSRF** — `generateCsrfToken()` / `verifyCsrfToken()` added; token exposed as `window.CSRF_TOKEN`; jQuery `$.ajaxPrefilter` attaches it to every POST; `grids.php` validates it before any state-changing operation
- **Stored XSS — certificate CN** — `listCertificates()` now `htmlspecialchars()` the CN field; JS `loadCertificates()` uses `$('<td>').text()` and `.attr()` instead of string concatenation
- **Stored XSS — user email/phone in HTML attributes** — `userActionsFormatter`, `adminActionsFormatter`, `passFormatter`, and `adminPassFormatter` rebuilt with jQuery DOM construction (`.attr()`, `.append()`); no user data is concatenated into raw HTML strings
- **Privilege escalation — fail-open role** — `getCurrentAdminRole()` now returns `'read-only'` (not `'super-admin'`) when the role field is empty or on any database exception; system fails closed
- **Session cookie hardening** — `session_set_cookie_params()` with `httponly=true`, `samesite=Strict`, and `secure` (auto-detected from HTTPS) added before every `session_start()`

### Installer / Updater
- `install.sh`: fixed `read -t 120` prompt bug — timeout persisted even after user pressed Enter; replaced with plain `read -p` which returns immediately
- `install.sh` / `update.sh`: fail2ban installed, configured, and enabled as part of the standard install/update flow
- `update.sh`: sudoers block now always writes both `easyrsa` and `fail2ban-client` entries unconditionally (was "create only if missing"), so existing installs pick up `fail2ban-client` permission on next update

---

## 1.0.0
- Upgrade UI from Bootstrap 3 to Bootstrap 5 with Bootstrap Icons (CDN-based; removes Bower/npm dependency entirely)
- Replace x-editable inline editing with Bootstrap 5 modal-based row editing
- Add live connection dashboard (polls OpenVPN status log every 10 seconds)
- Add per-user certificate management: generate, revoke, download `.ovpn`, email config
- Add role-based admin access: `super-admin` (full control) and `read-only`
- Add SMTP configuration page with send-test functionality
- Add email notification toggles: on-connect, on-disconnect, account-expiry
- Add `include/mailer.php`: lightweight zero-dependency PHP SMTP client
- Add `include/notify.php`: CLI script called by OpenVPN connect/disconnect hooks
- Add `sql/schema-10.sql`: admin role column, `smtp_settings` table, fix missing admin columns
- Fix `install.sh`: remove PHP 7.4 forcing; use system PHP; fix OpenVPN 2.5+ genkey syntax;
  fix `iptables-save`; fix IP forwarding persistence via `sysctl.d`; fix hardcoded `eth0`;
  detect MySQL vs MariaDB; add sudoers entry for EasyRSA; add 64-bit Raspberry Pi OS detection;
  remove Bower/nodejs/npm; fix `apt-get upgrade` removal; add `systemctl enable`
- Fix `online-install.sh`: remove non-ASCII chars from ASCII art; fix RPi 64-bit detection;
  guard `needrestart` edit; remove `apt-get upgrade`; handle existing clone directory; add `set -e`
- Fix `uninstall.sh`: add colors; fix MySQL socket auth; fix hardcoded `eth0`; fix `iptables-save`;
  fix sysctl cleanup; stop/disable OpenVPN service before file removal; clean up all new artifacts
  (sudoers, log dir, iptables persistence file, Apache conf); fix PHP timezone revert
- Fix `update.sh`: add colors; add pre-update backup; fix `stat` for user/group detection;
  add sudoers and log dir creation for existing installs; patch `server.conf` for OpenVPN 2.5+;
  add Apache reload; add OpenVPN restart when server.conf changes
- Connect and disconnect scripts now call `notify.php` for email alerts

## 0.3.2
- Fix with MySQL NO_ZERO_DATE mode

## 0.3.1
- Fix path issues

## 0.3.0
- Add title to webpage
- Improve design and user experience
- Add redirections (after install...)
- Upgrade to EasyRSA 3.x
- Files are in a subdirectory in the Zip configuration
