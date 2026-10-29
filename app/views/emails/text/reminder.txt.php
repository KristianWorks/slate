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


SEE YOU TOMORROW

Hi <?= $name ?>,

Just a quick reminder — your appointment with <?= $businessName ?> is tomorrow.

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


Need to cancel or move it?
<?= $cancelUrl ?>


— <?= $businessName ?>
