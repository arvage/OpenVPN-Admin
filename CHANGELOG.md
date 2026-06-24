# OpenVPN-Admin Version History

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
