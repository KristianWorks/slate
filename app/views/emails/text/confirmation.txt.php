<?php
/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $viewUrl */
/** @var string $cancelUrl */
/** @var string $businessName */
/** @var string $tzName */
?>
<?= $businessName ?>


YOUR BOOKING IS CONFIRMED

Hi <?= $name ?>,

Your appointment is confirmed. We're looking forward to seeing you.

  Service:   <?= $serviceName ?>

  Date:      <?= $startsAt->format('l, F j, Y') ?>

  Time:      <?= $startsAt->format('g:i A') ?> (<?= $tzName ?>)
  Duration:  <?= (int) $duration ?> min
<?php if ($location !== ''): ?>
  Location:  <?= $location ?>

<?php endif ?>
  Reference: <?= $reference ?>


View booking:
<?= $viewUrl ?>


Cancel or reschedule:
<?= $cancelUrl ?>


If you didn't book this, simply reply to let us know.

— <?= $businessName ?>
