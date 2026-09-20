# Doctor Booking System

A Laravel API for booking appointments with doctors: browse doctors and their services, check real-time availability, preview a booking's price, confirm it, and manage it afterwards (view, cancel, reschedule).

## Features

- Doctor listing and per-doctor service catalog
- Service selection with server-side validation (a service must belong to the chosen doctor)
- Doctor working-hour schedules and generated available time slots
- Booking preview (price/duration computed from the database, never trusted from the client)
- Booking confirmation with transactional, database-enforced double-booking prevention
- "My bookings" list, scoped to the authenticated user
- Database notification on booking confirmation
- Booking cancellation, subject to a configurable cutoff window
- Booking rescheduling, re-validated against availability

## Requirements

- PHP 8.3+
- Composer
- MySQL (or another Laravel-supported relational database)

## Installation

```bash
composer install

cp .env.example .env
php artisan key:generate

# Point DB_* in .env at your database, then:
php artisan migrate --seed

php artisan test

php artisan serve
```

The seeder creates 5 sample doctors, each offering a subset of 5 sample services, so the API has data to browse immediately. It does **not** create `doctor_schedules`, so seeded doctors have no available-slots until a schedule is added for them (via `DoctorSchedule::factory()` or a manual insert) — this keeps the seeded dataset deliberately minimal rather than baking in assumptions about working hours.

## API Documentation

All routes are prefixed with `/api`. Routes marked 🔒 require an `Authorization: Bearer <token>` header (obtained from `/register` or `/login`).

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/register` | Create an account, returns a Sanctum token |
| POST | `/login` | Authenticate, returns a Sanctum token |
| POST | `/logout` 🔒 | Revoke the current token |
| GET | `/doctors` | List active doctors |
| GET | `/doctors/{doctor}/services` | A doctor's active services |
| GET | `/doctors/{doctor}/available-slots` | Available start times for a date + selected services |
| POST | `/bookings/preview` 🔒 | Preview price/duration for a selection, without persisting |
| POST | `/bookings` 🔒 | Confirm a booking |
| GET | `/my-bookings` 🔒 | The authenticated user's bookings |
| POST | `/bookings/{booking}/cancel` 🔒 | Cancel a booking (owner only) |
| POST | `/bookings/{booking}/reschedule` 🔒 | Move a booking to a new date/time (owner only) |

### Examples

**List doctors**

```
GET /api/doctors
```
```json
[{ "id": 1, "name": "Dr. Ahmed", "specialization": "Dentist" }]
```

**A doctor's services**

```
GET /api/doctors/1/services
```
```json
{
    "doctor": { "id": 1, "name": "Dr. Ahmed" },
    "services": [{ "id": 1, "name": "Dental Cleaning", "price": "300.00", "duration": 30 }]
}
```

**Available slots**

```
GET /api/doctors/1/available-slots?date=2026-09-26&service_ids[]=1&service_ids[]=3
```
```json
{ "date": "2026-09-26", "slots": ["10:30", "13:30", "15:00"] }
```

**Preview a booking**

```
POST /api/bookings/preview
{ "doctor_id": 1, "service_ids": [1, 3], "date": "2026-09-26", "time": "10:30" }
```
```json
{
    "doctor": "Dr. Ahmed",
    "services": ["Dental Cleaning", "Consultation"],
    "total_price": 800,
    "date": "2026-09-26",
    "time": "10:30"
}
```

**Confirm a booking**

```
POST /api/bookings
{ "doctor_id": 1, "service_ids": [1, 3], "date": "2026-09-26", "time": "10:30" }
```
Returns `201` with the created booking, or `409` if the slot is no longer available.

## Database Structure

| Table | Purpose |
| --- | --- |
| `users` | Accounts (patients) |
| `doctors` | name, email, phone, specialization, bio, is_active |
| `services` | name, description, price, duration (minutes), is_active |
| `doctor_service` | Pivot: which services a doctor offers |
| `doctor_schedules` | Per-doctor, per-day-of-week working hours (`day_of_week` 0–6, `start_time`, `end_time`, `is_available`) |
| `bookings` | user_id, doctor_id, date, start_time, end_time, total_price, status, `slot_key` (see below) |
| `booking_service` | Pivot: services on a booking, with `price`/`duration` **snapshotted at booking time** so a later price change on the `services` table never alters an existing booking's cost |
| `notifications` | Laravel's standard database notifications table |

```
Doctor 1---* Booking *---1 User
Doctor *---* Service (via doctor_service)
Booking *---* Service (via booking_service, carries price/duration)
Doctor 1---* DoctorSchedule
```

## Booking Flow

```
Doctor -> Services -> Select Services -> Select Date -> Select Time
       -> Preview -> Confirm -> Booking Created -> Notification -> My Bookings
```

## Business Rules

- **Services must belong to the doctor.** Every endpoint that accepts `service_ids` re-validates that each ID is actually offered by the given doctor (`Doctor::offersServices()`); an unrelated service ID fails validation with a `422`.
- **Price and duration are never trusted from the client.** They are always recomputed server-side from the current `services` rows at preview/confirm time (`BookingPriceCalculator`).
- **Double booking is prevented at two layers**, both exercised by tests:
  1. `BookingService::book()` runs inside a DB transaction, locks the doctor's existing bookings for that date (`lockForUpdate`), and re-checks availability before inserting.
  2. The `bookings.slot_key` column (`"{doctor_id}|{date}|{start_time}"`, `NULL` once cancelled) carries a database-level unique constraint — the final guard against two requests racing past the application-level check at the exact same instant. A collision is caught and surfaced as `409 Conflict`.
- **Cancellation** is blocked for a `completed` or already-`cancelled` booking, and is only allowed at least `BOOKING_CANCELLATION_HOURS` (default `24`, configurable in `.env`) before the appointment's start time.
- **Rescheduling** is blocked for a `completed` or `cancelled` booking, and re-runs the same availability check used when first booking (excluding the booking's own current slot, so it can be moved to an adjacent time or reconfirmed unchanged).
- Only the owning user can cancel or reschedule a booking (`BookingPolicy`); the response is `403` otherwise.
