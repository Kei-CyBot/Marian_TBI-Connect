# Marian TBI Connect

Marian TBI Connect is a Technology Business Incubator (TBI) management platform built with Laravel 12. It provides startup lifecycle tracking, user account management, and Google OAuth authentication for incubator staff, founders, and ecosystem partners.

---

## Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | Blade, Tailwind CSS, Vite (`vite.config.mjs`) |
| Database | MySQL 8+ / TiDB Cloud (SSL-enabled) |
| Authentication | Laravel Socialite (Google OAuth) |
| Tooling | Composer, npm, PHPUnit |

---

## Prerequisites

- PHP >= 8.2 with the `pdo_mysql`, `openssl`, `mbstring`, and `curl` extensions
- Composer
- Node.js 20+ and npm
- MySQL 8.0+ or TiDB Cloud, with the ISRG Root X1 CA certificate available for TLS connections

---

## Local Setup

### 1. Clone the repository

```bash
git clone https://github.com/Kei-CyBot/Marian_TBI-Connect.git
cd Marian_TBI-Connect
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure the environment

```bash
cp .env.example .env
```

Set the application URL, database connection, and Google OAuth credentials in `.env`:

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marian_tbi
DB_USERNAME=root
DB_PASSWORD=

# Optional path to the database CA certificate.
# Required for TiDB Cloud; use the ISRG Root X1 certificate.
MYSQL_ATTR_SSL_CA=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Register `http://127.0.0.1:8000/auth/google/callback` as an authorized redirect URI in the Google Cloud Console.

### 4. Generate the application key and migrate

```bash
php artisan key:generate
php artisan migrate --seed
```

### 5. Run the application

Start the frontend asset compiler and the Laravel development server in separate terminals:

```bash
npm run dev
```

```bash
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser.

---

## Production Build Notes

1. Build the frontend assets:

```bash
npm ci
npm run build
```

2. Point `MYSQL_ATTR_SSL_CA` at the Linux host's system CA bundle:

```dotenv
# Debian / Ubuntu
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

# RHEL / CentOS / Fedora
MYSQL_ATTR_SSL_CA=/etc/pki/tls/certs/ca-bundle.crt
```

3. Register the production Google OAuth callback in the Google Cloud Console and set the matching value in `.env`:

```dotenv
GOOGLE_REDIRECT_URI="https://your-domain.com/auth/google/callback"
```

4. Run the production migrations and optimize the application:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Testing

```bash
php artisan test
```
