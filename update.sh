#!/bin/bash

print_help () {
  echo -e "./update.sh www_basedir"
  echo -e "\tbase_dir: The place where the web application is in"
}

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root"
  exit
fi

if [ "$#" -ne 1 ]; then
  print_help
  exit
fi

www="$1/openvpn-admin"

if [ ! -d "$www" ]; then
  print_help
  exit
fi

base_path=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

user=$(ls -l "$www/include/config.php" | awk '{ print $3 }')
group=$(ls -l "$www/include/config.php" | awk '{ print $4 }')

# Remove old files (vendor still served from CDN now)
rm -rf "${www:?}/"{index.php,js,include/html,include/connect.php,include/functions.php,include/grids.php,include/mailer.php,include/notify.php,css}

# Copy new files
cp -r "$base_path/"{index.php,js,css} "$www"
cp -r "$base_path/include/"{html,connect.php,functions.php,grids.php,mailer.php,notify.php} "$www/include"

# Copy new SQL migrations
if [ -d "$base_path/sql" ]; then
  cp -r "$base_path/sql" "$www/"
fi

chown -R "$user:$group" "$www"

# Update OpenVPN scripts
rm -f "/etc/openvpn/scripts/"{connect.sh,disconnect.sh,login.sh,functions.sh}
cp "$base_path/installation/scripts/"{connect.sh,disconnect.sh,login.sh,functions.sh} "/etc/openvpn/scripts"
chmod +x "/etc/openvpn/scripts/"{connect.sh,disconnect.sh,login.sh,functions.sh}

echo "Processing database migrations..."
php "$base_path/migration.php" "$www"
echo "Database migrations done."

echo "OpenVPN-admin upgraded."
