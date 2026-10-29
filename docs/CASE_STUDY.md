# Slate — a case study

*A six-week side project, written up the way I'd present it in an interview.*

---

## The problem

If you survey the booking-software landscape, two clusters dominate:

1. **The functional ones** — Calendly, Acuity, Setmore. Powerful, generic, blue, branded the same as ten thousand other SaaS products. They look like *software*, not like a business.
2. **The pretty ones** — Cal.com, Tidycal. Open-source, well-designed, but they bring a Node + Postgres + React stack with them. The deploy story is "spin up a Kubernetes cluster".

I wanted to see if a third option was possible: **something that feels premium and considered, built with the simplest possible stack.** Specifically, vanilla PHP. No framework, no SPA, no calendar library, no build step.

The hypothesis was that the friction in "premium-feeling" software is usually design and engineering taste, not technology choice — and that PHP 8.2 in particular is a much better language than its reputation suggests.

---

## Process

### Typography

**Inter + Fraunces.** Inter for the body because it's the best free workhorse sans — high x-height, generous counters, designed for screen. Fraunces for display because it has *soul* without being precious. It's an optically-sized variable font: the larger you set it, the more its character emerges (the 96px hero headline reads as architectural, almost monumental; the 22px card titles read as quiet and warm). That kind of typography does the heavy lifting that gradients would otherwise have to fake.

### Colour

**One accent on a cream background.** `#C84B31` (terracotta) on `#FAF8F5` (warm cream). Surfaces are pure white. Borders are `#E8E4DD`. That's the entire palette. Status pills use slightly desaturated versions of standard semantic colours (forest green for confirmed, amber for pending) at the same saturation level so they sit in the palette without shouting.

The one place I revisited: accent text on accent-soft was 4.04:1 — failed WCAG AA for normal-sized text. Introduced a `--color-accent-text: #A23E26` (a hand-darkened variant, 6.4:1) and applied it specifically to the small-text-on-accent-soft pairings.

**No gradients anywhere.** Not even subtle ones. Surfaces are flat colours. Where I needed visual texture (the "closed day" cells in the booking calendar), I used a CSS `repeating-linear-gradient` with hard stops — a pattern, not a colour blend. That kept the visual language consistent: solid colours, deliberate edges.

### The split calendar

The single most important screen, and the one I spent the most time on.

Most booking calendars cram the date picker and time slots into one column. Calendly does this; Cal.com does this; SimplyBook does this. I went with **40/60 horizontal**: the month grid on the left (compact, scannable, less than a third of the viewport on desktop) and the available times on the right (room to breathe, slot cards rendered in serif at 1.3rem).

The slot cards stagger in 30ms per index when a date is selected. The selected date scales `1.05×` and gets a solid accent fill (versus the soft-accent hover state on available days). Closed weekdays get a hard-stop diagonal hatch pattern; one-off blocked dates get the same hatch *plus* a tiny × icon. Same "you can't pick this" information, but it reads as design intent rather than greyed-out chrome.

On mobile (<880px), the layout stacks: calendar first, slot panel below. The keyboard navigation (arrow keys to move focus, Enter to select, Esc to peel back one layer at a time) works identically on both layouts.

---

## Trade-offs

### Vanilla PHP vs Laravel

Laravel would have given me Eloquent, validation, auth, queues, mail, broadcasting — all out of the box. I'd have shipped this in a third of the time. Instead I have a 200-line Router, a 200-line Database singleton, a 200-line Validator, and a 350-line Mailer.

The point of this project was the opposite of "ship fast". It was to **prove I understand what a framework abstracts away**. I can walk an interviewer line-by-line through:

- How PDO's `ATTR_EMULATE_PREPARES = false` actually changes parameter binding
- Why my Router stores routes in registration order and what that means for `/admin/appointments/{id}/panel` vs `/admin/appointments/day/{date}` collision
- What `flock(LOCK_EX)` buys me in the rate limiter, and what it doesn't
- Why the booking transaction needs `SELECT ... FOR UPDATE` and what the InnoDB next-key lock guarantees at REPEATABLE READ

If I'd used Laravel, the answer to all four would have been *"the framework handles it."* That's a worse interview.

### HTMX vs full SPA

The React + Vite + Tailwind stack would have been the conventional choice. HTMX was the contrarian one.

What I bought with HTMX:
- **Zero build step.** The repo IS the deployable artifact. `composer install` and you can serve it.
- **Server-rendered HTML.** Good for SEO. Good for screen readers. Good for fallback (turn off JS, the booking calendar still works as month-paged page reloads).
- **No hydration mismatch.** No "flash of unstyled content" or "wrong tab active for 200ms while React boots".

What I paid:
- **Alpine.js does the small client state.** Modal open/closed, selected slot, calendar selected date. I had to be disciplined about keeping Alpine as a *thin* state layer rather than letting it grow into a Redux replacement.
- **Some duplication between server-rendered initial state and client-side state.** When a customer's POST fails validation, I have to restore the calendar selection in two places (Alpine state init + the day-cell `:class` bindings).

For a booking app — where the server is the source of truth about availability anyway — HTMX is the right shape.

### Custom calendar vs library

I could have used FullCalendar.io and spent 20 minutes integrating it. Instead I spent two days building a server-rendered 42-cell grid with HTMX-swapped month navigation, keyboard arrow-key support, `role="grid"` + `role="gridcell"` accessibility, per-day availability dots that recompute when the customer switches services, and a CSS slide-fade transition tied to the swap direction.

**The code is now legible from first principles.** Anyone reading `app/views/booking/_calendar.php` can see exactly how the cells are computed. That's a much stronger portfolio signal than "I imported a calendar component and styled it."

---

## Challenges

### The slot-booking race condition

The classic concurrency problem for any booking system. Two customers click the same 9:30 AM slot at the same time. Naively:

```
Customer A: SELECT … WHERE starts_at='2026-05-15 09:30' → 0 rows → INSERT
Customer B: SELECT … WHERE starts_at='2026-05-15 09:30' → 0 rows → INSERT
```

Both succeed. Two bookings for one slot. Disaster.

The fix is `SELECT ... FOR UPDATE` inside a transaction:

```php
$db->beginTransaction();
$conflicts = $db->fetchAll(
    "SELECT id FROM appointments
      WHERE status IN ('pending','confirmed')
        AND starts_at < :end
        AND ends_at   > :start
      FOR UPDATE",
    ['start' => $startsAtStr, 'end' => $endsAtStr],
);
if ($conflicts !== []) { $db->rollBack(); /* user sees friendly flash */ }
$db->insert('appointments', [...]);
$db->commit();
```

On InnoDB at REPEATABLE READ (MySQL's default), `FOR UPDATE` takes **next-key locks**: not just the matching rows but the *gap* they would occupy in the index. Two simultaneous transactions probing the same empty window now serialise — the second one's `SELECT` waits, sees the first's inserted row when the wait resolves, hits the conflict branch, and rolls back.

The customer sees: *"Sorry, that slot was just taken. Please pick another time."* The friendly message + the immediate redirect back to the calendar (with the slots already refreshed) make this feel like a soft UX nudge rather than a system error.

This is the single most important piece of correctness in the project, and the reason I'd be uncomfortable shipping a booking system without it.

### Timezone handling

The business has its own timezone (configurable via `settings.timezone`). The customer's browser has another. MySQL stores `DATETIME` without an offset. Getting the right time displayed to the right person was the most subtle part of the project.

The rule I settled on: **everything stored is wall-clock in the business timezone**. The `SlotGenerator` instantiates every `DateTimeImmutable` with the business `DateTimeZone` explicitly. When constructing the customer's confirmation email, the displayed time is in the business timezone with the IANA name shown beside it: *"9:30 AM (America/Toronto)"*.

The one place I convert: the Google Calendar export link. Google's `dates=` URL parameter expects `Ymd\THis\Z` (UTC). I `setTimezone(new DateTimeZone('UTC'))` before formatting so Google interprets it correctly no matter where the recipient lives. The customer in Berlin clicking "Add to Google Calendar" sees the event correctly placed in Central European Time without any browser-side conversion.

It took two read-throughs of every datetime in the codebase to get this right. The reward is that the test scenario — a Toronto-based business taking a booking from a customer using a Berlin-locale browser, with the customer adding it to a Tokyo-time Google account — actually works.

---

## What I'd build next

In rough order of how much I want to:

1. **Stripe payments.** The `appointments.status` enum already includes `pending`. Services with `price > 0` would create a Checkout session instead of confirming immediately; the booking flips to `confirmed` on webhook. `BookingController::store()` has a `TODO: Stripe integration point` comment marking where it goes.

2. **Twilio SMS reminders.** The `Mailer` already abstracts dispatch behind `queueX(int $appointmentId)` static methods. An `SmsService` with the same shape would slot in alongside it; the `SendReminders` cron would call both.

3. **Recurring appointments.** A `appointments.recurrence_rule` column (RFC 5545 RRULE syntax) plus an expansion job that materialises the next 90 days of instances. UI would need a "repeats" widget in the booking form and an "applies to all future" / "only this one" choice in the cancel/reschedule flow.

4. **Multi-staff support.** The biggest schema change of the four. Add `staff_id` to `services`, `appointments`, and `business_hours`. The `SlotGenerator` takes a staff filter. The admin gets a "team" management page.

5. **Public REST API.** `/api/v1/services`, `/api/v1/availability`, `/api/v1/bookings` with key-based auth. The Router supports it today; needs a JSON response helper and an `ApiController` namespace. The booking flow itself would be largely the same code — just a different output adapter.

---

## What I'd tell a hiring manager

Slate is the project I built to answer the question *"can you build production-shaped software without leaning on a framework?"*

The answer is: yes, in PHP 8.2, with three composer dependencies and no build step. The trade-offs are explicit, the security model is documented, and every interesting decision has a sentence's worth of justification next to it in the code.

If you want to see how I think about timezones, race conditions, accessibility, or rate limiting, the source is the case study.
