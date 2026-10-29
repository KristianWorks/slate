<?php
/** @var string $name */
/** @var string $email */
/** @var string $phone */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $notes */
/** @var string $appUrl */
/** @var string $businessName */
?>
NEW BOOKING — <?= $businessName ?>


Customer: <?= $name ?> <<?= $email ?>>
Phone:    <?= $phone ?>


Service:  <?= $serviceName ?>

When:     <?= $startsAt->format('l, F j, Y · g:i A') ?> (<?= (int) $duration ?> min)
<?php if ($location !== ''): ?>
Location: <?= $location ?>

<?php endif ?>
<?php if ($notes !== ''): ?>
Notes:    <?= $notes ?>

<?php endif ?>
Ref:      <?= $reference ?>


Open in admin:
<?= $appUrl ?>/admin/appointments
