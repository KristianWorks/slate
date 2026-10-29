<?php
/**
 * app/views/emails/admin_notification.php
 *
 * Goes to the business owner on every new booking. Denser than the customer
 * emails — facts only, no marketing voice.
 */

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

$details = [
    ['label' => 'Customer', 'value' => e($name)
        . '<br><a href="mailto:' . e($email) . '" style="color:#1A1A1A;">' . e($email) . '</a>'
        . '<br><a href="tel:' . e($phone) . '" style="color:#1A1A1A;">' . e($phone) . '</a>'],
    ['label' => 'Service',  'value' => e($serviceName)],
    ['label' => 'When',     'value' => e($startsAt->format('l, F j, Y · g:i A'))
        . ' <span style="color:#5A5A5A;">(' . (int) $duration . ' min)</span>'],
];
if ($location !== '') {
    $details[] = ['label' => 'Location', 'value' => e($location)];
}
if ($notes !== '') {
    $details[] = ['label' => 'Notes', 'value' => nl2br(e($notes))];
}
$details[] = [
    'label' => 'Reference',
    'value' => '<span style="font-family:ui-monospace,Menlo,Consolas,monospace; letter-spacing:0.06em;">' . e($reference) . '</span>',
];

$primaryCta   = ['url' => $appUrl . '/admin/appointments', 'label' => 'Open in admin'];
$secondaryCta = null;

$footerHtml = 'This notification was sent automatically because a customer booked an appointment.';

ob_start();
?>
<p style="margin:0 0 8px 0; color:#5A5A5A; font-size:13px;">New booking just came in.</p>
<?php
$bodyHtml = ob_get_clean();

include __DIR__ . '/_layout.php';
