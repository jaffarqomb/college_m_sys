# Automated College System

Automated College System is a Laravel-based college management platform for academic administration, student self-service, finance, timetable generation, and public admissions support. It combines a Livewire student/staff portal with a Filament administration panel and a responsive public-facing site.

## Features

- **Academic administration**: departments, programs, courses, prerequisites, academic terms, rooms, course sections, schedules, instructors, students, enrollments, attendance, assessments, grades, transcripts, and GPA reporting.
- **Student and instructor portals**: course enrollment and withdrawal, open-section browsing, personal enrollments, attendance marking, instructor gradebooks, student gradebooks, transcripts, and profile/security settings.
- **Finance workflows**: fee structures, term invoice generation, invoice items, manual payments, invoice publication, payment status tracking, and online Tap checkout/webhook handling.
- **AI timetable operations**: conflict-aware timetable generation, room and instructor balancing, generation status tracking, publishing, and a timetable builder in the admin panel.
- **Public website**: home, about, contact, contact-message persistence, and a rule-based visitor chatbot for programs, admissions, fees, timetables, grades, and campus contact information.
- **Teams and authorization**: team membership, invitations, team-scoped routes, role-based access, policies, and protected Filament resources.
- **Notifications**: mail notifications for enrollment, attendance, grades, invoices, payments, timetable publication, contact messages, and team invitations, with an optional SMS channel.
- **Authentication**: Laravel Fortify flows, email verification, password reset, password confirmation, two-factor authentication, and passkey support.
- **Operational tooling**: database backup command, queued jobs, database-backed cache/session/queue defaults, Pest tests, Pint formatting, and Larastan analysis.

## Technology stack

- PHP 8.3+
- Laravel 13
- Livewire 4 and Flux UI
- Filament 5
- Laravel Fortify
- SQLite by default, with MySQL, MariaDB, PostgreSQL, and SQL Server configuration available
- Tailwind CSS 4 and Vite Plus
- Pest 5, PHPUnit, Laravel Pint, and Larastan

## Requirements

- PHP 8.3 or newer with the extensions required by Laravel
- Composer
- Node.js and npm
- SQLite for the default local setup, or another database supported by `config/database.php`

## Installation

```bash
git clone https://github.com/jaffarqomb/college_m_sys.git
cd college_m_sys

composer install
cp .env.example .env
php artisan key:generate

# The default configuration uses SQLite.
touch database/database.sqlite
php artisan migrate --seed

npm install
npm run build
```

Start the local application with:

```bash
php artisan serve
```

For the complete development experience, use the Composer development script:

```bash
composer run dev
```

The application is then available at `http://localhost:8000` (or the URL shown by Artisan).

## Demo data and accounts

`php artisan migrate --seed` runs `AcademicDemoSeeder`, which creates departments, programs, terms, rooms, courses, prerequisites, instructors, students, sections, schedules, attendance, grades, fee structures, invoices, and payments. The seeder is designed to be safe to run again using business-key lookups.

All seeded staff and student demo accounts use the password:

```text
password
```

Example staff accounts include:

| Email | Role |
| --- | --- |
| `admin@college.test` | System administrator |
| `registrar@college.test` | Registrar |
| `finance@college.test` | Finance officer |
| `dept.head@college.test` | Department head |
| `peter.otieno@college.test` | Instructor |

The seeder also creates student accounts. Review `database/seeders/AcademicDemoSeeder.php` for the complete demo dataset and account list. Change or remove demo credentials before using the application outside a local development environment.

## Configuration

Copy `.env.example` to `.env` and configure values for the environment. Important settings include:

- `APP_URL`, `APP_ENV`, and `APP_DEBUG`
- `DB_CONNECTION` and database-specific connection variables
- `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION`
- `MAIL_*` values for email delivery
- `ATTENDANCE_ALERT_THRESHOLD`, `BACKUP_DISK`, and `BACKUP_KEEP`
- `OLLAMA_URL` and `OLLAMA_MODEL` for the local timetable/chat assistant integration
- `TAP_SECRET_KEY`, `TAP_CURRENCY`, and `TAP_BASE_URL` for Tap payments
- `SMS_DRIVER` and `SMS_FROM` for optional SMS notifications
- `SITE_CONTACT_EMAIL` and related public-site settings

The default local environment uses SQLite, log mail, database-backed sessions/cache/queues, and a local Ollama endpoint. Keep credentials in `.env`; never commit them.

## Useful commands

```bash
# Run the test suite, formatter check, and static analysis
composer test

# Format PHP files
composer lint

# Check formatting without changing files
composer lint:check

# Run Larastan
composer types:check

# Run only Laravel tests
php artisan test

# Build frontend assets
npm run build

# Watch frontend assets during development
npm run dev

# Create a database snapshot using the configured backup disk
php artisan college:backup-database
```

## Application structure

```text
app/
  Actions/       Application use cases for academic, finance, site, teams, and auth flows
  Data/          Typed data objects
  Enums/         Domain status, role, permission, and grading enums
  Filament/      Admin panel resources, pages, schemas, tables, and widgets
  Http/          Controllers, middleware, and response classes
  Jobs/          Queued work such as timetable generation
  Livewire/      Reactive portal and settings components
  Models/        Eloquent domain models and relationships
  Notifications/ Mail and SMS notifications
  Policies/      Authorization boundaries
  Services/      Grading, finance, reports, timetable, and Ollama integrations
database/
  migrations/    Database schema
  seeders/       Demo and application seed data
resources/
  css/           Tailwind application styles
  js/            Vite entry points and passkey client code
  views/         Blade, Livewire, Flux, public-site, portal, and admin views
routes/
  web.php        Public, team-scoped, payment, and portal routes
  settings.php   Profile, password, two-factor, appearance, and account routes
tests/
  Feature/       End-to-end application behavior coverage
  Unit/          Focused unit tests
```

## Testing and quality

The project includes feature coverage for authentication, academic workflows, Filament resources, dashboards, finance and Tap webhooks, notifications, reports, teams, timetable generation, and the public site. The `composer test` script clears configuration, checks formatting, runs Larastan, and executes the Laravel test suite.

## Integrations

- **Tap Payments**: hosted invoice checkout, return handling, and server-to-server webhook processing.
- **Ollama**: local model endpoint used by timetable and site-assistant services.
- **Mail**: Laravel notification mail delivery, configured through the standard mail environment variables.
- **SMS**: optional channel slot that can log messages or be connected to a provider.
- **Passkeys**: browser-side registration and verification through the Laravel passkeys package.

## Security notes

- Never commit `.env`, API keys, payment secrets, database credentials, or production-generated files.
- Replace the seeded demo password and remove demo accounts before deployment.
- Set `APP_DEBUG=false` in production.
- Configure trusted domains, HTTPS, mail, queue workers, backups, and payment webhook secrets for the deployment environment.

## License

This project inherits the license declared by its dependencies and project configuration. Add a project-specific license before distributing the application if required by your organization.
