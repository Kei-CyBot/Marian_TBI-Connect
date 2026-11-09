# Marian TBI Connect

**Marian TBI Connect** is an incubator management platform built with Laravel, designed to track incubatee startups, technical assistance needs, document repositories, network contacts, and ecosystem analytics.

---

## 🛠 Tech Stack & Requirements

| Dependency | Required Version |
| :--- | :--- |
| **PHP** | `^8.2` |
| **Composer** | `^2.5` |
| **Node.js** | `^20.0` |
| **NPM** | `^10.0` |
| **Database** | PostgreSQL `14+`, MySQL `8.0+`, or SQLite |

---

## 🚀 Quickstart: Local Development Setup

### 1. Clone the Repository

```bash
git clone https://github.com/Kei-CyBot/Marian_TBI-Connect.git
cd Marian_TBI-Connect
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

Copy the example environment file and generate a unique application key:

```bash
cp .env.example .env
php artisan key:generate
```

Configure your database credentials inside `.env`:

```dotenv
APP_NAME="Marian TBI Connect"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

### 4. Database Setup & Storage Link

```bash
php artisan migrate --seed
php artisan storage:link
```

### 5. Run the Application

Start the Laravel server and frontend asset compiler in separate terminal tabs.

**Terminal 1 (Backend Server):**

```bash
php artisan serve
```

**Terminal 2 (Frontend Assets):**

```bash
npm run dev
```

Visit http://localhost:8000 in your browser.

---

## 🧪 Running Automated Tests

```bash
# Run full test suite
php artisan test

# Run specific module tests
php artisan test --filter TechnicalNeedTest
php artisan test --filter DashboardTest
```

---

## 🌐 Production Deployment Guidelines

### 1. Production Environment Configuration

Set the following variables in your production `.env` file:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 2. Deployment Commands

```bash
# Install PHP dependencies without dev packages
composer install --no-dev --optimize-autoloader

# Build production frontend assets
npm ci
npm run build

# Run database migrations
php artisan migrate --force

# Create public storage symlink
php artisan storage:link

# Cache configuration, routes, and views for optimal performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 🛠 Maintenance Commands

```bash
# Clear application optimizations and caches
php artisan optimize:clear
```
