# OpenVPN Admin

A web-based administration panel for OpenVPN servers — manage users, certificates, logs, and settings through a modern browser interface.

---

## Features

| Feature | Description |
|---|---|
| **Live Dashboard** | Real-time table of connected clients with IP, bandwidth, and session duration |
| **User Management** | Add, edit, enable/disable, and delete VPN users with expiry dates |
| **Certificate Management** | Generate and revoke per-user certificates via EasyRSA; download individual `.ovpn` files |
| **Connection Logs** | Aggregated session history with data transferred per user |
| **Role-based Admin Access** | `super-admin` (full control) and `read-only` roles for web panel admins |
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

---

## Admin Panel

After logging in at `http://<your-server-ip>/`, the sidebar gives access to:

### Dashboard
Live table of currently connected VPN clients — username, real IP address, bytes received/sent, and connection time. Refreshes every 10 seconds automatically.

### Users
Full user management: add users, reset passwords, set start/end dates, enable or disable accounts. Rows with expired end dates are highlighted automatically.

### Certificates
Per-user certificate management backed by EasyRSA:
- **Generate** a certificate for a user (runs `easyrsa build-client-full`)
- **Revoke** a certificate (runs `easyrsa revoke` + `gen-crl`)
- **Download** a ready-to-use `.ovpn` file with the user's certificate embedded inline
- **Email** the `.ovpn` file directly to the user (requires SMTP to be configured)

> The installer automatically grants `www-data` passwordless sudo access to `easyrsa` via `/etc/sudoers.d/openvpn-admin`.

### Logs
Aggregated connection history per user: total sessions, data transferred, and last seen timestamp. Server-side paginated for large datasets.

### Admins
Manage web panel admin accounts. Each admin has a **role**:
- `super-admin` — full read/write access to all pages
- `read-only` — can view all pages but cannot make changes

### Configs
Edit the raw OpenVPN client configuration templates for GNU/Linux, Windows, and macOS directly in the browser. Changes are saved with a version history so you can review or restore previous configs.

### Settings → SMTP
Configure outgoing email:
- SMTP host, port, username, password
- Security: STARTTLS, SSL/TLS, or none
- Send a test email to verify settings

### Settings → Notifications
Toggle email alerts sent to the user's registered address:
- **On Connect** — email when the user connects to VPN
- **On Disconnect** — email when the user disconnects
- **Account Expiry** — email 7 days before the account's end date

> Connect/disconnect notifications require the OpenVPN server scripts to be in place. The installer sets this up automatically via `/etc/openvpn/scripts/connect.sh` and `disconnect.sh`.

---

## Update

```bash
cd ~/openvpn-admin
git pull origin master
sudo ./update.sh /var/www
```

`update.sh` will:
- Back up the current installation to `/root/openvpn-admin-backup-<timestamp>.tar.gz`
- Copy updated web application files
- Apply any pending database migrations automatically
- Patch `server.conf` for OpenVPN 2.5+ compatibility if needed
- Add missing infrastructure (sudoers entry, log directory) for existing installs
- Reload Apache and restart OpenVPN if config changed

---

## Uninstall

Removes all installed components: OpenVPN keys and configuration, the web application, the MySQL/MariaDB database and user, iptables NAT rules, the Apache virtual host, and sysctl forwarding settings.

```bash
sudo ./uninstall.sh /var/www
```

The script will show a full list of what will be deleted and require you to type `yes` to confirm. Installed packages (`openvpn`, `apache2`, `mysql`/`mariadb`, `php`) are **not** removed.

---

## How It Works

```
Browser ──► Apache ──► PHP (index.php / grids.php)
                          │
                          ├── MySQL/MariaDB  (users, admins, logs, SMTP config)
                          ├── /etc/openvpn/  (server.conf, certs, client configs)
                          └── /var/log/openvpn/openvpn-status.log  (live dashboard)

OpenVPN server ──► connect.sh / disconnect.sh
                          │
                          └── notify.php  (sends email alerts via SMTP)
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
- The error message or unexpected behaviour
- Relevant Apache/PHP logs (`/var/log/apache2/error.log`)
