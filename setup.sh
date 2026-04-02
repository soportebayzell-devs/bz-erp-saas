#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# setup.sh — Bayzell ERP Project Scaffolder
#
# Run this ONCE on your local machine (requires PHP 8.3 + Composer).
# It creates a real Laravel project and merges the ERP skeleton into it.
#
# Usage:
#   chmod +x setup.sh
#   ./setup.sh
#
# After this script finishes:
#   git add . && git commit -m "feat: initial laravel + erp scaffold"
#   git push origin main
#   → EasyPanel will build and deploy successfully.
# ═══════════════════════════════════════════════════════════════

set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; CYAN='\033[0;36m'; NC='\033[0m'

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   Bayzell ERP — Project Scaffolder       ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
echo ""

# ── Check prerequisites ───────────────────────────────────────
echo -e "${YELLOW}Checking prerequisites...${NC}"

if ! command -v php &> /dev/null; then
    echo -e "${RED}ERROR:${NC} PHP not found. Install PHP 8.3+ first."
    echo "  macOS:  brew install php"
    echo "  Ubuntu: sudo apt install php8.3-cli php8.3-xml php8.3-curl php8.3-zip"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "  ${GREEN}✓${NC} PHP $PHP_VERSION found"

if ! command -v composer &> /dev/null; then
    echo -e "${RED}ERROR:${NC} Composer not found."
    echo "  Install: https://getcomposer.org/download/"
    exit 1
fi
echo -e "  ${GREEN}✓${NC} Composer found"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ── Save ERP skeleton files before Laravel overwrites anything ─
echo ""
echo -e "${YELLOW}→ Saving ERP skeleton files...${NC}"

TEMP_DIR=$(mktemp -d)
trap "rm -rf $TEMP_DIR" EXIT

# Copy everything we want to preserve
cp -r app/           "$TEMP_DIR/app/"
cp -r database/      "$TEMP_DIR/database/"
cp -r routes/        "$TEMP_DIR/routes/"
cp -r config/        "$TEMP_DIR/config/"
cp -r docker/        "$TEMP_DIR/docker/"
cp -r bootstrap/     "$TEMP_DIR/bootstrap/"
cp    composer.json  "$TEMP_DIR/composer.json"
cp    Dockerfile     "$TEMP_DIR/Dockerfile"
cp    docker-compose.yml "$TEMP_DIR/docker-compose.yml"
cp    .env.example   "$TEMP_DIR/.env.example"
[ -f deploy.sh ] && cp deploy.sh "$TEMP_DIR/"
[ -f README.md ] && cp README.md "$TEMP_DIR/README.md"

echo -e "  ${GREEN}✓${NC} Skeleton saved"

# ── Install Laravel into a temp location ──────────────────────
echo ""
echo -e "${YELLOW}→ Downloading Laravel 11...${NC}"

LARAVEL_DIR=$(mktemp -d)
trap "rm -rf $TEMP_DIR $LARAVEL_DIR" EXIT

composer create-project laravel/laravel:^11.0 "$LARAVEL_DIR" \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --quiet

echo -e "  ${GREEN}✓${NC} Laravel 11 downloaded"

# ── Merge: Laravel base → current directory ───────────────────
echo ""
echo -e "${YELLOW}→ Merging Laravel base into project...${NC}"

# Copy Laravel's core files that we don't have
for ITEM in \
    artisan \
    public \
    resources \
    lang \
    storage \
    tests \
    vite.config.js \
    package.json \
    phpunit.xml \
    .editorconfig \
    .gitattributes \
    tailwind.config.js \
; do
    if [ -e "$LARAVEL_DIR/$ITEM" ] && [ ! -e "$SCRIPT_DIR/$ITEM" ]; then
        cp -r "$LARAVEL_DIR/$ITEM" "$SCRIPT_DIR/$ITEM"
        echo -e "    Added: $ITEM"
    fi
done

# Copy Laravel bootstrap files we need but preserve our bootstrap/app.php
cp "$LARAVEL_DIR/bootstrap/providers.php" "$SCRIPT_DIR/bootstrap/providers.php" 2>/dev/null || true

echo -e "  ${GREEN}✓${NC} Laravel base merged"

# ── Restore ERP skeleton on top ───────────────────────────────
echo ""
echo -e "${YELLOW}→ Applying ERP skeleton on top...${NC}"

# Our files take precedence over Laravel defaults
cp -r "$TEMP_DIR/app/"        "$SCRIPT_DIR/app/"
cp -r "$TEMP_DIR/database/"   "$SCRIPT_DIR/database/"
cp -r "$TEMP_DIR/config/"     "$SCRIPT_DIR/config/"
cp -r "$TEMP_DIR/docker/"     "$SCRIPT_DIR/docker/"
cp    "$TEMP_DIR/bootstrap/app.php" "$SCRIPT_DIR/bootstrap/app.php"

# Routes: merge rather than overwrite
# Laravel has routes/web.php and routes/console.php — keep both, add our api.php
cp "$TEMP_DIR/routes/api.php" "$SCRIPT_DIR/routes/api.php"

# .env.example: use ours (it has ERP-specific vars)
cp "$TEMP_DIR/.env.example" "$SCRIPT_DIR/.env.example"

echo -e "  ${GREEN}✓${NC} ERP skeleton applied"

# ── Merge composer.json dependencies ──────────────────────────
echo ""
echo -e "${YELLOW}→ Merging composer.json...${NC}"

# Use PHP to merge our ERP packages into Laravel's composer.json
php -r "
    \$laravel = json_decode(file_get_contents('$LARAVEL_DIR/composer.json'), true);
    \$erp     = json_decode(file_get_contents('$TEMP_DIR/composer.json'), true);

    // Merge require sections (ERP packages on top of Laravel defaults)
    \$merged = \$laravel;
    \$merged['require']     = array_merge(\$laravel['require'] ?? [], \$erp['require'] ?? []);
    \$merged['require-dev'] = array_merge(\$laravel['require-dev'] ?? [], \$erp['require-dev'] ?? []);
    \$merged['description'] = \$erp['description'];
    \$merged['name']        = \$erp['name'];

    file_put_contents('composer.json', json_encode(\$merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    echo 'Merged.' . PHP_EOL;
"

echo -e "  ${GREEN}✓${NC} composer.json merged"

# ── Run composer install ───────────────────────────────────────
echo ""
echo -e "${YELLOW}→ Running composer install (generates composer.lock)...${NC}"

composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress

echo -e "  ${GREEN}✓${NC} Packages installed, composer.lock generated"

# ── Set up local .env ─────────────────────────────────────────
echo ""
echo -e "${YELLOW}→ Setting up local .env...${NC}"

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --quiet
    echo -e "  ${GREEN}✓${NC} .env created with APP_KEY"
else
    echo -e "  ${YELLOW}→${NC} .env already exists — skipping"
fi

# ── Storage setup ─────────────────────────────────────────────
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── Summary ───────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   ✅ Project scaffold complete!                      ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo "  New files added:"
echo "    artisan, public/index.php, resources/, storage/, tests/"
echo "    package.json, phpunit.xml, composer.lock"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo ""
echo "  1. Commit everything:"
echo "     git add ."
echo "     git commit -m 'feat: initial laravel + erp scaffold'"
echo "     git push origin main"
echo ""
echo "  2. EasyPanel will now build successfully."
echo "     The Dockerfile will find composer.lock and public/index.php."
echo ""
echo "  3. After first deploy, run bootstrap in EasyPanel terminal:"
echo "     php artisan migrate"
echo "     php artisan db:seed"
echo "     php artisan config:cache"
echo "     php artisan route:cache"
echo "     php artisan storage:link"
echo ""
