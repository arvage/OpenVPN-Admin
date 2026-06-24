#!/bin/bash

# ─── Colors ───────────────────────────────────────────────────────────────────
NC='\033[0m'
Red='\033[1;31m'
Yellow='\033[0;33m'
Green='\033[0;32m'

# ─── OS Detection ─────────────────────────────────────────────────────────────
. /etc/os-release

# Normalize OS name from /etc/os-release ID field
OS=$(echo "$ID" | awk '{print toupper(substr($0,1,1)) substr($0,2)}')
OS_Version_Major=$(echo "${VERSION_ID:-0}" | cut -d. -f1)
OS_Version_Minor=$(echo "${VERSION_ID:-0}" | cut -d. -f2)

# 64-bit Raspberry Pi OS identifies itself as "debian" — detect by hardware
IS_RASPBERRYPI=false
if grep -qiE "Raspberry Pi|BCM2" /proc/cpuinfo 2>/dev/null; then
  IS_RASPBERRYPI=true
  OS="Raspbian"
fi

case "$OS" in
  Ubuntu|Raspbian|Debian) ;;
  *)
    echo -e "${Red}Only Ubuntu, Debian, and Raspberry Pi OS are supported.${NC}"
    exit 1
    ;;
esac

# ─── Variables ────────────────────────────────────────────────────────────────
timezone="America/Los_Angeles"
gmt_offset="-8:00"
www=$1
user=$2
group=$3

openvpn_admin="$www/openvpn-admin"
base_path=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ip_server=$(hostname -I | awk '{print $1}')
public_ip=$(curl -s --max-time 10 https://api.ipify.org || \
            host myip.opendns.com resolver1.opendns.com 2>/dev/null | \
            awk '/myip.opendns.com has address/{print $4; exit}')

openvpn_proto="udp"
server_port="1194"

mysql_root_pass=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 16)
mysql_user=$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c 12)
mysql_pass=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 16)

key_size="2048"
ca_expire="36500"
cert_expire="36500"
cert_country="US"
cert_province="California"
cert_city="Mission Viejo"
cert_org="OpenVPN-Admin"
cert_ou="IT"
cert_email="admin@example.com"

# ─── Help ─────────────────────────────────────────────────────────────────────
print_help() {
  echo -e "sudo ./install.sh www_basedir user group"
  echo -e "\twww_basedir: e.g. /var/www"
  echo -e "\tuser:        web server user, e.g. www-data"
  echo -e "\tgroup:       web server group, e.g. www-data"
}

# ─── Root check ───────────────────────────────────────────────────────────────
if [ "$EUID" -ne 0 ]; then
  echo -e "${Red}Please run as root:${NC}"
  echo -e "${Green}sudo ./install.sh /var/www www-data www-data${NC}"
  exit 1
fi

if [ "$#" -ne 3 ]; then
  print_help
  exit 1
fi

echo -e "${Green}\nAutomated Installation Started\n${NC}"
sleep 1

# ─── Public IP / hostname prompt ──────────────────────────────────────────────
echo -e "${Red}$public_ip${NC} detected as your Public IP."
read -p "Press Enter to accept, or type a different IP/hostname: " public_hostname </dev/tty

if [ -n "$public_hostname" ]; then
  public_ip="$public_hostname"
fi
echo -e "Using: ${Red}$public_ip${NC}"

# ─── VPN connection name ──────────────────────────────────────────────────────
echo -e "\nEnter a VPN connection name shown to users (e.g. 'LA Office')."
read -p "VPN name [VPN]: " company_name </dev/tty

if [ -z "$company_name" ]; then
  company_name="VPN"
fi
echo -e "Using: ${Red}$company_name.ovpn${NC}"

echo -e "${Yellow}\nThis will take a few minutes.\n${NC}"
echo -e "${NC}Detected OS: ${Red}$OS $OS_Version_Major.$OS_Version_Minor${NC}\n"
sleep 1

# ─── Install prerequisites ────────────────────────────────────────────────────
echo -e "${Green}Installing Prerequisites...${NC}"

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

case $OS in
  Ubuntu)
    apt-get update -q
    apt-get install -y -q \
      openvpn apache2 mysql-server \
      php php-mysql php-zip php-mbstring \
      unzip git wget curl net-tools \
      iptables-persistent ca-certificates \
      fail2ban

    # Blacklist floppy only on x86 (not on ARM or VM without floppy)
    if [ "$(uname -m)" != "aarch64" ] && [ "$(uname -m)" != "armv7l" ]; then
      rmmod floppy 2>/dev/null || true
      echo "blacklist floppy" > /etc/modprobe.d/blacklist-floppy.conf
    fi
    ;;

  Raspbian)
    apt-get update -q
    apt-get install -y -q \
      openvpn apache2 mariadb-server \
      php php-mysql php-zip php-mbstring \
      unzip git wget curl net-tools \
      iptables-persistent ca-certificates \
      fail2ban
    ;;

  Debian)
    apt-get update -q
    apt-get install -y -q \
      openvpn apache2 mariadb-server \
      php php-mysql php-zip php-mbstring \
      unzip git wget curl net-tools \
      iptables-persistent ca-certificates \
      fail2ban
    ;;
esac

# ─── Verify required binaries ─────────────────────────────────────────────────
for bin in openvpn apache2 mysql php unzip git wget curl; do
  if ! command -v "$bin" &>/dev/null; then
    echo -e "${Red}$bin is missing. Please install it manually and re-run.${NC}"
    exit 1
  fi
done

# ─── Detect database engine and service name ──────────────────────────────────
if mysql --version 2>&1 | grep -qi mariadb; then
  DB_ENGINE="mariadb"
  DB_SERVICE="mariadb"
else
  DB_ENGINE="mysql"
  DB_SERVICE="mysql"
fi

# ─── Validate arguments ───────────────────────────────────────────────────────
if [ ! -d "$www" ] || ! grep -q "^$user:" /etc/passwd || ! grep -q "^$group:" /etc/group; then
  print_help
  exit 1
fi

# ─── MySQL / MariaDB setup ────────────────────────────────────────────────────
echo -e "${Green}Configuring Database...${NC}"

# Ensure service is running before we touch it
systemctl start "$DB_SERVICE"

if [ "$DB_ENGINE" = "mysql" ]; then
  # MySQL 8.0+ uses caching_sha2_password by default; switch root to native password
  # so password-based auth works for subsequent mysql commands
  mysql -u root <<-EOF
    ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$mysql_root_pass';
    DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
    DELETE FROM mysql.user WHERE User='';
    DELETE FROM mysql.db WHERE Db='test' OR Db='test_%';
    FLUSH PRIVILEGES;
EOF
else
  # MariaDB: unix_socket plugin is default; SET PASSWORD works universally
  mysql -u root <<-EOF
    SET PASSWORD FOR 'root'@'localhost' = PASSWORD('$mysql_root_pass');
    DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
    DELETE FROM mysql.user WHERE User='';
    DELETE FROM mysql.db WHERE Db='test' OR Db='test_%';
    FLUSH PRIVILEGES;
EOF
fi

# Wait for DB to accept password-authenticated connections
for i in $(seq 1 10); do
  mysql -u root --password="$mysql_root_pass" -e "SELECT 1" &>/dev/null && break
  sleep 1
done

# Check / create database
sql_result=$(mysql -u root --password="$mysql_root_pass" -N -e "SHOW DATABASES" 2>/dev/null | grep -x "openvpn-admin" || true)
if [ -n "$sql_result" ]; then
  echo -e "${Red}Database 'openvpn-admin' already exists. Aborting.${NC}"
  exit 1
fi

# Check / create DB user
if mysql -u root --password="$mysql_root_pass" -e "SELECT 1 FROM mysql.user WHERE User='$mysql_user' AND Host='localhost'" 2>/dev/null | grep -q 1; then
  echo -e "${Red}MySQL user '$mysql_user' already exists. Aborting.${NC}"
  exit 1
fi

echo -e "${Green}Creating database and user...${NC}"
mysql -u root --password="$mysql_root_pass" <<-EOF
  CREATE DATABASE \`openvpn-admin\`;
  CREATE USER '$mysql_user'@'localhost' IDENTIFIED BY '$mysql_pass';
  GRANT ALL PRIVILEGES ON \`openvpn-admin\`.* TO '$mysql_user'@'localhost';
  FLUSH PRIVILEGES;
EOF

# Set MySQL timezone (best-effort — may fail if timezone tables not loaded)
mysql -u root --password="$mysql_root_pass" -e "SET GLOBAL time_zone = '$gmt_offset';" 2>/dev/null || true

systemctl restart "$DB_SERVICE"

# ─── EasyRSA ──────────────────────────────────────────────────────────────────
echo -e "${Green}Downloading EasyRSA...${NC}"

EASYRSA_LOCATION=$(curl -s https://api.github.com/repos/OpenVPN/easy-rsa/releases/latest \
  | grep '"browser_download_url"' \
  | grep '\.tgz"' \
  | head -1 \
  | sed 's/.*"browser_download_url": *"\(.*\)"/\1/')

if [ -z "$EASYRSA_LOCATION" ]; then
  # Fallback to known-good release if API fails
  EASYRSA_LOCATION="https://github.com/OpenVPN/easy-rsa/releases/download/v3.1.7/EasyRSA-3.1.7.tgz"
fi

curl -L -o /tmp/easyrsa.tgz "$EASYRSA_LOCATION"
tar -xzf /tmp/easyrsa.tgz -C /tmp/
# Move extracted directory (works regardless of version string in dirname)
rm -rf /etc/openvpn/easy-rsa
mv /tmp/EasyRSA-*/ /etc/openvpn/easy-rsa
rm /tmp/easyrsa.tgz

echo -e "${Green}Generating Certificates and Keys...${NC}"

cd /etc/openvpn/easy-rsa || { echo -e "${Red}Failed to enter EasyRSA directory.${NC}"; exit 1; }

export EASYRSA_BATCH=1
export EASYRSA_KEY_SIZE="$key_size"
export EASYRSA_CA_EXPIRE="$ca_expire"
export EASYRSA_CERT_EXPIRE="$cert_expire"
export EASYRSA_REQ_COUNTRY="$cert_country"
export EASYRSA_REQ_PROVINCE="$cert_province"
export EASYRSA_REQ_CITY="$cert_city"
export EASYRSA_REQ_ORG="$cert_org"
export EASYRSA_REQ_OU="$cert_ou"
export EASYRSA_REQ_EMAIL="$cert_email"

./easyrsa init-pki
./easyrsa build-ca nopass
./easyrsa gen-dh
./easyrsa build-server-full server nopass

# TLS auth key — syntax changed in OpenVPN 2.5
OPENVPN_VER=$(openvpn --version 2>&1 | head -1 | grep -oE '[0-9]+\.[0-9]+' | head -1)
OPENVPN_MAJOR=$(echo "$OPENVPN_VER" | cut -d. -f1)
OPENVPN_MINOR=$(echo "$OPENVPN_VER" | cut -d. -f2)

if [ "${OPENVPN_MAJOR:-2}" -gt 2 ] || { [ "${OPENVPN_MAJOR:-2}" -eq 2 ] && [ "${OPENVPN_MINOR:-4}" -ge 5 ]; }; then
  openvpn --genkey tls-auth pki/ta.key
else
  openvpn --genkey --secret pki/ta.key
fi

# ─── OpenVPN Server Config ────────────────────────────────────────────────────
echo -e "${Green}Configuring OpenVPN Server...${NC}"

cp pki/{ca.crt,ta.key,issued/server.crt,private/server.key,dh.pem} /etc/openvpn/
cp "$base_path/installation/server.conf" /etc/openvpn/
mkdir -p /etc/openvpn/ccd

# Set port and protocol
sed -i "s/port 1194/port $server_port/" /etc/openvpn/server.conf
[ "$openvpn_proto" = "udp" ] && sed -i "s/proto tcp/proto udp/" /etc/openvpn/server.conf

# Fix 'group nogroup' for distros that use 'group nobody'
nobody_group=$(id -ng nobody)
sed -i "s/group nogroup/group $nobody_group/" /etc/openvpn/server.conf

# OpenVPN 2.5+ deprecations: replace comp-lzo and update cipher directive
if [ "${OPENVPN_MAJOR:-2}" -gt 2 ] || { [ "${OPENVPN_MAJOR:-2}" -eq 2 ] && [ "${OPENVPN_MINOR:-4}" -ge 5 ]; }; then
  sed -i 's/^comp-lzo$/compress lz4-v2/' /etc/openvpn/server.conf
  # Replace old cipher line with data-ciphers for 2.5+
  sed -i 's/^cipher AES-256-CBC$/data-ciphers AES-256-GCM:AES-128-GCM:AES-256-CBC\ndata-ciphers-fallback AES-256-CBC/' /etc/openvpn/server.conf
fi

# Status log: write to /var/log/openvpn/ so www-data can read it
mkdir -p /var/log/openvpn
chmod 755 /var/log/openvpn
sed -i 's|^status openvpn-status.log.*|status /var/log/openvpn/openvpn-status.log 10|' /etc/openvpn/server.conf
sed -i 's|^log-append /var/log/openvpn.log.*|log-append /var/log/openvpn/openvpn.log|' /etc/openvpn/server.conf
touch /var/log/openvpn/openvpn-status.log
chmod 644 /var/log/openvpn/openvpn-status.log

# ─── Firewall ─────────────────────────────────────────────────────────────────
echo -e "${Green}Configuring Firewall...${NC}"

# Use 'ip route' (modern) instead of deprecated 'route' from net-tools
primary_nic=$(ip route | awk '/^default/{print $5; exit}')
if [ -z "$primary_nic" ]; then
  primary_nic=$(ip link | awk -F': ' '/^[0-9]+: e/{print $2; exit}')
fi

iptables -I FORWARD -i tun0 -j ACCEPT
iptables -I FORWARD -o tun0 -j ACCEPT
iptables -I OUTPUT  -o tun0 -j ACCEPT
iptables -A FORWARD -i tun0 -o "$primary_nic" -j ACCEPT
iptables -t nat -A POSTROUTING -o "$primary_nic" -j MASQUERADE
iptables -t nat -A POSTROUTING -s 10.8.0.0/24 -o "$primary_nic" -j MASQUERADE

# Persist iptables rules (works for Ubuntu, Debian, Raspbian — iptables-persistent installed above)
mkdir -p /etc/iptables
iptables-save > /etc/iptables/rules.v4

# Persist IP forwarding via sysctl.d (reliable on all distros)
cat > /etc/sysctl.d/99-openvpn.conf <<-EOF
net.ipv4.ip_forward=1
EOF
sysctl -p /etc/sysctl.d/99-openvpn.conf

# ─── OpenVPN Scripts ─────────────────────────────────────────────────────────
echo -e "${Green}Installing OpenVPN Scripts...${NC}"

cp -r "$base_path/installation/scripts" /etc/openvpn/
chmod +x /etc/openvpn/scripts/*

sed -i "s/USER=''/USER='$mysql_user'/" /etc/openvpn/scripts/config.sh
sed -i "s/PASS=''/PASS='$mysql_pass'/" /etc/openvpn/scripts/config.sh

# ─── Web Application ──────────────────────────────────────────────────────────
echo -e "${Green}Installing Web Application...${NC}"

mkdir -p "$openvpn_admin"
cp -r "$base_path/"{index.php,sql,js,include,css,installation/client-conf} "$openvpn_admin"

cd "$openvpn_admin" || exit 1

# Write database credentials into config.php
sed -i "s/\$user = '';/\$user = '$mysql_user';/" ./include/config.php
sed -i "s/\$pass = '';/\$pass = '$mysql_pass';/" ./include/config.php

# Patch client config files with server IP and protocol
for file in $(find . -name "client.ovpn"); do
  sed -i "s/remote xxx\.xxx\.xxx\.xxx 1194/remote $public_ip $server_port/" "$file"
  sed -i "s/remote xxx\.xxx\.xxx\.xxx 443/remote $public_ip $server_port/"  "$file"
  [ "$openvpn_proto" = "udp" ] && sed -i "s/proto tcp-client/proto udp/" "$file"
  # Keep compression in sync with server (comp-lzo is deprecated in OpenVPN 2.5+)
  sed -i 's/^comp-lzo$/compress lz4-v2/' "$file"
  sed -i 's/^compress lzo$/compress lz4-v2/' "$file"
  # Append inline CA and TLS-auth
  echo "<ca>"       >> "$file"; cat /etc/openvpn/ca.crt >> "$file"; echo "</ca>"       >> "$file"
  echo "<tls-auth>" >> "$file"; cat /etc/openvpn/ta.key >> "$file"; echo "</tls-auth>" >> "$file"
done

# Copy shared cert files into client-conf directories
for dir in ./client-conf/gnu-linux/ ./client-conf/osx-viscosity/ ./client-conf/windows/; do
  cp /etc/openvpn/{ca.crt,ta.key} "$dir"
done

# Set download filename
if [ "$company_name" != "VPN" ]; then
  sed -i "s/VPN/$company_name/" "$openvpn_admin/client-conf/windows/filename"
fi
# Strip trailing newline from filename file
truncate -s -1 "$openvpn_admin/client-conf/windows/filename"

chown -R "$user:$group" "$openvpn_admin"

# ─── sudoers: EasyRSA + fail2ban-client (required for web UI) ────────────────
cat > /etc/sudoers.d/openvpn-admin <<-EOF
$user ALL=(ALL) NOPASSWD: /etc/openvpn/easy-rsa/easyrsa
$user ALL=(ALL) NOPASSWD: /usr/bin/fail2ban-client
EOF
chmod 440 /etc/sudoers.d/openvpn-admin

# ─── Apache Configuration ─────────────────────────────────────────────────────
echo -e "${Green}Configuring Apache...${NC}"

# Auto-detect installed PHP version
php_version=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
if [ -z "$php_version" ]; then
  echo -e "${Red}PHP not found after install. Please check your PHP installation.${NC}"
  exit 1
fi

cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/openvpn.conf

# Set document root
sed -i 's|/var/www/html|/var/www/openvpn-admin|g' /etc/apache2/sites-available/openvpn.conf

# Add Directory block before closing VirtualHost tag
sed -i '/<\/VirtualHost>/i \\n\t<Directory \/var\/www\/openvpn-admin>\n\t\tOptions Indexes FollowSymLinks\n\t\tAllowOverride All\n\t\tRequire all granted\n\t<\/Directory>' \
    /etc/apache2/sites-available/openvpn.conf

# Set timezone in PHP ini
php_ini="/etc/php/$php_version/apache2/php.ini"
if [ -f "$php_ini" ]; then
  if grep -q "^;date.timezone" "$php_ini"; then
    sed -i "s|^;date.timezone =.*|date.timezone = $timezone|" "$php_ini"
  elif ! grep -q "^date.timezone" "$php_ini"; then
    echo "date.timezone = $timezone" >> "$php_ini"
  fi
fi

# Disable any other PHP modules that may conflict, then enable the correct one
for mod in $(apache2ctl -M 2>/dev/null | grep -oE 'php[0-9]+_[0-9]+' | tr '_' '.'); do
  [ "$mod" != "php$php_version" ] && a2dismod "$mod" 2>/dev/null || true
done
a2enmod "php$php_version" 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true

a2dissite 000-default
a2ensite openvpn
systemctl restart apache2

# ─── Start OpenVPN ────────────────────────────────────────────────────────────
echo -e "${Green}Starting OpenVPN...${NC}"

systemctl enable openvpn@server
systemctl start  openvpn@server

# ─── Fail2Ban ─────────────────────────────────────────────────────────────────
echo -e "${Green}Configuring Fail2Ban...${NC}"

# Custom filter: catches OpenVPN auth failures and TLS handshake errors in the
# server log. Matches lines like: "1.2.3.4:54321 TLS Auth Error: ..."
cat > /etc/fail2ban/filter.d/openvpn.conf <<-'FILTER'
[Definition]
failregex = <HOST>:\d+ TLS Auth Error:
            <HOST>:\d+ AUTH_FAILED
            <HOST>:\d+ TLS Error: TLS key negotiation failed
ignoreregex =
FILTER

# Jail configuration: SSH (default) + OpenVPN
cat > /etc/fail2ban/jail.d/openvpn-admin.conf <<-JAILCONF
[DEFAULT]
bantime  = 3600
findtime = 600
maxretry = 5
banaction = iptables-multiport

[sshd]
enabled  = true
port     = ssh
logpath  = %(sshd_log)s
backend  = %(sshd_backend)s

[openvpn]
enabled  = true
port     = $server_port
protocol = $openvpn_proto
filter   = openvpn
logpath  = /var/log/openvpn/openvpn.log
maxretry = 5
JAILCONF

systemctl enable fail2ban
systemctl restart fail2ban

# ─── Save credentials ─────────────────────────────────────────────────────────
{
  echo "Auto Generated MySQL Root Password: $mysql_root_pass"
  echo "Auto Generated OpenVPN-Admin MySQL Username: $mysql_user"
  echo "Auto Generated OpenVPN-Admin MySQL Password: $mysql_pass"
} >> ~/OpenVPN_Creds

chmod 600 ~/OpenVPN_Creds

# ─── Summary ──────────────────────────────────────────────────────────────────
echo -e "\n\n${Yellow}################################################################################${NC}"
echo -e "${Green}          OpenVPN-Admin Installation Complete!${NC}"
echo -e "${Yellow}################################################################################${NC}"
echo
echo -e "  Complete setup by visiting:"
echo -e "    Local:  ${Red}http://$ip_server/index.php?installation${NC}"
echo -e "    Public: ${Red}http://$public_ip/index.php?installation${NC}"
echo
echo -e "  After setup, access the admin panel at:"
echo -e "    Local:  ${Red}http://$ip_server/${NC}"
echo -e "    Public: ${Red}http://$public_ip/${NC}"
echo
echo -e "  ${Yellow}Security reminders:${NC}"
echo -e "    - Only forward UDP port $server_port externally (not port 80)"
echo -e "    - Credentials saved to ${Red}~/OpenVPN_Creds${NC} — delete after noting them"
echo -e "    - Fail2Ban is active: SSH + OpenVPN jails (5 attempts → 1h ban)"
echo -e "      Manage bans via the admin panel under ${Red}Fail2Ban${NC} in the sidebar"
echo
echo -e "  Generated credentials:"
echo -e "    MySQL root password:   ${Red}$mysql_root_pass${NC}"
echo -e "    App DB username:       ${Red}$mysql_user${NC}"
echo -e "    App DB password:       ${Red}$mysql_pass${NC}"
echo -e "    Download filename:     ${Red}$company_name.ovpn${NC}"
echo
echo -e "  PHP version: ${Red}$php_version${NC}  |  DB engine: ${Red}$DB_ENGINE${NC}  |  OS: ${Red}$OS $OS_Version_Major.$OS_Version_Minor${NC}"
echo
echo -e "  Report issues: ${Red}https://github.com/arvage/OpenVPN-Admin${NC}"
echo -e "${Yellow}################################################################################${NC}\n"
