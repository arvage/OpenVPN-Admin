#!/bin/bash

# --- Colors -------------------------------------------------------------------
NC='\033[0m'
Red='\033[1;31m'
Yellow='\033[0;33m'
Green='\033[0;32m'
Bold='\033[1m'

# --- Help ---------------------------------------------------------------------
print_help() {
  echo -e "sudo ./uninstall.sh www_basedir"
  echo -e "\twww_basedir: The directory where the web application is (e.g. /var/www)"
}

# --- Root check ---------------------------------------------------------------
if [ "$EUID" -ne 0 ]; then
  echo -e "${Red}Please run as root:${NC}"
  echo -e "  sudo ./uninstall.sh /var/www"
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

# --- Extract MySQL user from config.php ---------------------------------------
mysql_user=$(sed -n "s/.*\\\$user = '\(.*\)';.*/\1/p" "$www/include/config.php" 2>/dev/null | head -1)

if [ -z "$mysql_user" ]; then
  echo -e "${Yellow}Warning: could not read MySQL username from $www/include/config.php${NC}"
  echo -e "${Yellow}Database cleanup will be skipped.${NC}"
fi

# --- Detect primary NIC (same logic as install.sh) ----------------------------
primary_nic=$(ip route 2>/dev/null | awk '/^default/{print $5; exit}')
if [ -z "$primary_nic" ]; then
  primary_nic=$(ip link 2>/dev/null | awk -F': ' '/^[0-9]+: e/{print $2; exit}')
fi

# --- Confirmation prompt -------------------------------------------------------
echo -e ""
echo -e "${Bold}${Red}=== OpenVPN-Admin Uninstaller ===${NC}"
echo -e ""
echo -e "This will permanently remove:"
echo -e "  ${Red}*${NC} OpenVPN service, keys and configuration"
echo -e "  ${Red}*${NC} Web application at ${Yellow}$www${NC}"
[ -n "$mysql_user" ] && \
echo -e "  ${Red}*${NC} MySQL database ${Yellow}openvpn-admin${NC} and user ${Yellow}$mysql_user${NC}"
echo -e "  ${Red}*${NC} Apache virtual host (openvpn)"
echo -e "  ${Red}*${NC} iptables NAT rules (NIC: ${Yellow}${primary_nic:-unknown}${NC})"
echo -e "  ${Red}*${NC} IP forwarding sysctl settings"
echo -e "  ${Red}*${NC} Log directory ${Yellow}/var/log/openvpn/${NC}"
echo -e "  ${Red}*${NC} sudoers entry for easyrsa"
echo -e ""
echo -e "${Bold}Type 'yes' to confirm, anything else to abort:${NC}"
read -r agree </dev/tty

if [ "$agree" != "yes" ]; then
  echo "Aborted."
  exit 0
fi

echo -e ""

# --- Stop and disable OpenVPN service -----------------------------------------
echo -e "${Green}Stopping OpenVPN service...${NC}"
systemctl stop   openvpn@server 2>/dev/null || true
systemctl disable openvpn@server 2>/dev/null || true

# --- MySQL / MariaDB cleanup --------------------------------------------------
if [ -n "$mysql_user" ]; then
  echo -e "${Green}Removing database and user...${NC}"

  # Try socket auth first (default on modern MySQL/MariaDB), then prompt for password
  MYSQL_AUTH=()
  if ! mysql -u root -e "SELECT 1" &>/dev/null; then
    while true; do
      read -p "MySQL root password: " -s mysql_root_pass; echo
      if mysql -u root --password="$mysql_root_pass" -e "SELECT 1" &>/dev/null; then
        MYSQL_AUTH=(--password="$mysql_root_pass")
        break
      fi
      echo -e "${Red}Wrong password, try again.${NC}"
    done
  fi

  mysql -u root "${MYSQL_AUTH[@]}" -e "DROP USER IF EXISTS '$mysql_user'@'localhost';" 2>/dev/null || true
  mysql -u root "${MYSQL_AUTH[@]}" -e "DROP DATABASE IF EXISTS \`openvpn-admin\`;"       2>/dev/null || true
fi

# --- Remove OpenVPN files -----------------------------------------------------
echo -e "${Green}Removing OpenVPN files...${NC}"
rm -rf /etc/openvpn/easy-rsa/
rm -rf /etc/openvpn/ccd/
rm -rf /etc/openvpn/scripts/
rm -f  /etc/openvpn/server.conf
rm -f  /etc/openvpn/ca.crt
rm -f  /etc/openvpn/ta.key
rm -f  /etc/openvpn/server.crt
rm -f  /etc/openvpn/server.key
rm -f  /etc/openvpn/dh*.pem

# --- Remove web application ---------------------------------------------------
echo -e "${Green}Removing web application...${NC}"
rm -rf "$www"

# --- Remove sudoers entry -----------------------------------------------------
rm -f /etc/sudoers.d/openvpn-admin

# --- Remove log directory -----------------------------------------------------
rm -rf /var/log/openvpn/

# --- IP forwarding cleanup ----------------------------------------------------
echo -e "${Green}Disabling IP forwarding...${NC}"
echo 0 > /proc/sys/net/ipv4/ip_forward
# Remove sysctl.d entry added by install.sh
rm -f /etc/sysctl.d/99-openvpn.conf
# Also clean up any old-style entry in sysctl.conf (added by older versions)
sed -i '/net.ipv4.ip_forward/d' /etc/sysctl.conf 2>/dev/null || true
sysctl -p /etc/sysctl.d/*.conf &>/dev/null || true

# --- iptables cleanup ---------------------------------------------------------
echo -e "${Green}Removing iptables rules...${NC}"
iptables -D FORWARD -i tun0 -j ACCEPT 2>/dev/null || true
iptables -D FORWARD -o tun0 -j ACCEPT 2>/dev/null || true
iptables -D OUTPUT  -o tun0 -j ACCEPT 2>/dev/null || true

if [ -n "$primary_nic" ]; then
  iptables -D FORWARD -i tun0 -o "$primary_nic" -j ACCEPT                       2>/dev/null || true
  iptables -t nat -D POSTROUTING -o "$primary_nic" -j MASQUERADE                2>/dev/null || true
  iptables -t nat -D POSTROUTING -s 10.8.0.0/24 -o "$primary_nic" -j MASQUERADE 2>/dev/null || true
  iptables -t nat -D POSTROUTING -s 10.8.0.2/24 -o "$primary_nic" -j MASQUERADE 2>/dev/null || true
else
  echo -e "${Yellow}Warning: could not detect primary NIC. iptables NAT rules may need manual removal.${NC}"
fi

# Persist the cleaned-up ruleset (removes our rules, keeps any others)
if [ -d /etc/iptables ]; then
  iptables-save > /etc/iptables/rules.v4 2>/dev/null || true
fi

# --- Apache cleanup -----------------------------------------------------------
echo -e "${Green}Removing Apache configuration...${NC}"
a2dissite openvpn 2>/dev/null || true
rm -f /etc/apache2/sites-available/openvpn.conf
a2ensite 000-default 2>/dev/null || true
systemctl restart apache2 2>/dev/null || true

# --- PHP timezone cleanup -----------------------------------------------------
php_version=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
if [ -n "$php_version" ]; then
  php_ini="/etc/php/$php_version/apache2/php.ini"
  if [ -f "$php_ini" ]; then
    # Revert date.timezone back to commented-out default
    sed -i "s|^date.timezone = .*|;date.timezone =|" "$php_ini" 2>/dev/null || true
    # Remove old-style line added by earlier versions of this installer
    sed -i "/added by openvpn-admin/d" "$php_ini" 2>/dev/null || true
  fi
fi

# --- Done ---------------------------------------------------------------------
echo -e ""
echo -e "${Green}OpenVPN-Admin has been completely removed.${NC}"
echo -e "${Yellow}Note: packages (openvpn, apache2, mysql/mariadb, php) were not removed."
echo -e "      Remove them manually if no longer needed.${NC}"
