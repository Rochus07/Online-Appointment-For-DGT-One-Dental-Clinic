# Dr. Elaine Silva Dental Clinic — Management System

PHP 8.2 (PDO) · MySQL/MariaDB · Native JavaScript (Fetch API) · custom CSS (medical slate/navy theme).

## Setup

1. **Start Apache + MySQL** from the XAMPP control panel.
2. **Import the database**:
   ```
   d:\XAMPP\mysql\bin\mysql.exe -u root < d:\XAMPP\htdocs\dgt_dental_clinic\schema.sql
   ```
   or open **phpMyAdmin → Import → `schema.sql`**. This creates the
   `dental_clinic_system` database, all tables, and seed data.
3. Visit **http://localhost/dgt_dental_clinic/**.

If your MySQL uses a password, edit the constants at the top of
[`config/db.php`](config/db.php).

## Demo accounts

Usernames are `first.last` (migration 028); all passwords are `password123`.

| Username            | Role         |
|---------------------|--------------|
| `admin.demo`        | Administrator |
| `elaine.silva`      | Dentist (owner, Doctor of Dental Medicine, 8 yrs) |
| `associate.dentist` | Dentist (associate, Doctor of Dental Surgery, 4 yrs) |
| `cashier.demo`      | Cashier       |
| `reception.demo`    | Receptionist  |

Dental assistants are non-account staff (no login) — they are a roster managed in
**Admin → User Accounts → Assistant Dentists** and are tracked by the front desk's daily
duty check-in and by assist tags on patient check-ins and treatment records.

There is no seeded patient account — self-register at `register.php`, or create a
patient from the admin Users / front-desk walk-in / Patient Records screens.

## Migrations

`schema.sql` is the full current schema. If you already have the database from an
earlier version, apply the files in [`migrations/`](migrations/) in order instead
of re-importing:

```
d:\XAMPP\mysql\bin\mysql.exe -u root < d:\XAMPP\htdocs\dgt_dental_clinic\migrations\001_add_user_avatar.sql
```

## Workflow

0. **Registration & login**: patients self-register with First / Middle (optional)
   / Last name, e-mail, contact number and a password; the system assigns a
   `first.last` **username** (shown on the confirmation screen) which is the only
   login credential — there is no e-mail login. Admin-created staff accounts get
   a system-generated temporary password (shown once to the admin); a reminder on
   the staff member's profile prompts them to set their own via **Change
   Password**.
1. **Patient** submits an online booking request: picks a service, insurance
   (optional), preferred date/time, then pays the reservation fee (Bank Transfer
   or GCash), enters the reference number and uploads a receipt. A live status
   screen polls for approval.
2. **Receptionist** reviews every pending request in *Appointments for Approval*
   — online bookings **and walk-ins** (both enter the queue as `pending` and wait
   their turn) — and either **approves** it (assigning a dentist) or **declines**
   it with a reason the patient sees. The Walk-in Booking modal first resolves
   the person to a real patient record (search for an existing one or create a
   new one inline, and pick a service) so a returning walk-in keeps a single
   history. Approving only assigns the dentist; **check-in is always a separate
   step** from *Approved Appointments*, done when the patient actually arrives.
   Check-in is what enables the dentist to log the procedure, and keeps the
   patient on the admin dashboard's **Ongoing Patients** card until the cashier
   bills them (a full payment or an installment downpayment both clear them).
   The receptionist marks which **dental assistants** reported for duty that day
   in the *Assistant Dentists on Duty Today* widget; the check-in modal and the
   dentist's treatment log then offer an optional "assisting staff" tag from that
   roster for shift and workload reporting.
3. **Dentist** opens the daily queue, logs the procedures performed for a
   checked-in patient in the treatment modal (totals auto-calculate), and marks
   the visit **completed** — this auto-creates a billing record in the **Cashier**
   queue.
4. **Cashier — Transactions**: for a completed visit, *Proceed to Billing* opens
   the **Service Invoice**. Scheduled visits can be *Rescheduled* here.
5. **Service Invoice**: `Total Sales − SC/PWD Discount (20%, needs ID) =` what the
   patient pays. `Doctor's Fee (20%)` and `Withholding Tax` are shown for records
   only. The reservation fee already paid is credited, so
   `Balance Due = Patient Total − Reservation Fee`. Pick **Cash / GCash / Bank
   Transfer** (GCash reference = 13 digits, globally unique), enter the received
   amount → change is computed → **Mark as Paid**.
6. A printable **Official Receipt** (`OR-000000`) is generated; the cashier,
   admin, and the patient can reopen it. All bills are listed in **Transaction
   Logs** (Paid / Pending).
7. **Dentist — Patient Records**: searchable list of every patient account. Opening
   a record shows personal info, treatment history, and an editable **dental chart**
   (FDI tooth grid — click a tooth to cycle Light-cured / Cured / Missing) plus
   medical-history fields (occlusion, periodontal, oral hygiene, dentures, …).
8. **Admin** dashboard shows Revenue / Today / Completed / Pending / Rescheduled /
   Cancelled (each card drills down), plus donut/bar charts for appointment status,
   accounts by role and top services.
9. **Admin — Revenue Reports**: filter by Day / Week / Month (anchored on a
   chosen date) or a custom Date Range; the table + total cover fully-paid
   transactions. **Generate PDF** downloads the filtered report as a formatted
   PDF in one click (html2pdf, no preview tab).
10. **Admin — Appointment**: every appointment across all statuses with
    Pending / Approved / Completed / Cancelled / Rescheduled counts, a status
    filter, an Online / Walk-in type column and an optional 30-second
    auto-refresh.
11. **Admin — Dentist Schedules**: set each dentist's duty mode (In-House Owner /
    Roaming Associate) and their recurring weekly working grid — which days
    (Mon–Sat) and which clinic time slots they work. Online booking shows only
    the in-house doctor's working day+time combinations; the receptionist
    walk-in / approve / assign pickers only list dentists on duty for the chosen
    date and time. The time-slot list itself stays clinic-wide (Manage
    Appointments).
12. **Admin — History Log**: system audit trail (sign-ins/outs, failed attempts,
    forced/self password resets, denied-access tries, appointment approvals /
    reschedules / cancellations / check-ins / walk-ins, patient online booking
    requests, patient-record edits, payments and discounts) with Role (incl.
    **Patient**) / Category / date-range / search filters. Admin-only to view.

## Structure

```
config/db.php              PDO connection
includes/
  auth_middleware.php      session + checkAuth()/apiAuth() RBAC
  functions.php            helpers (escaping, settings, notify, ...)
  header.php  sidebar.php  layout.php   page shell
assets/css/style.css       theme
assets/js/app.js           API wrapper, modal, toast, clock, notifications
assets/js/charts.js        dependency-free SVG donut + bar charts
assets/js/nav.js           AJAX page loader (fetch + history.pushState)
api/*.php                  JSON endpoints
modules/<role>/*.php       role modules (dashboard, dentist/calendar, shared/profile)
uploads/                   user uploads (avatars, reservation receipts, service images)
migrations/                incremental schema changes
schema.sql                 database + seed
```
