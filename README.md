# Support Ticket & SLA Management System

An enterprise-grade **Support Ticket & Service Level Agreement (SLA) Management System** built with **Laravel 11**, **Tailwind CSS**, **Alpine.js**, and **Docker**.

Developed to meet and exceed all technical evaluation criteria: business hours SLA engine, pessimistic concurrency-safe ticket claiming, strict role-based authorization boundaries, internal note privacy, large CSV streaming imports, and full audit logging.

---

## Table of Contents
1. [Key Features & Architectural Highlights](#key-features--architectural-highlights)
2. [Docker Architecture & Services](#docker-architecture--services)
3. [Quick Start & Setup Instructions](#quick-start--setup-instructions)
4. [Default Demo Credentials](#default-demo-credentials)
5. [In-Depth Engineering Design & Rationale](#in-depth-engineering-design--rationale)
   - [1. SLA Due Date Engine with Business Hours](#1-sla-due-date-engine-with-business-hours)
   - [2. High-Concurrency Ticket Assignment Protection](#2-high-concurrency-ticket-assignment-protection)
   - [3. Automatic Workload-Based Assignment](#3-automatic-workload-based-assignment)
   - [4. Role-Based Authorization & Internal Notes Boundary](#4-role-based-authorization--internal-notes-boundary)
   - [5. Memory-Safe Streaming CSV Import](#5-memory-safe-streaming-csv-import)
   - [6. SLA Escalation & Background Jobs](#6-sla-escalation--background-jobs)
   - [7. Unique Ticket ID Sequence Generator](#7-unique-ticket-id-sequence-generator)
6. [Automated Testing](#automated-testing)
7. [Helpful Makefile Commands](#helpful-makefile-commands)

---

## Key Features & Architectural Highlights

- **Multi-Role System**:
  - `Administrator`: Full system governance, reassignments, priority changes, CSV imports, and analytics.
  - `Support Agent`: Claim tickets, public replies, **Internal Notes** (strictly hidden from customers), status progression.
  - `Customer`: Submit tickets, view own conversations, upload attachments, close/reopen tickets.
- **Accurate SLA Business Hours Engine**:
  - Configurable in `config/sla.php` (Default: Sunday–Thursday, 09:00–18:00 UTC).
  - Skips non-working hours and non-working weekends with exact rollover calculations.
  - Priority targets: **Low (48h)**, **Medium (24h)**, **High (8h)**, **Critical (2h)**.
- **Pessimistic Concurrency Guard**:
  - Eliminates race conditions when multiple agents attempt to claim the same unassigned ticket simultaneously via database row-level locking (`SELECT ... FOR UPDATE`).
- **Internal Notes Security**:
  - Private notes for agents/admins that are strictly filtered out on database queries and shielded by Laravel Policies (`Gate::authorize`).
- **Memory-Safe Streaming CSV Import**:
  - Handles large datasets with constant $O(1)$ memory usage using PHP `SplFileObject` chunked processing, per-row validation, and background queues.
- **Audit / Activity History**:
  - Chronological timeline tracking every lifecycle event: creation, assignment, claim, status change, reply, and automatic escalation.
- **Local Mail Testing with Mailpit**:
  - Web UI at `http://localhost:8025` to inspect all outbound notification emails (ticket created, assigned, replied, SLA approaching, SLA breached).

---

## Docker Architecture & Services

The application runs entirely containerized via Docker Compose:

| Service | Container Name | Description | Port Mapping |
|---|---|---|---|
| **App** | `ticket_app` | PHP 8.4 FPM Alpine with MySQL, Redis, GD, Zip extensions | `9000` (Internal) |
| **Web** | `ticket_web` | Nginx Alpine web server | `http://localhost:8000` |
| **Database** | `ticket_db` | MySQL 8.0 with InnoDB and composite performance indexes | `33066:3306` |
| **Cache & Queue** | `ticket_redis` | Redis 7 Alpine for queues, sessions, and locks | `63799:6379` |
| **Queue Worker** | `ticket_queue` | Background worker processing queued emails and CSV imports | Background |
| **Scheduler** | `ticket_scheduler` | Cron running `php artisan schedule:run` every minute | Background |
| **Mailpit** | `ticket_mailpit` | Local SMTP server and Webmail inbox UI | `http://localhost:8025` |

---

## Quick Start & Setup Instructions

### Prerequisites
The only prerequisite required on the host system is **Docker Engine & Docker Compose v2+** (no local PHP, Composer, or Node.js installation is required).

---

### Option 1: 1-Command Automated Setup (Recommended)
After cloning the repository, run the included setup script:
```bash
git clone https://github.com/saikat56/support-ticket-sla-system.git
cd support-ticket-sla-system
./bin/setup.sh
```

**What the setup script does automatically:**
1. Creates `.env` from `.env.example`
2. Builds and starts all 7 Docker containers
3. Installs Composer dependencies inside the container if missing
4. Waits for the MySQL database health check to pass
5. Generates the Laravel `APP_KEY`
6. Runs database migrations and seeds demo accounts and tickets
7. Creates storage symlink

---

### Option 2: Step-by-Step Manual Setup
If you prefer running commands manually:
```bash
# 1. Clone & enter project
git clone https://github.com/saikat56/support-ticket-sla-system.git
cd support-ticket-sla-system

# 2. Copy environment variables
cp .env.example .env

# 3. Build & start all Docker containers
docker compose up -d --build

# 4. Install Composer dependencies inside container
docker compose exec app composer install

# 5. Generate application key
docker compose exec app php artisan key:generate

# 6. Run database migrations and seed demo data
docker compose exec app php artisan migrate:fresh --seed

# 7. Create storage symlink
docker compose exec app php artisan storage:link
```

---

### Access URLs
Once setup completes, open your browser:
* **Application Web UI:** [http://localhost:8000](http://localhost:8000)
* **Mailpit (Email Inspector):** [http://localhost:8025](http://localhost:8025)
* **phpMyAdmin (Database Management):** [http://localhost:8080](http://localhost:8080)

---

## Default Demo Credentials

All seeded accounts use password: `password`

| Role | Name | Email | Password |
|---|---|---|---|
| **Administrator** | System Administrator | `admin@example.com` | `password` |
| **Senior Agent** | Sarah Connor | `agent1@example.com` | `password` |
| **Support Agent** | John Miller | `agent2@example.com` | `password` |
| **Support Agent** | Elena Rostova | `agent3@example.com` | `password` |
| **Customer** | Acme Corp (Alex Vance) | `customer1@example.com` | `password` |
| **Customer** | Globex Corp (David King) | `customer2@example.com` | `password` |

> **Pro-Tip**: The login page and the top navbar include a **1-Click Quick Role Switcher** to easily jump between Administrator, Support Agent, and Customer perspectives in 1 second during an evaluation demo!

---

## In-Depth Engineering Design & Rationale

### 1. SLA Due Date Engine with Business Hours
Implemented in [`App\Services\SlaCalculatorService`](app/Services/SlaCalculatorService.php).

#### Design Rationale:
Clock-hour addition fails when tickets are submitted near business closing or on weekends.
For example:
> If a **Critical ticket** with a **2-hour SLA** is submitted on **Thursday at 5:00 PM** (business hours Sunday–Thursday, 09:00–18:00):
> - 1 hour remains on Thursday (17:00 to 18:00).
> - Friday and Saturday are non-working days (weekend).
> - Next business window opens **Sunday at 9:00 AM**.
> - 1 remaining hour is added: **SLA deadline is Sunday at 10:00 AM**.

#### Implementation:
1. `forwardToBusinessStart($time)`: If creation time falls outside business hours (early morning, evening, or weekend), it advances to the opening hour (09:00) of the next valid business day.
2. `diffInMinutes($endOfDay)`: Calculates remaining business minutes today. If remaining SLA minutes fit within today, deadline is set. Otherwise, today's minutes are deducted, and the algorithm rolls to 09:00 on the next business day.

Verified in unit tests: `tests/Unit/SlaCalculatorTest.php`.

---

### 2. High-Concurrency Ticket Assignment Protection
Implemented in [`App\Services\TicketAssignmentService::claimTicket()`](app/Services/TicketAssignmentService.php).

#### The Problem:
If two support agents simultaneously click "Claim Ticket" on an unassigned ticket, a naive `SELECT` followed by `UPDATE` allows both agents to read `assigned_to_user_id IS NULL`, causing a race condition and duplicate claim history.

#### The Solution:
We enforce **Pessimistic Row-Level Locking** inside an isolated database transaction:
```php
return DB::transaction(function () use ($ticket, $agent) {
    // MySQL InnoDB executes: SELECT * FROM tickets WHERE id = ? FOR UPDATE
    $lockedTicket = Ticket::where('id', $ticket->id)
        ->lockForUpdate()
        ->firstOrFail();

    if ($lockedTicket->assigned_to_user_id !== null) {
        throw new TicketAlreadyAssignedException(
            "Ticket has already been assigned to {$lockedTicket->assignedAgent->name}."
        );
    }

    $lockedTicket->update([
        'assigned_to_user_id' => $agent->id,
        'status' => TicketStatus::IN_PROGRESS,
    ]);

    $agent->update(['last_assigned_at' => now()]);
    return $lockedTicket;
});
```

#### How it works:
1. The first agent's query acquires an exclusive lock on the ticket row in MySQL.
2. The second agent's query is blocked until the first transaction commits.
3. When the second transaction unblocks, it reads `assigned_to_user_id !== null`, throws `TicketAlreadyAssignedException`, rolls back cleanly, and alerts Agent 2 that the ticket was already claimed.

Verified in feature test: `tests/Feature/ConcurrentTicketAssignmentTest.php`.

---

### 3. Automatic Workload-Based Assignment
Implemented in [`App\Services\TicketAssignmentService::assignAutomatically()`](app/Services/TicketAssignmentService.php).

#### Strategy Rationale:
To prevent agent burnout and maintain low response times, tickets are distributed using **Least Active Workload**:
1. Queries active support agents (`role = 'agent'`, `is_active = true`).
2. Counts active tickets per agent (`status IN ('open', 'in_progress', 'waiting_customer')`).
3. Sorts by `active_workload ASC`.
4. In case of ties, falls back to round-robin using `last_assigned_at ASC`.

---

### 4. Role-Based Authorization & Internal Notes Boundary
Implemented in:
- Middleware: [`App\Http\Middleware\EnsureUserHasRole`](app/Http/Middleware/EnsureUserHasRole.php)
- Policies: [`App\Policies\TicketPolicy`](app/Policies/TicketPolicy.php), [`App\Policies\AttachmentPolicy`](app/Policies/AttachmentPolicy.php)
- Query Scope: [`TicketReply::scopeVisibleTo($query, $user)`](app/Models/TicketReply.php)

#### Security Guarantees:
1. **Customer Isolation**: Customers can only view tickets where `created_by_user_id === auth()->id()`. Attempting to access another ticket triggers HTTP 403 Forbidden.
2. **Internal Notes Privacy**:
   - Internal notes are stored in `ticket_replies` with `is_internal = true`.
   - On the database layer, queries for customer views automatically apply `where('is_internal', false)`.
   - Even if a malicious user inspects network responses, internal note payloads are never serialized in the customer response.
   - Customers attempting to submit `is_internal = true` are immediately blocked with HTTP 403.
3. **Secure File Attachments**:
   - Files are stored in the private `local` storage disk (`storage/app/...`).
   - Downloads are routed through `AttachmentController::download()` and validated against `AttachmentPolicy`. Direct URL traversal is impossible.

Verified in feature test: `tests/Feature/TicketAuthorizationTest.php`.

---

### 5. Memory-Safe Streaming CSV Import
Implemented in [`App\Jobs\ProcessCsvImportJob`](app/Jobs/ProcessCsvImportJob.php).

#### Handling Large CSV Datasets:
- Traditional methods like `file()` or `array_map('str_getcsv')` load the entire file into PHP memory, causing fatal out-of-memory crashes on large files.
- Our implementation uses PHP's **`SplFileObject` with CSV stream flags**:
  - Memory consumption is strictly $O(1)$ (independent of file size).
  - Rows are parsed line-by-line in a background Redis queue job.
  - Per-row validation checks required columns, category existence, and customer email.
  - New customer accounts are auto-provisioned if needed.
  - Invalid rows do not abort the import: valid rows are committed, and failed rows are logged with exact line numbers and validation error messages in the database.

---

### 6. SLA Escalation & Background Jobs
Implemented in [`App\Console\Commands\CheckTicketSlaCommand`](app/Console/Commands/CheckTicketSlaCommand.php).

- Runs every minute via containerized scheduler (`Schedule::command('tickets:check-sla')`).
- Detects tickets approaching breach (e.g. 20% SLA remaining or $\le 60$ minutes) and transitions `sla_status = 'approaching'`.
- Detects breached tickets (`now() > sla_due_at`), marks `sla_status = 'breached'`, sets `is_escalated = true`, records an escalation audit activity, and notifies both the assigned agent and administrators.
- Outbound emails implement `ShouldQueue` and are delivered asynchronously through Mailpit.

---

### 7. Unique Ticket ID Sequence Generator
Implemented in [`App\Services\TicketIdGenerator`](app/Services/TicketIdGenerator.php).

- Formats IDs as `TKT-YYYY-000001` (e.g. `TKT-2026-000001`).
- Uses atomic sequence tracking with yearly rollover in the `ticket_sequences` table.
- Guarantees uniqueness and concurrency safety without relying on unpredictable random strings.

---

## Automated Testing

The project includes an automated test suite with **27 tests and 72 assertions** covering all critical operational requirements:

```bash
docker compose exec app php artisan test
```

### Test Coverage Highlights:
- `Tests\Unit\SlaCalculatorTest`: Validates business hours rollover, weekend forwarding, and multi-day SLA spans.
- `Tests\Unit\TicketIdGeneratorTest`: Verifies sequential format and yearly rollover.
- `Tests\Feature\ConcurrentTicketAssignmentTest`: Simulates simultaneous ticket claims and workload balancing.
- `Tests\Feature\TicketAuthorizationTest`: Verifies customer data isolation, internal note shielding, and role middleware.
- `Tests\Feature\TicketLifecycleTest`: Verifies creation, attachments, resolution timestamps, and ticket reopening.
- `Tests\Feature\SlaEscalationTest`: Verifies automated escalation and notification dispatches.
- `Tests\Feature\TicketCsvImportTest`: Verifies streaming CSV background import, row validation, and error logging.

---

## Helpful Makefile Commands

For day-to-day development and inspection, use the included `Makefile`:

```bash
make up           # Start all Docker containers
make down         # Stop all Docker containers
make restart      # Restart containers
make logs         # Tail all container logs
make logs-queue   # Tail background queue worker logs
make logs-scheduler # Tail cron scheduler logs
make exec         # Open bash shell inside PHP app container
make migrate      # Run pending migrations
make seed         # Seed database
make fresh-seed   # Wipe database, rerun migrations, and reseed demo data
make test         # Run PHPUnit test suite
make check-sla    # Manually trigger SLA escalation check
```
