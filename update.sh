#!/bin/bash

# --- Colors -------------------------------------------------------------------
NC='\033[0m'
Red='\033[1;31m'
Yellow='\033[0;33m'
Green='\033[0;32m'

# --- Help ---------------------------------------------------------------------
print_help() {
  echo -e "sudo ./update.sh www_basedir"
  echo -e "\twww_basedir: The directory where the web application is (e.g. /var/www)"
}

# --- Root check ---------------------------------------------------------------
if [ "$EUID" -ne 0 ]; then
  echo -e "${Red}Please run as root:${NC}"
  echo -e "  sudo ./update.sh /var/www"
  exit 1
fi

# --- Args check ---------------------------------------------------------------
if [ "$#" -ne 1 ]; then
  print_help
  exit 1
fi

www="$1/openvpn-admin"

if [ ! -d "$www" ]; then
  echo -e "${Red}Directory not found: $www${NC}"
  print_help
  exit 1
fi

# --- Validate config.php exists (needed for user/group and DB credentials) ----
if [ ! -f "$www/include/config.php" ]; then
  echo -e "${Red}config.php not found at $www/include/config.php${NC}"
  echo -e "${Red}Cannot determine file ownership or database credentials. Aborting.${NC}"
  exit 1
fi

base_path=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

# Use stat instead of ls -l for reliable user/group detection
user=$(stat -c '%U' "$www/include/config.php")
group=$(stat -c '%G' "$www/include/config.php")

echo -e "${Green}Updating OpenVPN-Admin...${NC}"
echo -e "  Install path : ${Yellow}$www${NC}"
echo -e "  File owner   : ${Yellow}$user:$group${NC}"
echo ""

# --- Backup current installation ----------------------------------------------
BACKUP="/root/openvpn-admin-backup-$(date +%Y%m%d-%H%M%S).tar.gz"
echo -e "${Green}Creating backup at $BACKUP...${NC}"
tar -czf "$BACKUP" -C "$1" openvpn-admin 2>/dev/null || true
chmod 600 "$BACKUP"

# --- Update web application files ---------------------------------------------
echo -e "${Green}Updating web application files...${NC}"

rm -rf "${www:?}/"{index.php,js,css,include/html,include/connect.php,include/functions.php,include/grids.php,include/mailer.php,include/notify.php}

cp -r "$base_path/"{index.php,js,css} "$www/"
cp -r "$base_path/include/"{html,connect.php,functions.php,grids.php,mailer.php,notify.php} "$www/include/"

# Copy SQL migration files (used by migration.php and first-time web installer)
if [ -d "$base_path/sql" ]; then
  cp -r "$base_path/sql" "$www/"
fi

chown -R "$user:$group" "$www"

# --- Add sudoers entry for EasyRSA if missing (enables web UI cert mgmt) ------
if [ ! -f /etc/sudoers.d/openvpn-admin ]; then
  echo -e "${Green}Adding sudoers entry for EasyRSA...${NC}"
  echo "$user ALL=(ALL) NOPASSWD: /etc/openvpn/easy-rsa/easyrsa" > /etc/sudoers.d/openvpn-admin
  chmod 440 /etc/sudoers.d/openvpn-admin
fi

# --- Create /var/log/openvpn/ if missing (needed for live dashboard) ----------
if [ ! -d /var/log/openvpn ]; then
  echo -e "${Green}Creating OpenVPN log directory...${NC}"
  mkdir -p /var/log/openvpn
  chmod 755 /var/log/openvpn
  touch /var/log/openvpn/openvpn-status.log
  chmod 644 /var/log/openvpn/openvpn-status.log
fi

# --- Patch server.conf for OpenVPN 2.5+ if needed ----------------------------
SERVER_CONF="/etc/openvpn/server.conf"
SERVER_CONF_CHANGED=false

if [ -f "$SERVER_CONF" ] && command -v openvpn &>/dev/null; then
  OPENVPN_VER=$(openvpn --version 2>&1 | head -1 | grep -oE '[0-9]+\.[0-9]+' | head -1)
  OPENVPN_MAJOR=$(echo "$OPENVPN_VER" | cut -d. -f1)
  OPENVPN_MINOR=$(echo "$OPENVPN_VER" | cut -d. -f2)

  if [ "${OPENVPN_MAJOR:-2}" -gt 2 ] || \
     { [ "${OPENVPN_MAJOR:-2}" -eq 2 ] && [ "${OPENVPN_MINOR:-4}" -ge 5 ]; }; then

    # Replace deprecated comp-lzo with compress lz4-v2
    if grep -q '^comp-lzo' "$SERVER_CONF"; then
      echo -e "${Green}Patching server.conf: comp-lzo -> compress lz4-v2${NC}"
      sed -i 's/^comp-lzo$/compress lz4-v2/' "$SERVER_CONF"
      SERVER_CONF_CHANGED=true
    fi

    # Add data-ciphers directive if only old cipher line is present
    if grep -q '^cipher AES-256-CBC' "$SERVER_CONF" && ! grep -q '^data-ciphers' "$SERVER_CONF"; then
      echo -e "${Green}Patching server.conf: adding data-ciphers for OpenVPN 2.5+${NC}"
      sed -i 's/^cipher AES-256-CBC$/data-ciphers AES-256-GCM:AES-128-GCM:AES-256-CBC\ndata-ciphers-fallback AES-256-CBC/' "$SERVER_CONF"
      SERVER_CONF_CHANGED=true
    fi
  fi

  # Update status log to absolute path so www-data can read it (for dashboard)
  if grep -qE '^status openvpn-status\.log' "$SERVER_CONF"; then
    echo -e "${Green}Patching server.conf: updating status log path...${NC}"
    mkdir -p /var/log/openvpn
    chmod 755 /var/log/openvpn
    sed -i 's|^status openvpn-status\.log.*|status /var/log/openvpn/openvpn-status.log 10|' "$SERVER_CONF"
    touch /var/log/openvpn/openvpn-status.log
    chmod 644 /var/log/openvpn/openvpn-status.log
    SERVER_CONF_CHANGED=true
  fi

  # Update old log-append path if still pointing to /var/log/openvpn.log
  if grep -q '^log-append /var/log/openvpn\.log' "$SERVER_CONF"; then
    sed -i 's|^log-append /var/log/openvpn\.log|log-append /var/log/openvpn/openvpn.log|' "$SERVER_CONF"
    SERVER_CONF_CHANGED=true
  fi
fi

# --- Update OpenVPN connect/disconnect scripts --------------------------------
if [ -d /etc/openvpn/scripts ]; then
  echo -e "${Green}Updating OpenVPN scripts...${NC}"
  rm -f /etc/openvpn/scripts/{connect.sh,disconnect.sh,login.sh,functions.sh}
  cp "$base_path/installation/scripts/"{connect.sh,disconnect.sh,login.sh,functions.sh} /etc/openvpn/scripts/
  chmod +x /etc/openvpn/scripts/*.sh
else
  echo -e "${Yellow}Warning: /etc/openvpn/scripts/ not found. Skipping script update.${NC}"
fi

# --- Database migrations ------------------------------------------------------
if command -v php &>/dev/null; then
  echo -e "${Green}Running database migrations...${NC}"
  php "$base_path/migration.php" "$www" && echo -e "${Green}Database migrations done.${NC}"
else
  echo -e "${Yellow}Warning: php not found in PATH. Database migrations were skipped.${NC}"
  echo -e "${Yellow}Run manually: php $base_path/migration.php $www${NC}"
fi

# --- Reload Apache to pick up updated PHP files -------------------------------
echo -e "${Green}Reloading Apache...${NC}"
systemctl reload apache2 2>/dev/null || systemctl restart apache2 2>/dev/null || true

# --- Restart OpenVPN if server.conf was changed -------------------------------
if [ "$SERVER_CONF_CHANGED" = true ]; then
  echo -e "${Green}Restarting OpenVPN (server.conf was patched)...${NC}"
  systemctl restart openvpn@server 2>/dev/null || \
    echo -e "${Yellow}Warning: could not restart openvpn@server. Restart it manually.${NC}"
fi

# --- Summary ------------------------------------------------------------------
echo ""
echo -e "${Green}OpenVPN-Admin updated successfully.${NC}"
echo -e "  Backup : ${Yellow}$BACKUP${NC}"
[ "$SERVER_CONF_CHANGED" = true ] && \
  echo -e "  ${Yellow}server.conf was patched. OpenVPN restarted.${NC}"
echo ""
echo -e "  Access the admin panel to verify: ${Yellow}http://$(hostname -I | awk '{print $1}')/index.php?admin${NC}"
