# Installation

## Requirements

- PHP >= 8.0
- Composer
- MySQL / MariaDB (or PostgreSQL / SQLite for alternative drivers)
- Apache with `mod_rewrite` enabled (or use the built-in development server)

## Option A — Via Composer (recommended)

```bash
composer create-project gustocoder/dorguzen my-app
cd my-app
```

Replace `my-app` with your project folder name. Composer will pull the latest stable release and install all dependencies automatically.

## Option B — Clone from GitHub

```bash
git clone https://github.com/gustavNdamukong/Dorguzen.git my-app
cd my-app
composer install
```

## 1. Set up your environment file

Dorguzen ships with `.env.example` as a starting template.

```bash
cp .env.example .env
```

Open `.env` and fill in the values for your local setup. At minimum you need:

```dotenv
APP_NAME=yourAppName
APP_URL=http://localhost/yourAppName

FILE_ROOT_PATH_LOCAL=/yourAppName/

DB_CONNECTION=mysqli
DB_HOST=127.0.0.1
DB_DATABASE=your_database_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
DB_KEY=a-random-encryption-key
```

> `DB_KEY` is used to AES-encrypt password fields in the database. Choose any random string and keep it consistent — changing it after data has been inserted will break password verification.

See the [Configuration](/dorguzen-docs/docs/configuration) page for the full list of available environment variables.

## 2. Create the database

Create a MySQL database that matches the `DB_DATABASE` name you set, then run the migrations:

```bash
php dgz migrate
```

This creates all the core tables — users, logs, jobs, SEO settings, contact form messages, and more.

## 3. Seed the database

```bash
php dgz db:seed
```

This creates the default super-admin account and populates the `baseSettings` table with sensible starting values.

The default admin credentials are:

| Field    | Value               |
|----------|---------------------|
| Email    | admin@dorguzen.com  |
| Password | Admin123            |

> Change these immediately after your first login.

## 4. Point your web server at the project root

### Apache (MAMP / XAMPP)

Set the document root so that `http://localhost/yourAppName` resolves to the project directory containing `index.php`. Apache's `mod_rewrite` must be enabled and `AllowOverride All` must be set for the directory so that `.htaccess` is respected.

### Built-in PHP development server

If you prefer not to configure Apache, use the `serve` command:

```bash
php dgz serve
```

This starts the app on `http://localhost:8000` by default. You can specify a different port:

```bash
php dgz serve --port=9000
```

> The built-in server is for local development only. Use Apache or Nginx in production.

## 5. Log in

Visit `http://localhost/yourAppName` and you should see the home page. Navigate to `/auth/login` and sign in with the admin credentials above to access the admin dashboard at `/admin/dashboard`.
