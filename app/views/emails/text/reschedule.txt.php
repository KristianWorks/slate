<?php
/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $viewUrl */
/** @var string $cancelUrl */
/** @var string $oldStartsAtLong */
/** @var string $businessName */
?>
<?= $businessName ?>


YOUR BOOKING HAS MOVED

Hi <?= $name ?>,

Your appointment has been moved. Here are the updated details:

  Service:   <?= $serviceName ?>


  Was:       <?= $oldStartsAtLong ?>

  Now:       <?= $startsAt->format('l, F j · g:i A') ?>


  Duration:  <?= (int) $duration ?> min
<?php if ($location !== ''): ?>
  Location:  <?= $location ?>

<?php endif ?>
  Reference: <?= $reference ?>


View booking:
<?= $viewUrl ?>


Cancel or reschedule:
<?= $cancelUrl ?>


— <?= $businessName ?>
