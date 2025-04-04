#!/bin/bash
# run_tests.sh

# Datenbankname für Tests
export MYSQL_DATABASE=app_test

# Start Docker Compose
echo "Starting Docker containers..."
docker compose down -v
docker compose up -d

# Wait for database to be ready (idealerweise einen besseren Check implementieren)
echo "Waiting for database to initialize..."
sleep 10

# Führen Sie die Tests aus
echo "Running tests..."
php ./vendor/bin/phpunit "$@"
TEST_STATUS=$?

# Stoppen Sie die Container
echo "Stopping Docker containers..."
docker compose down

# Return the original exit code from PHPUnit
exit $TEST_STATUS
