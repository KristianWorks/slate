<?php
/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $bookUrl */
/** @var string $businessName */
?>
<?= $businessName ?>


YOUR BOOKING IS CANCELLED

Hi <?= $name ?>,

Your appointment has been cancelled. If this was a mistake, you can book again any time.

  Service:   <?= $serviceName ?> (cancelled)
  Date:      <?= $startsAt->format('l, F j, Y') ?> (cancelled)
  Time:      <?= $startsAt->format('g:i A') ?> (cancelled)
  Reference: <?= $reference ?>


Book again:
<?= $bookUrl ?>


— <?= $businessName ?>
