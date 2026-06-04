# OpenVPN-Admin Version History

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
