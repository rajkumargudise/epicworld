#!/bin/bash
# Restore the WordPress site exactly as it was before cutover.
set -e
ROOT=/home/u844890624/domains/epicworld.in
APP=$ROOT/epicworld
PH=$ROOT/public_html
BK=$(cat $ROOT/.last-wp-backup)
[ -d "$BK" ] || { echo "no backup folder"; exit 1; }

cd "$PH"
# remove the Laravel front door
rm -f index.php .htaccess favicon.ico build storage
# restore wp-content pieces (uploads never moved)
for f in $(ls -A "$BK/wp-content"); do mv "$BK/wp-content/$f" "$PH/wp-content/"; done
rmdir "$BK/wp-content" 2>/dev/null || true
# restore WordPress core
for f in $(ls -A "$BK"); do mv "$BK/$f" "$PH/"; done

cd "$APP"
sed -i 's#^APP_URL=.*#APP_URL=https://staging.epicworld.in#' .env
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
echo "ROLLED BACK to WordPress"
