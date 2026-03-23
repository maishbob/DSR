# DSR — cPanel Deployment Files

## Files in this folder

| File | Where it goes on the server |
|---|---|
| `index.php` | `/home/tosnaxan/public_html/dsr.pinnaclekenyaprojects.com/index.php` |
| `.htaccess` | `/home/tosnaxan/public_html/dsr.pinnaclekenyaprojects.com/.htaccess` |
| `deploy.sh` | `/home/tosnaxan/dsr/backend/deploy.sh` (run once after upload) |

## Step-by-step

### 1. Build assets locally (on your machine)
```bash
cd backend
npm run build
```

### 2. Upload the Laravel app
ZIP the `backend/` folder (exclude `node_modules/`, `.git/`) and upload to:
```
/home/tosnaxan/dsr/
```
So the app lives at `/home/tosnaxan/dsr/backend/`.

### 3. Upload to web root
Upload these into `/home/tosnaxan/public_html/dsr.pinnaclekenyaprojects.com/`:
- `deploy/index.php`
- `deploy/.htaccess`
- `backend/public/build/` (entire folder)
- `backend/public/favicon.ico`

### 4. Create .env on the server
Create `/home/tosnaxan/dsr/backend/.env`:
```env
APP_NAME="DSR System"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://dsr.pinnaclekenyaprojects.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=tosnaxan_dsr
DB_USERNAME=tosnaxan_dsr
DB_PASSWORD=YOUR_STRONG_PASSWORD

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

### 5. Run the deploy script
Via cPanel Terminal or SSH:
```bash
cd /home/tosnaxan/dsr/backend
bash deploy.sh
```

### 6. Set PHP version
cPanel → MultiPHP Manager → set `dsr.pinnaclekenyaprojects.com` to PHP 8.2+

### 7. Add cron job
cPanel → Cron Jobs → Every minute:
```
* * * * * /usr/local/bin/php /home/tosnaxan/dsr/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /dev/null 2>&1
```

## Re-deploying after changes

| Change type | Action |
|---|---|
| PHP/backend code | Re-upload changed files → `php artisan config:cache` |
| Vue/JS/CSS | `npm run build` locally → re-upload `public/build/` |
| Database changes | `php artisan migrate --force` |
| .env changes | Edit on server → `php artisan config:cache` |
