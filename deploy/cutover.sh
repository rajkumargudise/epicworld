#!/bin/bash
# Cut epicworld.in over from WordPress to the Laravel app. Everything is MOVED (not deleted)
# into a timestamped backup folder outside the web root; rollback.sh puts it all back.
set -e
ROOT=/home/u844890624/domains/epicworld.in
APP=$ROOT/epicworld
PH=$ROOT/public_html
TS=$(date +%Y%m%d-%H%M%S)
BK=$ROOT/wp-backup-$TS
echo "$BK" > $ROOT/.last-wp-backup

mkdir -p "$BK/wp-content"
cd "$PH"

# 1. Move the WordPress core files aside.
for f in .htaccess .htaccess.bk index.php license.txt readme.html wp-activate.php wp-admin wp-blog-header.php \
         wp-comments-post.php wp-config.php wp-config-sample.php wp-cron.php wp-includes wp-links-opml.php \
         wp-load.php wp-login.php wp-mail.php wp-settings.php wp-signup.php wp-trackback.php xmlrpc.php; do
  if [ -e "$f" ] || [ -L "$f" ]; then mv "$f" "$BK/"; fi
done

# 2. wp-content: keep ONLY uploads (existing post images live there); move plugins/themes/etc. aside.
cd "$PH/wp-content"
for f in $(ls -A); do
  [ "$f" = "uploads" ] && continue
  mv "$f" "$BK/wp-content/"
done
cd "$PH"

# 3. Install the Laravel front door.
cp "$APP/public/.htaccess" "$PH/.htaccess"
cat > "$PH/index.php" <<'PHPEOF'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode
if (file_exists($maintenance = __DIR__.'/../epicworld/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../epicworld/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../epicworld/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHPEOF
ln -sfn "$APP/public/build" "$PH/build"
ln -sfn "$APP/storage/app/public" "$PH/storage"
[ -f "$APP/public/favicon.ico" ] && cp "$APP/public/favicon.ico" "$PH/favicon.ico" || true

# 4. Point the app at the real domain and rebuild caches.
cd "$APP"
sed -i 's#^APP_URL=.*#APP_URL=https://epicworld.in#' .env
php artisan optimize:clear >/dev/null
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null

echo "CUTOVER DONE. Backup at $BK"
