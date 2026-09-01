#!/bin/sh
# init-laravel.sh — boot de DEV padronizado do Starter Kit (mesmo script em todo projeto).
#
# Idempotente: roda a cada subida do container (via `command:` do docker-compose).
# Mesmo arquivo para API e Platform — o bloco NPM só age se houver package.json
# (a API não tem; o Platform tem e o `npm run dev` do compose vem logo depois).
#
# NÃO roda `migrate`: migração é passo manual e consciente (ver a armadilha "migrate a
# cada boot" no CLAUDE.md do deploy). Rode uma vez: `php artisan migrate` no container.
set -e

echo "🚀 Initializing Laravel environment..."

# Storage + bootstrap/cache
echo "📁 Creating storage directories..."
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/testing
mkdir -p storage/logs
mkdir -p storage/app/public
mkdir -p bootstrap/cache

echo "🔒 Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R 1000:1000 storage bootstrap

# .env a partir do .env.example (só se faltar)
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo "📝 Creating .env from .env.example..."
        cp .env.example .env
    fi
fi

# Composer
if [ -f composer.json ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# APP_KEY (só se ainda não houver)
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --ansi || true
fi

# Caches (dev — limpa, nunca `config:cache`, senão env() fora de config quebra)
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true
php artisan route:clear || true

# Storage link (uploads públicos)
echo "🔗 Creating storage link..."
php artisan storage:link || true

# NPM (só o Platform tem package.json; a API pula)
if [ -f package.json ]; then
    echo "📦 Installing NPM dependencies..."
    npm install
fi

echo "✅ Laravel environment initialized!"
