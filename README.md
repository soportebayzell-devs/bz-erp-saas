# Bayzell ERP — Laravel 11

## Phase 1 complete. Phase 2 (Attendance, Staff, Scheduling) and Phase 3 (Automation, Reporting) are pre-wired.

---

## Folder Structure

```
bayzell-erp/
│
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── MarkOverdueInvoices.php       ← Daily cron: stamps overdue invoices
│   │       └── SendFollowUpReminders.php     ← Daily cron: stale lead nudges
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/V1/
│   │   │       ├── Auth/
│   │   │       │   └── AuthController.php    ← login / logout / refresh / me
│   │   │       ├── CRM/
│   │   │       │   └── LeadController.php    ← full CRUD + convert + activities
│   │   │       ├── Students/
│   │   │       │   └── StudentController.php ← full CRUD + enroll + status
│   │   │       ├── Courses/
│   │   │       │   └── CourseController.php  ← full CRUD + capacity guard
│   │   │       ├── Finance/
│   │   │       │   └── InvoiceController.php ← invoices + payments
│   │   │       └── Webhooks/
│   │   │           └── WebhookController.php ← public lead intake endpoint
│   │   │
│   │   └── Middleware/
│   │       └── ResolveTenant.php             ← subdomain/domain → tenant context
│   │
│   ├── Jobs/
│   │   └── NotificationJobs.php              ← SendOverdueInvoiceReminder, SendLeadFollowUpReminder
│   │
│   ├── Models/
│   │   ├── Tenant.php
│   │   └── User.php
│   │
│   └── Modules/                              ← Domain modules (high cohesion)
│       ├── CRM/
│       │   ├── Models/
│       │   │   ├── Lead.php
│       │   │   └── LeadActivity.php
│       │   └── Services/
│       │       └── LeadService.php           ← create / convert / assign / log
│       │
│       ├── Students/
│       │   └── Models/
│       │       └── Student.php
│       │
│       ├── Courses/
│       │   └── Models/
│       │       ├── Course.php
│       │       └── Enrollment.php
│       │
│       ├── Finance/
│       │   ├── Models/
│       │   │   ├── Invoice.php
│       │   │   ├── InvoiceItem.php
│       │   │   └── Payment.php
│       │   └── Services/
│       │       └── InvoiceService.php        ← generate / recordPayment / markOverdue
│       │
│       ├── Attendance/                       ← Phase 2
│       ├── Staff/                            ← Phase 2
│       ├── Scheduling/                       ← Phase 2 (CalDAV sync)
│       └── Automation/                       ← Phase 3 (workflow engine)
│
├── bootstrap/
│   └── app.php                               ← Middleware aliases, schedule, exceptions
│
├── config/
│   └── erp.php                               ← Statuses, features, defaults
│
├── database/
│   └── migrations/
│       ├── 0001_create_tenants_table.php
│       ├── 0002_create_users_table.php
│       ├── 0003_create_leads_table.php
│       ├── 0004_create_crm_students_courses_tables.php
│       └── 0005_create_finance_tables.php
│
├── routes/
│   └── api.php                               ← All API routes with tenant + auth middleware
│
├── docker-compose.yml                        ← app, nginx, postgres, redis, horizon, scheduler
├── .env.example
└── composer.json
```

---

## Quick Start

```bash
# 1. Clone and configure
cp .env.example .env

# 2. Start services
docker-compose up -d

# 3. Install deps and run migrations
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed

# 4. Verify Horizon is running
open http://localhost:8080/horizon
```

---

## API Quick Reference

| Method | Endpoint                              | Description                  |
|--------|---------------------------------------|------------------------------|
| POST   | /api/v1/auth/login                    | Login → token                |
| GET    | /api/v1/auth/me                       | Current user + tenant        |
| GET    | /api/v1/leads                         | List leads (filterable)      |
| POST   | /api/v1/leads                         | Create lead                  |
| PATCH  | /api/v1/leads/{id}/status             | Update lead status           |
| POST   | /api/v1/leads/{id}/convert            | Lead → Student               |
| POST   | /api/v1/leads/{id}/activities         | Log call/email/note          |
| GET    | /api/v1/students                      | List students                |
| POST   | /api/v1/students/{id}/enroll          | Enroll in course             |
| GET    | /api/v1/courses                       | List courses                 |
| POST   | /api/v1/invoices                      | Generate invoice             |
| POST   | /api/v1/invoices/{id}/payments        | Record payment               |
| POST   | /api/v1/webhooks/lead-intake/{slug}   | Public lead intake webhook   |

---

## Phase 2 Roadmap

- `Attendance` module (manual check-in, reports)
- `Staff` module (roles, salary, PTO)
- `Scheduling` module (CalDAV / Nextcloud sync — reuse Meeting Manager pattern)
- Email templates for all notifications

## Phase 3 Roadmap

- `Automation` engine (event triggers + conditional workflows + delays)
- `Reporting` module (revenue dashboard, funnel, CSV/PDF export)
- WhatsApp integration via n8n
- S3 file management module
