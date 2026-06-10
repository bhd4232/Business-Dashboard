# ZamZam ERP - Auto-Deploy গাইড

## কিভাবে কাজ করে

```
আপনি code লিখুন → git push → GitHub Actions চলবে → সার্ভার অটো আপডেট
```

---

## ধাপ ১: GitHub Repository Secrets সেট করুন

GitHub Repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

| Secret নাম | মান | বিবরণ |
|---|---|---|
| `SERVER_HOST` | `your-server-ip` | আপনার VPS-এর IP address |
| `SERVER_USER` | `deploy` | SSH username |
| `SERVER_SSH_KEY` | `-----BEGIN OPENSSH PRIVATE KEY-----...` | সার্ভার থেকে পাওয়া private key |
| `SERVER_PORT` | `22` | SSH port (default 22) |

### Private Key কোথায় পাবেন?

সার্ভারে `server-setup.sh` রান করলে স্ক্রিনে দেখাবে। অথবা:

```bash
cat /home/deploy/.ssh/id_ed25519
```

পুরো output কপি করুন (`-----BEGIN` থেকে `-----END` পর্যন্ত সহ)।

---

## ধাপ ২: সার্ভার প্রথমবার সেটআপ করুন

```bash
# সার্ভারে SSH করুন
ssh root@your-server-ip

# Setup script আপলোড করে রান করুন
bash server-setup.sh
```

তারপর manually:

```bash
# Repo clone করুন
sudo -u deploy git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git /var/www/zamzam-erp

# .env ফাইল তৈরি করুন
cd /var/www/zamzam-erp/zamzam-erp
cp .env.example .env
nano .env  # নিচের মত পূরণ করুন
```

**.env এ এগুলো সেট করুন:**
```env
APP_NAME="ZamZam ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zamzam_erp
DB_USERNAME=zamzam_user
DB_PASSWORD=YourStrongPassword123!
```

```bash
# প্রথমবার সব ইন্সটল করুন
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
```

---

## ধাপ ৩: SSL Certificate (HTTPS)

```bash
certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

---

## ধাপ ৪: Auto-Deploy টেস্ট করুন

```bash
# Local machine থেকে
git add .
git commit -m "test deploy"
git push origin main
```

GitHub → **Actions** ট্যাবে দেখুন deployment চলছে।

---

## Troubleshooting

**Deploy fail হলে:**
```bash
# সার্ভারে logs দেখুন
tail -f /var/log/nginx/error.log
tail -f /var/www/zamzam-erp/zamzam-erp/storage/logs/laravel.log
```

**Permission error হলে:**
```bash
cd /var/www/zamzam-erp/zamzam-erp
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**Queue worker সেটআপ (Supervisor দিয়ে):**
```bash
apt install -y supervisor

cat > /etc/supervisor/conf.d/zamzam-worker.conf << 'EOF'
[program:zamzam-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zamzam-erp/zamzam-erp/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/zamzam-erp/zamzam-erp/storage/logs/worker.log
EOF

supervisorctl reread
supervisorctl update
supervisorctl start zamzam-worker:*
```

---

## Deployment Flow (সংক্ষেপে)

```
git push main
    ↓
GitHub Actions (.github/workflows/deploy.yml)
    ↓
SSH → সার্ভারে connect
    ↓
git pull + composer install + npm build
    ↓
php artisan migrate --force
    ↓
Cache clear & rebuild
    ↓
✓ লাইভ আপডেট সম্পন্ন!
```
