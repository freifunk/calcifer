# calcifer

## Requirements

- PHP 8.0 or higher
- Composer
- SQLite (or another database supported by Doctrine)
- Docker and Docker Compose (for MariaDB development environment)

## Installation

1. Clone the repository:
    ```sh
    git clone https://github.com/freifunk/calcifer.git
    cd calcifer
    ```

2. Install PHP dependencies:
    ```sh
    composer install
    ```

## Entwicklungsdatenbank mit MariaDB

1. MariaDB-Container starten:
    ```sh
    docker compose up -d
    ```

2. Datenbank-Schema erstellen:
    ```sh
    php bin/console doctrine:schema:create
    ```

3. Wenn nötig, Datenbank-Container und Volumes löschen:
    ```sh
    docker compose down -v
    ```

4. Status der Datenbank überprüfen:
    ```sh
    docker compose ps
    ```

5. Direkte Verbindung zur Datenbank:
    ```sh
    docker compose exec database mysql -u app -p'!ChangeMe!' app
    ```

## Initializing the Database (SQLite)

1. Create the database schema:
    ```sh
    php bin/console doctrine:schema:create
    ```

## Running Tests

1. Run PHPUnit tests:
    ```sh
    ./vendor/bin/phpunit
    ```

## Additional Commands

- To clear the cache:
    ```sh
    php bin/console cache:clear
    ```

- To run the development server:
    ```sh
    symfony server:start -d
    ```
    or
    ```sh
    php -S localhost:8000 -t public/ 
    ```