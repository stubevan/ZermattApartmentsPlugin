# Zermatt Apartments Website Support

WordPress plugin for https://zermattapartments.ch, which uses [WP Booking System Premium](https://www.wpbookingsystem.com) (WPBS).

## What it does

- **Booking form checks:** the two email addresses must match, and guest numbers must not exceed the apartment's limit (`includes/booking-validation.php`; form and field IDs are hard-coded).
- **Deposit Received email:** guests are emailed when the owner marks their deposit as paid (see below).
- **Email backfill:** queues arrival, checkout and review emails that WPBS failed to schedule.
- **Past guest picker:** on wp-admin Add Booking, copies a returning guest's details from their latest booking (see below).

IDs (calendars, forms, emails) live in `includes/config.php`.

## Deposit Received email

WPBS already sends every notification with the trigger *"When the final payment is made or booking is marked
as paid"* whose conditions match, **once per booking**, right after a deposit is marked as paid. It does this in
`wpbs_action_ajax_booking_part_payment_update_status()` in `modules/pricing/functions-part-payments.php`, guarded by
the booking meta `deposit_paid_email_sent`. So the email is a normal form notification, `07a - Deposit Received`,
with that trigger and the condition *Payment Status = Deposit Paid*. It needs no runtime code.

`wp zermatt deposit-email install` creates it on each apartment's form, copied from the "07 - Final Payment
Received" email, with EN/FR/DE text from `templates/deposit-received.php`. It also adds the same text to
*Settings → Email → Email Templates* so it can be sent by hand (booking → Email Customer → Load Template).
It skips anything that already exists, so it won't overwrite edits made in wp-admin.

Two template systems are involved, so changing the wording means editing both: the `07a` notification on each form and the manual template.

## Payment received email (once per booking)

`includes/paid-email.php`. WP Booking System sends each apartment's "07 - Final Payment Received" email (IDs =
`final_payment_email` in `includes/config.php`) when a bank-transfer booking is marked paid. This adds:
- **Once only.** The first successful send sets booking meta `za_paid_email_sent`. After that, the email's "send to"
  reads as empty for that booking, so WPBS skips it without sending or logging. (Unmarking and re-marking a final
  payment used to send it again.)
- **Payment on arrival.** Marking those bookings paid now sends the same email; WPBS on its own sends nothing.

`wp zermatt paid-email status [--booking=ID]`, `mark-history [--yes]` (flags bookings whose email log already has a
"07 -" email; dry run without `--yes`), `reset-flag --booking=ID`, `templates [--dry-run]` (copies the email's
EN/FR/DE text into the manual "Load Template" list).

## Coupons: pay cleaning only

`includes/coupons.php`: WP Booking System coupons discount either the nights only or the whole total. For the coupon
IDs in `ZA_COUPONS_PAY_CLEANING_ONLY` the discount is changed to "everything except the cleaning
fee", so the guest pays only cleaning (no nights, no tourist tax). The coupon itself must be a 100% percentage coupon
set to apply to the whole total; with any other setting the rule does nothing. Coupons are listed by ID, never
by code: this repo is public and the codes give free stays.

## Past guest picker (Add Booking)

`includes/customer-prefill.php` and `assets/customer-prefill.js`. WPBS can't start a booking from an existing guest,
so the admin Add Booking form (manual bookings only; the public form is untouched) gets a "Copy details from a
past guest" box above the fields. Typing filters a list of past guests (latest booking per email, across the
calendars in `ZA_PAST_GUEST_CALENDARS`, trashed bookings skipped). Choosing one copies the fields in
`ZA_PREFILL_FIELDS` (names, both email boxes, address, phone, guest numbers, guest list) from that booking; special
requests, discount code and payment method are not copied. Everything stays editable. The booking forms share field
IDs, so a guest from one apartment fills the other's form.

The list and the AJAX lookup (`za_past_guest_details`) are only available to logged-in users who can access the
form's calendar (`wpbs_current_user_can_access_calendar()`), with a nonce. The repo holds no guest data.

## WP-CLI commands

Run on the server: `ssh zermattapartments "cd wordpress && wp --skip-plugins=wordfence <command>"`

| Command | What it does |
|---|---|
| `zermatt deposit-email install [--dry-run] [--enable]` | Create the deposit notifications (disabled unless `--enable`) and manual templates |
| `zermatt deposit-email status [--booking=<id>]` | Show the notifications/templates, and a booking's deposit state |
| `zermatt deposit-email reset-flag --booking=<id>` | Let the deposit email send again for that booking |
| `zermatt backfill-emails [--yes] [--email=<ids>] [--booking=<id>]` | Queue missing timed emails (dry run without `--yes`) |

## Deploying

The live plugin folder is a git checkout of this repo. From a clean local clone:

```
git push origin <branch>          # and/or: git tag vX.Y && git push origin vX.Y
bin/deploy.sh vX.Y                # or origin/<branch>
```

It fetches on the server, checks out the ref, lint-checks with PHP 8.3, rolls back if that fails, then checks the
plugin status and the home page. It prints the previous ref; `bin/deploy.sh <that ref>` rolls back.

wp-cli on the server runs PHP 8.0, so the code must stay PHP 8.0-compatible.

## tools/

One-off scripts kept for the record (not loaded by the plugin). They run with `wp eval-file`. The email
backfills are now the `backfill-emails` command.
