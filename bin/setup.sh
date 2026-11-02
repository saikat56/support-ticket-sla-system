#!/usr/bin/env bash
set -e

echo "=== Initializing Support Ticket & SLA System ==="

# 1. Copy .env if not exists
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# 2. Build and start containers
echo "Starting Docker containers..."
docker compose up -d --build

# 3. Install Composer dependencies inside container if missing
if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies inside app container..."
    docker compose exec app composer install --prefer-dist --optimize-autoloader --no-interaction
fi

# 4. Wait for MySQL to be healthy
echo "Waiting for MySQL database container to be healthy..."
until docker compose exec db mysqladmin ping -h localhost -u ticket_user -pticket_password --silent; do
    echo "Waiting for database connection..."
    sleep 2
done

# 5. Generate app key if needed
docker compose exec app php artisan key:generate --force

# 6. Run migrations and seed
echo "Running database migrations and seeders..."
docker compose exec app php artisan migrate:fresh --seed --force

# 7. Storage link
docker compose exec app php artisan storage:link || true

echo ""
echo "=== Setup complete! ==="
echo "Application URL: http://localhost:8000"
echo "Mailpit Mailbox: http://localhost:8025"
echo "phpMyAdmin:      http://localhost:8080"
echo ""
echo "Demo Accounts (Password: 'password'):"
echo "  - Admin:    admin@example.com"
echo "  - Agent 1:  agent1@example.com"
echo "  - Agent 2:  agent2@example.com"
echo "  - Customer: customer1@example.com"
