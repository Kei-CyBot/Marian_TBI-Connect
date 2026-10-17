<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Marian TBI Connect

Technical needs are stored per incubatee. The Technical Needs landing page shows only incubatee folders; open a folder to see its records. To add or edit a need, print the completed PHP-denominated request, obtain the required signatures, scan it, and upload the signed PDF (maximum 10 MB). The record is not saved until the approval PDF is attached, and signed documents are downloadable only by users with access to that incubatee folder.

Submitted forms and their attachments appear inside the corresponding incubatee folder. Folder access can be restricted to selected signed-in team users; the folder manager retains access. This app permission is also checked when downloading an attachment.

### Google Drive connection

1. Enable the Google Drive API in the Marian TBI Google Cloud project and configure a Google OAuth web client.
2. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_DRIVE_REDIRECT_URI` (for example, `https://your-domain/auth/google-drive/callback`), and `GOOGLE_DRIVE_ACCOUNT_EMAIL` in the deployment environment. The account email must be the dedicated Marian TBI account.
3. Add the same callback URL to the OAuth client's authorized redirect URIs, run `php artisan migrate`, and sign in to Marian TBI Connect.
4. Open **File Drive & Documents**, connect the dedicated Google account, then select **Sync to Google Drive**. The first sync creates a private `Marian TBI Connect` root folder, one folder per incubatee, and JSON form-response files; uploaded form attachments are copied alongside them. New form submissions sync automatically after connection.
5. Folder access changes are mirrored as Google Drive reader permissions. Drive tokens are stored encrypted. Do not share the app-created root folder broadly in Drive, or inherited Drive permissions could bypass the app's per-folder allow-list.

Manually pasted Google Drive URLs remain external links and are not copied or synchronized by this integration. The app does not import files already present in those linked folders.

### Team roles and profile

The team roles are **Super Admin**, **Admin**, and **Member/Incubatee**. Super Admins can assign roles; Admins can manage workspace records; Members/Incubatees have read-only workspace access and can update their own profile. Set `SUPER_ADMIN_EMAIL` to an existing account before running migrations to designate it as the initial Super Admin. If it is unset, the role migration promotes the oldest existing account. The database seeder also creates its test account as Super Admin.

Users can update their name, sign-in email, and password from **My profile & credentials**. The current password is required to save profile changes.

### Contact email

From Contacts or Events, an Admin can compose a message and select individual contact recipients. The application sends a separate email to each selected contact. Configure Laravel's outgoing mailer (`MAIL_MAILER`, SMTP credentials where applicable, and `MAIL_FROM_ADDRESS`) before sending; the default `log` mailer is intentionally not treated as delivery.
