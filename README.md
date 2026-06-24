# OpenVPN Admin

A web-based administration panel for OpenVPN servers — manage users, certificates, logs, fail2ban bans, and settings through a modern browser interface.

---

## Features

| Feature | Description |
|---|---|
| **Live Dashboard** | Real-time table of connected clients with IP, bandwidth, and session duration. Refreshes every 10 seconds. |
| **User Management** | Add, edit, enable/disable, and delete VPN users with expiry dates |
| **Certificate Management** | Generate and revoke per-user certificates via EasyRSA; download individual `.ovpn` files |
| **Fail2Ban Integration** | View all jails and currently banned IPs, unban with one click, and manually ban any IP from the admin panel |
| **In-app Notifications** | Super-admins receive a bell notification whenever a user is added, edited, or deleted |
| **Connection Logs** | Aggregated session history with data transferred per user |
| **Role-based Admin Access** | `super-admin` (full control) and `read-only` roles for web panel admins |
| **Admin Profile** | Any admin can set their own email address via the Profile button in the topbar |
| **Email Notifications** | SMTP configuration with optional alerts on user connect, disconnect, or account expiry |
| **Client Configs** | Edit and version-history GNU/Linux, Windows, and macOS `.ovpn` templates in-browser |
| **Modern UI** | Bootstrap 5 interface with dark sidebar, stats cards, and responsive layout |

---

## Supported Platforms

| OS | Versions |
|---|---|
| Ubuntu Server | 22.04 LTS, 24.04 LTS |
| Debian | 11 (Bullseye), 12 (Bookworm) |
| Raspberry Pi OS | 32-bit and 64-bit (Bullseye / Bookworm) |

> The installer auto-detects your OS, PHP version, and database engine (MySQL or MariaDB). No manual configuration required.

---

## Installation

### Automatic (one-liner)

```bash
wget -O - https://raw.githubusercontent.com/arvage/OpenVPN-Admin/master/online-install.sh | bash
```

### Manual

```bash
sudo apt update && sudo apt install -y git
git clone https://github.com/arvage/OpenVPN-Admin ~/openvpn-admin
cd ~/openvpn-admin
sudo ./install.sh /var/www www-data www-data
```

### First-time setup

Once the installer finishes, open a browser and go to:

```
http://<your-server-ip>/index.php?installation
```

Create your first admin account there. The page will redirect to the admin panel when done.

The installer automatically sets up:
- OpenVPN server with EasyRSA PKI
- MySQL/MariaDB database
- Apache virtual host with PHP
- Firewall (iptables NAT + forwarding)
- **Fail2ban** with SSH and OpenVPN jails (5 attempts → 1-hour ban)
- Sudoers entries for `easyrsa` and `fail2ban-client` so the web UI can manage both

---

## Admin Panel

After logging in at `http://<your-server-ip>/`, the sidebar gives access to:

### Dashboard
Live table of currently connected VPN clients — username, real IP address, bytes received/sent, and connection time. Refreshes every 10 seconds automatically. Stats cards at the top show total users, online now, disabled accounts, and log entries.

### Users
Full user management: add users with optional email, reset passwords, set start/end dates, enable or disable accounts. Rows with expired end dates are highlighted. Super-admins receive an in-app notification whenever a user is added, edited, or deleted.

### Certificates
Per-user certificate management backed by EasyRSA:
- **Generate** a certificate for a user (runs `easyrsa build-client-full`)
- **Revoke** a certificate (runs `easyrsa revoke` + `gen-crl`)
- **Download** a ready-to-use `.ovpn` file with the user's certificate embedded inline
- **Email** the `.ovpn` file directly to the user (requires SMTP to be configured)

> The installer grants `www-data` passwordless sudo access to `easyrsa` via `/etc/sudoers.d/openvpn-admin`.

### Fail2Ban
Manage fail2ban directly from the admin panel (super-admin only):
- **Live jail status** — see every configured jail with currently banned IP count, currently failing count, and total bans ever
- **Banned IP list** — each jail card lists all currently banned IPs
- **Unban** — remove a ban instantly with a single click (confirm dialog)
- **Manual ban** — enter any IPv4 or IPv6 address and choose which jail to add it to
- Refreshes every 10 seconds automatically; manual refresh button also available

> Requires `www-data` to have passwordless sudo access to `fail2ban-client`. The installer and updater configure this automatically via `/etc/sudoers.d/openvpn-admin`. On existing installs that predate this feature, run `sudo ./update.sh /var/www`.

### Logs
Aggregated connection history per user: total sessions, data transferred, and last seen timestamp. Server-side paginated for large datasets.

### Admins
Manage web panel admin accounts. Each admin has a **role**:
- `super-admin` — full read/write access to all pages
- `read-only` — can view all pages but cannot make changes

Each row has three action buttons:
- ✏ **Edit email** — update that admin's email address
- ↺ **Toggle role** — switch between `super-admin` and `read-only`
- 🗑 **Delete** — remove the admin account (hidden for your own row)

### Profile
Any logged-in admin can update their own email address using the **Profile** button in the topbar. Super-admins can also edit any other admin's email directly from the Admins grid.

### Configs
Edit the raw OpenVPN client configuration templates for GNU/Linux, Windows, and macOS directly in the browser. Changes are saved with a version history so you can review or restore previous configs.

### Settings → SMTP
Configure outgoing email:
- SMTP host, port, username, password
- Security: STARTTLS, SSL/TLS, or none
- Send a test email to verify settings

### Settings → Notifications
Two independent groups of notification toggles:

**Email notifications** (sent to the user's registered address):
- **On Connect** — email when the user connects to VPN
- **On Disconnect** — email when the user disconnects
- **Account Expiry** — email 7 days before the account's end date

**Admin notifications** (in-app bell, super-admins only):
- **User Added** — notify when a new user is created (default on)
- **User Edited** — notify when a user's details are changed (default off)
- **User Deleted** — notify when a user is removed (default on)

> Connect/disconnect email notifications require the OpenVPN server scripts to be in place. The installer sets this up automatically via `/etc/openvpn/scripts/connect.sh` and `disconnect.sh`.

---

## Update

```bash
cd ~/openvpn-admin
sudo ./update.sh /var/www
```

`update.sh` pulls the latest code from git automatically before applying the update, so a single command is all that is needed. It will:
- Back up the current installation to `/root/openvpn-admin-backup-<timestamp>.tar.gz`
- Copy updated web application files
- Apply any pending database migrations automatically
- Install fail2ban if not already present and write jail/filter config
- Update `/etc/sudoers.d/openvpn-admin` to include both `easyrsa` and `fail2ban-client`
- Patch `server.conf` for OpenVPN 2.5+ compatibility if needed
- Add missing infrastructure (log directory) for older installs
- Reload Apache and restart OpenVPN if config changed

---

## Uninstall

Removes all installed components: OpenVPN keys and configuration, the web application, the MySQL/MariaDB database and user, iptables NAT rules, the Apache virtual host, and sysctl forwarding settings.

```bash
sudo ./uninstall.sh /var/www
```

The script will show a full list of what will be deleted and require you to type `yes` to confirm. Installed packages (`openvpn`, `apache2`, `mysql`/`mariadb`, `php`, `fail2ban`) are **not** removed.

---

## Security

The following hardening measures are built into the application:

| Measure | Detail |
|---|---|
| **CSRF protection** | Every state-changing POST requires a per-session token validated server-side |
| **Session cookies** | `HttpOnly`, `SameSite=Strict`, and `Secure` (auto-detected) set on every session |
| **No password hashes in API** | `user_pass` and `admin_pass` are never included in JSON API responses |
| **Role enforcement** | Every write endpoint calls `requireSuperAdmin()` before executing; role lookup fails closed to `read-only` |
| **XSS prevention** | All user data entering the DOM uses jQuery `.text()` / `.attr()` — no string concatenation into HTML |
| **Path traversal prevention** | Config file writes validate against an explicit allowlist of permitted paths |
| **Input validation** | IP addresses validated with `FILTER_VALIDATE_IP`; jail names with `^[a-zA-Z0-9_-]+$`; all shell args use `escapeshellarg()` |
| **Fail2ban** | SSH and OpenVPN jails active by default (5 attempts → 1-hour ban) |

---

## How It Works

```
Browser ──► Apache ──► PHP (index.php / grids.php)
                          │
                          ├── MySQL/MariaDB  (users, admins, logs, notifications, SMTP config)
                          ├── /etc/openvpn/  (server.conf, certs, client configs)
                          ├── /var/log/openvpn/openvpn-status.log  (live dashboard)
                          └── fail2ban-client  (via sudo — jail status, ban, unban)

OpenVPN server ──► connect.sh / disconnect.sh
                          │
                          └── notify.php  (sends email alerts via SMTP)

fail2ban ──► /etc/fail2ban/filter.d/openvpn.conf  (watches openvpn.log)
         └── /etc/fail2ban/jail.d/openvpn-admin.conf  (SSH + OpenVPN jails)
```

Users authenticate to OpenVPN with username + password (no client certificate required by default). The `login.sh` script validates credentials against the database. The certificate management feature generates optional per-user certs for environments that require them.

---

## Libraries Used

All frontend libraries are loaded from CDN — no build tools or package managers required.

| Library | Version | Purpose |
|---|---|---|
| [Bootstrap](https://getbootstrap.com/) | 5.3 | UI framework |
| [Bootstrap Icons](https://icons.getbootstrap.com/) | 1.11 | Icon set |
| [Bootstrap Table](https://bootstrap-table.wenzhixin.net.cn/) | 1.22 | Data grids with pagination and filtering |
| [jQuery](https://jquery.com/) | 3.7 | DOM and AJAX |

---

## Reporting Issues

Please open an issue at [github.com/arvage/OpenVPN-Admin/issues](https://github.com/arvage/OpenVPN-Admin/issues) and include:

- OS and version
- PHP version (`php -v`)
- MySQL/MariaDB version (`mysql --version`)
- fail2ban version (`fail2ban-client --version`)
- The error message or unexpected behaviour
- Relevant Apache/PHP logs (`/var/log/apache2/error.log`)
