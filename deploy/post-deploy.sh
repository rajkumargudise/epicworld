#!/bin/bash
# Run on the server after every code upload. Keeps the live web root (public_html) in step with the app's
# public/ folder, because Apache serves static files from public_html, not from epicworld/public.
set -e
ROOT=/home/u844890624/domains/epicworld.in
APP=$ROOT/epicworld
PH=$ROOT/public_html

# Static asset folders and root files
ln -sfn "$APP/public/build" "$PH/build"
ln -sfn "$APP/public/brand" "$PH/brand"
ln -sfn "$APP/storage/app/public" "$PH/storage"
cp -f "$APP/public/favicon.ico" "$PH/favicon.ico"

# Compression + caching rules (appended once)
if ! grep -q "mod_deflate" "$PH/.htaccess"; then
  # everything after the repo's marker comment
  sed -n '/# Compression and long-lived caching/,$p' "$APP/public/.htaccess" >> "$PH/.htaccess"
  echo "caching rules added to public_html/.htaccess"
fi

cd "$APP"
php artisan optimize:clear >/dev/null
php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null
echo "post-deploy done"
