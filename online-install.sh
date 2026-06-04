#!/bin/bash
set -e

# --- Colors -------------------------------------------------------------------
NC='\033[0m'
Red='\033[1;31m'
Yellow='\033[0;33m'
Green='\033[0;32m'

clear

echo -e "${Green}"
echo '  ================================================'
echo '  |                                              |'
echo '  |         OpenVPN-Admin Online Installer       |'
echo '  |      github.com/arvage/OpenVPN-Admin         |'
echo '  |                                              |'
echo '  ================================================'
echo -e "${NC}"

# --- OS Detection -------------------------------------------------------------
. /etc/os-release
OS=$(echo "$ID" | awk '{print toupper(substr($0,1,1)) substr($0,2)}')

# 64-bit Raspberry Pi OS identifies as "debian" - detect by hardware
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

echo -e "${Green}Detected OS: ${Red}$OS${NC}"

# --- Suppress needrestart interactive prompts (Ubuntu 22+ only) ---------------
NEEDRESTART_CONF="/etc/needrestart/needrestart.conf"
if [ -f "$NEEDRESTART_CONF" ]; then
  sed -i "s/#\$nrconf{restart} = 'i';/\$nrconf{restart} = 'a';/g" "$NEEDRESTART_CONF" || true
fi

# --- Update package lists and install git -------------------------------------
echo -e "${Green}Updating package lists...${NC}"
DEBIAN_FRONTEND=noninteractive sudo apt-get update -q

echo -e "${Green}Installing git...${NC}"
DEBIAN_FRONTEND=noninteractive sudo apt-get install -y -q git

# --- Clone the repository -----------------------------------------------------
INSTALL_DIR="$HOME/openvpn-admin"

if [ -d "$INSTALL_DIR" ]; then
  echo -e "${Yellow}Directory $INSTALL_DIR already exists. Pulling latest changes...${NC}"
  git -C "$INSTALL_DIR" pull origin master
else
  echo -e "${Green}Cloning OpenVPN-Admin...${NC}"
  git clone https://github.com/arvage/OpenVPN-Admin "$INSTALL_DIR"
fi

cd "$INSTALL_DIR" || { echo -e "${Red}Failed to enter $INSTALL_DIR${NC}"; exit 1; }

chmod +x ./install.sh

# --- Run installer ------------------------------------------------------------
echo -e "${Green}Starting installation...${NC}"
sudo ./install.sh /var/www www-data www-data
