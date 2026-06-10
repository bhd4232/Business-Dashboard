#!/bin/bash
# =============================================================
# ZamZam ERP - Server Initial Setup Script
# Ubuntu 20.04/22.04/24.04 এর জন্য
# Run as root: sudo bash server-setup.sh
# =============================================================

set -e

echo "============================================"
echo "  ZamZam ERP Server Setup Starting..."
echo "============================================"

# Variables - পরিবর্তন করুন
DOMAIN="yourdomain.com"           # আপনার ডোমেইন
DB_NAME="zamzam_erp"
DB_USER="zamzam_user"
DB_PASS="YourStrongPassword123!"  # শক্তিশালী পাসওয়ার্ড দিন
DEPLOY_USER="deploy"              # deployment user
PROJECT_DIR="/var/www/zamzam-erp"

# ============================================
# Step 1: System Update
# ============================================
echo "[1/8] Updating system packages..."
apt update && apt upgrade -y

# ============================================
# Step 2: Install Required Software
# ============================================
echo "[2/8] Installing Nginx, PHP 8.3, MySQL..."

# Nginx
apt install -y nginx

# PHP 8.3
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-mysql \
    php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
    php8.3-redis php8.3-imagick unzip git

# MySQL
apt install -y mysql-server

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo "  ✓ Software installed"

# ============================================
# Step 3: Configure MySQL
# ============================================
echo "[3/8] Setting up MySQL database..."

mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo "  ✓ Database created: ${DB_NAME}"

# ============================================
# Step 4: Create Deploy User
# ============================================
echo "[4/8] Creating deploy user..."

useradd -m -s /bin/bash ${DEPLOY_USER} 2>/dev/null || echo "  User already exists"
usermod -aG www-data ${DEPLOY_USER}

# SSH key setup for GitHub Actions
mkdir -p /home/${DEPLOY_USER}/.ssh
chmod 700 /home/${DEPLOY_USER}/.ssh

# Generate SSH keypair for deployment
ssh-keygen -t ed25519 -C "zamzam-erp-deploy" -f /home/${DEPLOY_USER}/.ssh/id_ed25519 -N ""
cat /home/${DEPLOY_USER}/.ssh/id_ed25519.pub >> /home/${DEPLOY_USER}/.ssh/authorized_keys
chmod 600 /home/${DEPLOY_USER}/.ssh/authorized_keys
chown -R ${DEPLOY_USER}:${DEPLOY_USER} /home/${DEPLOY_USER}/.ssh

echo "  ✓ Deploy user created"
echo ""
echo "  *** IMPORTANT: Copy this PRIVATE KEY to GitHub Secrets as SERVER_SSH_KEY ***"
echo "  ==========================================="
cat /home/${DEPLOY_USER}/.ssh/id_ed25519
echo "  ==========================================="

# ============================================
# Step 5: Clone Repository
# ============================================
echo "[5/8] Setting up project directory..."

mkdir -p ${PROJECT_DIR}
chown -R ${DEPLOY_USER}:www-data ${PROJECT_DIR}

# NOTE: এখানে আপনাকে নিজে clone করতে হবে
echo "  → Run this manually to clone your repo:"
echo "     sudo -u ${DEPLOY_USER} git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git ${PROJECT_DIR}"

# ============================================
# Step 6: Configure Nginx
# ============================================
echo "[6/8] Configuring Nginx..."

cat > /etc/nginx/sites-available/zamzam-erp << NGINX_CONF
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${PROJECT_DIR}/zamzam-erp/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_CONF

ln -sf /etc/nginx/sites-available/zamzam-erp /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t && systemctl reload nginx

echo "  ✓ Nginx configured"

# ============================================
# Step 7: Setup Laravel .env on Server
# ============================================
echo "[7/8] You need to create .env file manually:"
echo ""
echo "  sudo -u ${DEPLOY_USER} nano ${PROJECT_DIR}/zamzam-erp/.env"
echo ""
echo "  Required settings:"
echo "    APP_ENV=production"
echo "    APP_DEBUG=false"
echo "    APP_URL=https://${DOMAIN}"
echo "    DB_CONNECTION=mysql"
echo "    DB_HOST=127.0.0.1"
echo "    DB_PORT=3306"
echo "    DB_DATABASE=${DB_NAME}"
echo "    DB_USERNAME=${DB_USER}"
echo "    DB_PASSWORD=${DB_PASS}"

# ============================================
# Step 8: SSL with Certbot
# ============================================
echo "[8/8] Installing SSL certificate..."
apt install -y certbot python3-certbot-nginx
echo "  → Run this to get SSL certificate:"
echo "     certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"

# ============================================
# Final: Allow deploy user to run commands
# ============================================
cat >> /etc/sudoers.d/deploy << SUDOERS
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx, /bin/systemctl restart php8.3-fpm
SUDOERS

echo ""
echo "============================================"
echo "  Server Setup Complete!"
echo "============================================"
echo ""
echo "Next steps:"
echo "  1. Clone your GitHub repo to ${PROJECT_DIR}"
echo "  2. Create .env file in ${PROJECT_DIR}/zamzam-erp/"
echo "  3. Run: composer install --no-dev --optimize-autoloader"
echo "  4. Run: npm ci && npm run build"
echo "  5. Run: php artisan migrate --force"
echo "  6. Run: php artisan storage:link"
echo "  7. Add GitHub Secrets (see DEPLOYMENT.md)"
echo "  8. Get SSL: certbot --nginx -d ${DOMAIN}"
echo ""
