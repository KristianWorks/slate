<?php
/**
 * lang/en.php
 *
 * English (default) strings. Every user-facing string in the app should
 * have a key here. Keys use snake_case; sections are top-level keys.
 *
 * To add a new language: copy this file to `<locale>.php`, translate the
 * values (leave keys + `:placeholder` tokens unchanged), then set
 * APP_LOCALE in .env. See lang/README.md for details.
 */

declare(strict_types=1);

return [

    /* ============================================================
     * Navigation
     * ============================================================ */
    'nav' => [
        'home'              => 'Home',
        'book'              => 'Book',
        'sign_in'           => 'Sign in',
        'create_account'    => 'Create account',
        'sign_out'          => 'Sign out',
        'my_appointments'   => 'My appointments',
        'admin'             => 'Admin',
    ],

    /* ============================================================
     * Auth (login, register)
     * ============================================================ */
    'auth' => [
        'login_title'           => 'Welcome back',
        'login_sub'             => 'Sign in to manage your appointments.',
        'email_label'           => 'Email',
        'password_label'        => 'Password',
        'sign_in_button'        => 'Sign in',
        'no_account_prompt'     => "Don't have an account?",
        'no_account_link'       => 'Create one',

        'register_title'        => 'Create your account',
        'register_sub'          => 'A few details and you\'re set to book.',
        'name_label'            => 'Name',
        'confirm_password_label'=> 'Confirm password',
        'password_hint'         => 'At least 8 characters.',
        'create_button'         => 'Create account',
        'already_account_prompt'=> 'Already have an account?',
        'already_account_link'  => 'Sign in',

        'invalid_credentials'   => 'Invalid email or password.',
        'fix_errors_below'      => 'Please fix the errors below.',
        'welcome_back_flash'    => 'Welcome back, :name.',
        'welcome_new_flash'     => 'Welcome to Slate, :name.',
        'signed_out_flash'      => 'You have been signed out.',
    ],

    /* ============================================================
     * Public homepage
     * ============================================================ */
    'home' => [
        'hero_title_line_1' => 'Booking,',
        'hero_title_line_2' => 'finally beautiful.',
        'hero_lede'         => ':app is the appointment system your business deserves — quiet, considered, and built for the way people actually book.',
        'cta_book_now'      => 'Book now',
        'cta_see_it_live'   => 'See it live',

        'how_title'         => 'How it works',
        'how_sub'           => 'Three steps. No accounts to create, no app to install.',
        'step_1_title'      => 'Choose a service',
        'step_1_body'       => 'Pick from your business\'s real services and durations — no generic "consultation" placeholders.',
        'step_2_title'      => 'Pick your time',
        'step_2_body'       => 'See real availability in your time zone, with morning and afternoon grouped so the right slot is easy to find.',
        'step_3_title'      => 'Get confirmation',
        'step_3_body'       => 'A calendar-ready link arrives in your inbox seconds later. Cancel or reschedule from the same email, any time.',

        'why_title'         => 'What makes it different',
        'why_sub'           => 'Small details, taken seriously. The kind of things you only notice when they\'re missing.',

        'demo_title'        => 'See it in action.',
        'demo_sub'          => 'Book a sample 15-minute consultation to test the flow end-to-end — the calendar, the form, the confirmation email, even the cancel link.',
        'demo_cta'          => 'Try the booking flow',

        'footer_tagline'    => 'Quiet bookings, considered design.',
        'footer_credit'     => 'Built by :author — view source on :github.',
    ],

    /* ============================================================
     * Booking flow (calendar, form, confirmation, cancel)
     * ============================================================ */
    'booking' => [
        'page_title'            => 'Book an appointment',
        'page_sub'              => 'Pick a service, choose a date, then a time.',

        'available_times'       => 'Available times',
        'choose_date'           => 'Choose a date',
        'select_date_prompt'    => 'Select a date to see available times.',
        'fully_booked'          => 'Fully booked. Try another day.',
        'morning'               => 'Morning',
        'afternoon'             => 'Afternoon',
        'continue_button'       => 'Continue',
        'move_to_this_time'     => 'Move to this time',

        'form_title'            => 'Your details',
        'full_name_label'       => 'Full name',
        'email_label'           => 'Email',
        'phone_label'           => 'Phone',
        'notes_label'           => 'Notes or special requests (optional)',
        'terms_label'           => 'I agree to the booking terms.',
        'confirm_button'        => 'Confirm booking',
        'confirming'            => 'Confirming…',

        'confirmed_title'       => 'You\'re booked',
        'confirmed_sub'         => 'We\'ve sent the details to your email.',
        'cancelled_title'       => 'Booking cancelled',
        'cancelled_sub'         => 'This appointment has been cancelled.',

        'detail_service'        => 'Service',
        'detail_date'           => 'Date',
        'detail_time'           => 'Time',
        'detail_location'       => 'Location',
        'detail_reference'      => 'Reference',
        'detail_notes'          => 'Notes',

        'add_to_calendar'       => 'Add to calendar',
        'cancel_or_reschedule'  => 'Cancel or reschedule',
        'book_again'            => 'Book again',
        'footnote'              => 'Need to make a change? Click cancel or reschedule above, or reply to your confirmation email.',

        'cancel_title'          => 'Cancel this appointment?',
        'cancel_sub'            => 'This can\'t be undone, but you can always book again.',
        'yes_cancel'            => 'Yes, cancel this appointment',
        'keep_appointment'      => 'Keep my appointment',
        'reschedule_instead'    => 'Reschedule instead',

        'reschedule_title'      => 'Reschedule your appointment',
        'reschedule_sub'        => 'Pick a new date and time. We\'ll email you a fresh confirmation.',
        'currently_scheduled'   => 'Currently scheduled for :when',
        'keep_current'          => 'Keep current',

        'slot_taken'            => 'Sorry, that slot was just taken. Please pick another time.',
        'slot_no_longer'        => 'That time is no longer available. Please pick another.',
        'cancelled_flash'       => 'Your appointment has been cancelled.',
        'moved_flash'           => 'Your appointment has been moved.',
    ],

    /* ============================================================
     * Admin (sidebar labels, dashboard, common actions)
     * ============================================================ */
    'admin' => [
        'dashboard'             => 'Dashboard',
        'appointments'          => 'Appointments',
        'services'              => 'Services',
        'schedule'              => 'Schedule',
        'blocked_dates'         => 'Blocked dates',
        'settings'              => 'Settings',
        'email_test'            => 'Email test',
        'logs'                  => 'Logs',

        'dashboard_sub'         => 'A quick look at what\'s happening today.',
        'today_count'           => 'Appointments today',
        'this_week'             => 'This week',
        'revenue_month'         => 'Revenue this month',
        'no_upcoming'           => 'No upcoming appointments',
        'welcome_admin'         => 'Welcome back, :name.',

        'export_csv'            => 'Export CSV',
        'print'                 => 'Print',
    ],

    /* ============================================================
     * Errors
     * ============================================================ */
    'errors' => [
        'not_found_title'       => 'Lost your way?',
        'not_found_sub'         => 'This page doesn\'t exist or was moved.',
        'server_error_title'    => 'Something broke.',
        'server_error_sub'      => 'We\'re on it. Please try again in a moment.',
        'server_error_ref'      => 'Reference',
        'rate_limit_title'      => 'Too many attempts.',
        'rate_limit_sub'        => 'For your protection we\'re briefly pausing this action. Please try again in :minutes :unit.',
        'minute_singular'       => 'minute',
        'minute_plural'         => 'minutes',
        'back_home'             => 'Back to home',
    ],

    /* ============================================================
     * Email subject lines + headlines
     * ============================================================ */
    'emails' => [
        'confirmation_subject'  => 'Your booking is confirmed · :when',
        'confirmation_headline' => 'You\'re booked',
        'cancellation_subject'  => 'Booking cancelled · :when',
        'cancellation_headline' => 'Your booking is cancelled',
        'reschedule_subject'    => 'Booking moved · now :when',
        'reschedule_headline'   => 'Your booking has moved',
        'reminder_subject'      => 'Reminder: :time tomorrow',
        'reminder_headline'     => 'See you tomorrow',
        'admin_subject'         => 'New booking: :name · :when',
        'admin_headline'        => 'New booking',
    ],

    /* ============================================================
     * Common actions / generic labels
     * ============================================================ */
    'common' => [
        'cancel'                => 'Cancel',
        'save'                  => 'Save',
        'delete'                => 'Delete',
        'edit'                  => 'Edit',
        'close'                 => 'Close',
        'back'                  => 'Back',
        'print'                 => 'Print',
        'loading'               => 'Loading…',
    ],

];
