<?php
/**
 * app/views/booking/_form.php
 *
 * The booking form panel. Included inside booking/index.php only when
 * `$mode === 'book'`. State (service / date / time) flows in from the
 * outer Alpine `bookingApp()` via hidden inputs bound with `:value`.
 *
 * Floating labels: each <input> uses placeholder=" " so CSS can detect
 * "has content" via :not(:placeholder-shown); the label is its sibling
 * and moves on focus or when filled.
 */
?>
<header class="form-head">
    <button type="button"
            class="btn-back"
            @click="view = 'slots'"
            aria-label="Back to time selection">
        <i data-lucide="arrow-left" aria-hidden="true"></i>
    </button>
    <div>
        <h2 class="slots-title" style="font-size: 1.4rem;">Your details</h2>
        <p class="slots-sub">
            <span x-text="selectedDateLabel"></span>
            <span aria-hidden="true"> &middot; </span>
            <strong x-text="selectedSummary"></strong>
        </p>
    </div>
</header>

<form action="/book" method="post" novalidate
      x-data="{ submitting: false }"
      @submit="submitting = true">
    <?= csrf_field() ?>
    <input type="hidden" name="service_id" :value="selectedServiceId">
    <input type="hidden" name="date"       :value="selectedDate">
    <input type="hidden" name="time"       :value="selectedSlot">

    <div class="float-field">
        <input type="text"
               id="bf-name"
               name="name"
               placeholder=" "
               required
               minlength="2"
               maxlength="150"
               autocomplete="name"
               value="<?= old('name', current_user_name() ?? '') ?>"
               class="<?= errors('name') !== null ? 'is-invalid' : '' ?>">
        <label for="bf-name">Full name</label>
        <?php if ($msg = errors('name')): ?>
            <p class="field-error"><?= e($msg) ?></p>
        <?php endif ?>
    </div>

    <div class="float-field">
        <input type="email"
               id="bf-email"
               name="email"
               placeholder=" "
               required
               autocomplete="email"
               value="<?= old('email') ?>"
               class="<?= errors('email') !== null ? 'is-invalid' : '' ?>">
        <label for="bf-email">Email</label>
        <?php if ($msg = errors('email')): ?>
            <p class="field-error"><?= e($msg) ?></p>
        <?php endif ?>
    </div>

    <div class="float-field">
        <input type="tel"
               id="bf-phone"
               name="phone"
               placeholder=" "
               required
               autocomplete="tel"
               inputmode="tel"
               value="<?= old('phone') ?>"
               class="<?= errors('phone') !== null ? 'is-invalid' : '' ?>">
        <label for="bf-phone">Phone</label>
        <?php if ($msg = errors('phone')): ?>
            <p class="field-error"><?= e($msg) ?></p>
        <?php else: ?>
            <p class="muted" style="font-size: 0.8rem; margin: 4px 0 0;">e.g. +63 912 345 6789 (international numbers welcome)</p>
        <?php endif ?>
    </div>

    <div class="float-field float-field-area"
         x-data="{ count: <?= mb_strlen((string) ($_SESSION['_old']['notes'] ?? '')) ?> }">
        <textarea id="bf-notes"
                  name="notes"
                  placeholder=" "
                  maxlength="500"
                  @input="count = $event.target.value.length"
                  class="<?= errors('notes') !== null ? 'is-invalid' : '' ?>"><?= e((string) ($_SESSION['_old']['notes'] ?? '')) ?></textarea>
        <label for="bf-notes">Notes or special requests (optional)</label>
        <div class="float-counter"><span x-text="count"></span> / 500</div>
        <?php if ($msg = errors('notes')): ?>
            <p class="field-error" style="margin-top: 18px;"><?= e($msg) ?></p>
        <?php endif ?>
    </div>

    <label class="terms-row">
        <input type="checkbox"
               name="terms"
               value="1"
               required
               <?= !empty($_SESSION['_old']['terms']) ? 'checked' : '' ?>>
        <span>I agree to the booking terms.</span>
    </label>
    <?php if ($msg = errors('terms')): ?>
        <p class="field-error" style="margin: -10px 0 12px;"><?= e($msg) ?></p>
    <?php endif ?>

    <button type="submit"
            class="btn btn-accent btn-block btn-confirm"
            :disabled="submitting"
            :aria-busy="submitting">
        <span x-show="!submitting">Confirm booking</span>
        <span x-show="submitting" x-cloak class="confirm-busy">
            <span class="spinner" aria-hidden="true"></span> Confirming&hellip;
        </span>
    </button>
</form>
