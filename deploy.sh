#!/bin/bash
# ─────────────────────────────────────────────────────────────
# Bayzell ERP — First-deploy bootstrap script
# Run this ONCE after EasyPanel spins up the containers.
#
# Usage:
#   chmod +x deploy.sh
#   ./deploy.sh
# ─────────────────────────────────────────────────────────────

set -e

APP_CONTAINER="bayzell_app"

echo ""
echo "▶ Bayzell ERP — Bootstrap"
echo "──────────────────────────────────────────────"

# 1. Wait for the app container to be healthy
echo "⏳ Waiting for app container..."
until docker exec "$APP_CONTAINER" php -v > /dev/null 2>&1; do
  sleep 2
done
echo "✅ App container is ready."

# 2. Generate APP_KEY if not already set
if docker exec "$APP_CONTAINER" php artisan key:show 2>&1 | grep -q "base64:"; then
  echo "✅ APP_KEY already set — skipping."
else
  echo "🔑 Generating APP_KEY..."
  docker exec "$APP_CONTAINER" php artisan key:generate --force
fi

# 3. Clear config cache (picks up new .env values)
echo "🧹 Clearing config cache..."
docker exec "$APP_CONTAINER" php artisan config:clear
docker exec "$APP_CONTAINER" php artisan cache:clear

# 4. Run migrations
echo "🗄️  Running migrations..."
docker exec "$APP_CONTAINER" php artisan migrate --force --no-interaction

# 5. Seed the database (creates a demo tenant + admin user)
echo "🌱 Seeding database..."
docker exec "$APP_CONTAINER" php artisan db:seed --force --no-interaction

# 6. Cache config and routes for production
echo "⚡ Caching config and routes..."
docker exec "$APP_CONTAINER" php artisan config:cache
docker exec "$APP_CONTAINER" php artisan route:cache
docker exec "$APP_CONTAINER" php artisan view:cache

# 7. Fix storage permissions
echo "📁 Setting storage permissions..."
docker exec "$APP_CONTAINER" chmod -R 775 storage bootstrap/cache
docker exec "$APP_CONTAINER" chown -R www:www storage bootstrap/cache

# 8. Create storage symlink
echo "🔗 Linking storage..."
docker exec "$APP_CONTAINER" php artisan storage:link

echo ""
echo "──────────────────────────────────────────────"
echo "✅ Bootstrap complete!"
echo ""
echo "Your ERP is ready. Test it:"
echo "  curl http://localhost/api/v1/health"
echo ""
echo "First admin login:"
echo "  Email:    admin@demo-academy.com"
echo "  Password: password"
echo "  Tenant:   demo-academy (header: X-Tenant-Slug: demo-academy)"
echo "──────────────────────────────────────────────"
