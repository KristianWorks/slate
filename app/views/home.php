<?php
/**
 * app/views/home.php
 *
 * Public marketing homepage. Five sections, generous whitespace, calm motion.
 * No gradients, no stock photos, no "elevate your business" copy.
 *
 * Authoring note (read this once):
 *   The footer credits two placeholder values — replace before deploying.
 *   Either edit them here directly, or add SITE_AUTHOR / SITE_GITHUB_URL
 *   to your .env file and they'll flow through automatically.
 */

$appName = (string) ($_ENV['APP_NAME'] ?? 'Slate');
$author = (string) ($_ENV['SITE_AUTHOR']     ?? 'your name');
$github = (string) ($_ENV['SITE_GITHUB_URL'] ?? 'https://github.com/yourhandle/slate');
?>

<?php if (is_demo_mode()): ?>
    <?php require base_path('app/views/partials/demo_banner.php'); ?>
<?php endif ?>

<!-- ============================================================
     HERO
     ============================================================ -->
<section class="home-hero" aria-labelledby="hero-h">
    <div class="home-hero-copy">
        <h1 id="hero-h" class="home-headline">
            Booking,<br>finally beautiful.
        </h1>
        <p class="home-lede">
            <?= e($appName) ?> is the appointment system your business deserves &mdash;
            quiet, considered, and built for the way people actually book.
        </p>
        <div class="home-cta-row">
            <a href="/book" class="btn btn-accent btn-lg">Book now</a>
            <a href="/book" class="link-cta">See it live <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>

    <div class="home-hero-art-wrap" aria-hidden="true">
        <!-- Mini booking calendar — pure SVG + CSS animations -->
        <svg class="hero-art" viewBox="0 0 480 480" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="A miniature booking calendar with animated time slots">
            <!-- Card -->
            <rect x="20" y="20" width="440" height="440" rx="22" fill="#FFFFFF" stroke="#E8E4DD" stroke-width="1"/>

            <!-- Header: month + chevrons -->
            <text x="48" y="70" font-family="'Fraunces', Georgia, serif" font-size="22" font-weight="500" fill="#1A1A1A">May 2026</text>
            <g stroke="#5A5A5A" stroke-width="1.5" stroke-linecap="round" fill="none">
                <path d="M404 64 L397 70 L404 76"/>
                <path d="M428 64 L435 70 L428 76"/>
            </g>

            <!-- Day-of-week row -->
            <g font-family="'Inter', sans-serif" font-size="9" font-weight="500" fill="#5A5A5A" text-anchor="middle" letter-spacing="0.8">
                <text x="76"  y="106">SUN</text>
                <text x="132" y="106">MON</text>
                <text x="188" y="106">TUE</text>
                <text x="244" y="106">WED</text>
                <text x="300" y="106">THU</text>
                <text x="356" y="106">FRI</text>
                <text x="412" y="106">SAT</text>
            </g>

            <!-- Date grid -->
            <g font-family="'Inter', sans-serif" font-size="14" fill="#1A1A1A" text-anchor="middle">
                <!-- Row 1: ___ ___ ___ ___ 1 2 3 -->
                <text x="300" y="148">1</text>
                <text x="356" y="148">2</text>
                <text x="412" y="148">3</text>

                <!-- Row 2: 4 5(today) 6 7 8(avail) 9 10(avail) -->
                <text x="76"  y="196">4</text>
                <!-- TODAY: thin ring -->
                <circle cx="132" cy="190" r="14" fill="none" stroke="#1A1A1A" stroke-width="1"/>
                <text x="132" y="196">5</text>
                <text x="188" y="196">6</text>
                <text x="244" y="196">7</text>
                <text x="300" y="196">8</text>
                <circle class="hero-dot d1" cx="300" cy="208" r="2" fill="#C84B31"/>
                <text x="356" y="196">9</text>
                <text x="412" y="196">10</text>
                <circle class="hero-dot d2" cx="412" cy="208" r="2" fill="#C84B31"/>

                <!-- Row 3: 11 12(avail) 13 14 15(SELECTED) 16(avail) 17 -->
                <text x="76"  y="244">11</text>
                <text x="132" y="244">12</text>
                <circle class="hero-dot d3" cx="132" cy="256" r="2" fill="#C84B31"/>
                <text x="188" y="244">13</text>
                <text x="244" y="244">14</text>
                <!-- SELECTED day -->
                <circle class="hero-selected" cx="300" cy="240" r="20" fill="#C84B31"/>
                <text x="300" y="244" fill="#FFFFFF" font-weight="500">15</text>
                <text x="356" y="244">16</text>
                <circle class="hero-dot d4" cx="356" cy="256" r="2" fill="#C84B31"/>
                <text x="412" y="244">17</text>

                <!-- Row 4: 18 19 20(avail) 21 22 23(avail) 24 -->
                <text x="76"  y="292">18</text>
                <text x="132" y="292">19</text>
                <text x="188" y="292">20</text>
                <circle class="hero-dot d5" cx="188" cy="304" r="2" fill="#C84B31"/>
                <text x="244" y="292">21</text>
                <text x="300" y="292">22</text>
                <text x="356" y="292">23</text>
                <circle class="hero-dot d6" cx="356" cy="304" r="2" fill="#C84B31"/>
                <text x="412" y="292">24</text>

                <!-- Row 5: 25 26 27 28(avail) 29 30 31 -->
                <text x="76"  y="340">25</text>
                <text x="132" y="340">26</text>
                <text x="188" y="340">27</text>
                <text x="244" y="340">28</text>
                <circle class="hero-dot d7" cx="244" cy="352" r="2" fill="#C84B31"/>
                <text x="300" y="340">29</text>
                <text x="356" y="340">30</text>
                <text x="412" y="340">31</text>
            </g>

            <!-- Divider -->
            <line x1="40" y1="372" x2="440" y2="372" stroke="#E8E4DD" stroke-width="1"/>

            <!-- Slot section label -->
            <text x="48" y="394" font-family="'Inter', sans-serif" font-size="9" font-weight="500" fill="#5A5A5A" letter-spacing="1">AVAILABLE TIMES &middot; THU MAY 15</text>

            <!-- Animated slot pills -->
            <g class="hero-slot s1">
                <rect x="48" y="406" width="120" height="44" rx="10" fill="#FAF8F5" stroke="#E8E4DD" stroke-width="1"/>
                <text x="60" y="426" font-family="'Fraunces', Georgia, serif" font-size="15" font-weight="500" fill="#1A1A1A">9:30 AM</text>
                <text x="60" y="441" font-family="'Inter', sans-serif" font-size="9" fill="#5A5A5A">45 min</text>
            </g>
            <g class="hero-slot s2">
                <rect x="180" y="406" width="120" height="44" rx="10" fill="#FAF8F5" stroke="#E8E4DD" stroke-width="1"/>
                <text x="192" y="426" font-family="'Fraunces', Georgia, serif" font-size="15" font-weight="500" fill="#1A1A1A">11:00 AM</text>
                <text x="192" y="441" font-family="'Inter', sans-serif" font-size="9" fill="#5A5A5A">45 min</text>
            </g>
            <g class="hero-slot s3">
                <rect x="312" y="406" width="120" height="44" rx="10" fill="#FBEDE8" stroke="#C84B31" stroke-width="1.5"/>
                <text x="324" y="426" font-family="'Fraunces', Georgia, serif" font-size="15" font-weight="500" fill="#1A1A1A">2:15 PM</text>
                <text x="324" y="441" font-family="'Inter', sans-serif" font-size="9" fill="#A23E26">45 min</text>
            </g>
        </svg>
    </div>
</section>


<!-- ============================================================
     HOW IT WORKS
     ============================================================ -->
<section class="home-section" data-fade aria-labelledby="how-h">
    <div class="home-section-inner">
        <header class="home-section-head">
            <h2 id="how-h" class="home-section-title">How it works</h2>
            <p class="home-section-sub">Three steps. No accounts to create, no app to install.</p>
        </header>

        <div class="step-grid">
            <article class="step-card">
                <div class="step-num" aria-hidden="true">01</div>
                <h3 class="step-title">Choose a service</h3>
                <p class="step-body">
                    Pick from your business&rsquo;s real services and durations
                    &mdash; no generic &ldquo;consultation&rdquo; placeholders.
                </p>
            </article>

            <article class="step-card">
                <div class="step-num" aria-hidden="true">02</div>
                <h3 class="step-title">Pick your time</h3>
                <p class="step-body">
                    See real availability in your time zone, with morning
                    and afternoon grouped so the right slot is easy to find.
                </p>
            </article>

            <article class="step-card">
                <div class="step-num" aria-hidden="true">03</div>
                <h3 class="step-title">Get confirmation</h3>
                <p class="step-body">
                    A calendar-ready link arrives in your inbox seconds later.
                    Cancel or reschedule from the same email, any time.
                </p>
            </article>
        </div>
    </div>
</section>


<!-- ============================================================
     WHAT MAKES IT DIFFERENT
     ============================================================ -->
<section class="home-section home-section-alt" data-fade aria-labelledby="why-h">
    <div class="home-section-inner">
        <header class="home-section-head">
            <h2 id="why-h" class="home-section-title">What makes it different</h2>
            <p class="home-section-sub">
                Small details, taken seriously. The kind of things you only
                notice when they&rsquo;re missing.
            </p>
        </header>

        <div class="feature-grid">
            <article class="feature-card">
                <h3 class="feature-title">Real-time availability</h3>
                <p class="feature-body">
                    Slots are re-checked the instant you click &ldquo;Continue&rdquo;.
                    Two people racing for the same time? Only one wins, the other
                    gets a clear nudge to pick again.
                </p>
            </article>

            <article class="feature-card">
                <h3 class="feature-title">Smart reminders</h3>
                <p class="feature-body">
                    A confirmation in seconds, a tomorrow-reminder in their inbox,
                    and a self-serve cancel link in both. Your phone stops ringing.
                </p>
            </article>

            <article class="feature-card">
                <h3 class="feature-title">Easy reschedule</h3>
                <p class="feature-body">
                    One link from the confirmation email opens the calendar
                    back up &mdash; with the old time crossed out. No phone calls,
                    no email threads.
                </p>
            </article>

            <article class="feature-card">
                <h3 class="feature-title">No app required</h3>
                <p class="feature-body">
                    Everything works in the browser, on the smallest phone,
                    even with patchy signal. Nothing to install, nothing to
                    update, no permissions to grant.
                </p>
            </article>
        </div>
    </div>
</section>


<!-- ============================================================
     LIVE DEMO CALLOUT
     ============================================================ -->
<section class="home-section home-demo" data-fade aria-labelledby="demo-h">
    <div class="home-section-inner home-demo-inner">
        <div>
            <h2 id="demo-h" class="home-section-title">See it in action.</h2>
            <p class="home-section-sub" style="max-width: 480px;">
                Book a sample 15-minute consultation to test the flow end-to-end &mdash;
                the calendar, the form, the confirmation email, even the cancel link.
            </p>
        </div>
        <div>
            <a href="/book" class="btn btn-accent btn-lg">Try the booking flow <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</section>


<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="home-footer" data-fade>
    <div class="home-section-inner home-footer-inner">
        <div class="home-footer-brand">
            <a href="/" class="home-footer-name"><?= e($appName) ?></a>
            <p class="home-footer-tagline">Quiet bookings, considered design.</p>
        </div>

        <nav class="home-footer-nav" aria-label="Footer">
            <a href="/">Home</a>
            <a href="/book">Book</a>
            <?php if (current_user_id() === null): ?>
                <a href="/login">Sign in</a>
            <?php elseif (current_user_role() === 'admin'): ?>
                <a href="/admin">Admin</a>
            <?php else: ?>
                <a href="/appointments">My appointments</a>
            <?php endif ?>
        </nav>

        <div class="home-footer-meta">
            Built by <a href="<?= e($github) ?>" rel="noopener" target="_blank"><?= e($author) ?></a>
            &mdash; view source on
            <a href="<?= e($github) ?>" rel="noopener" target="_blank">GitHub</a>.
        </div>

        <div class="home-footer-copy">&copy; <?= date('Y') ?> <?= e($appName) ?></div>
    </div>
</footer>


<script>
    // Fade each section in as it scrolls into view.
    // The hero is excluded — it's already on screen at load time.
    (function () {
        const targets = document.querySelectorAll('[data-fade]');
        if (targets.length === 0) return;
        if (!('IntersectionObserver' in window)) {
            targets.forEach(el => el.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -8% 0px',
        });
        targets.forEach(el => io.observe(el));
    })();
</script>
