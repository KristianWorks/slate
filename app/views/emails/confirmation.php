<?php
/**
 * app/views/emails/confirmation.php
 */

/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $notes */
/** @var string $viewUrl */
/** @var string $cancelUrl */

$details = [
    ['label' => 'Service',   'value' => e($serviceName)],
    ['label' => 'Date',      'value' => e($startsAt->format('l, F j, Y'))],
    ['label' => 'Time',      'value' => e($startsAt->format('g:i A')) . ' <span style="color:#5A5A5A;">(' . e($tzName) . ')</span>'],
    ['label' => 'Duration',  'value' => (int) $duration . ' min'],
];
if ($location !== '') {
    $details[] = ['label' => 'Location', 'value' => e($location)];
}
$details[] = [
    'label' => 'Reference',
    'value' => '<span style="font-family:ui-monospace,Menlo,Consolas,monospace; letter-spacing:0.06em;">' . e($reference) . '</span>',
];
if ($notes !== '') {
    $details[] = ['label' => 'Your notes', 'value' => nl2br(e($notes))];
}

$primaryCta   = ['url' => $viewUrl,   'label' => 'View booking'];
$secondaryCta = ['url' => $cancelUrl, 'label' => 'Cancel or reschedule'];

ob_start();
?>
<p style="margin:0 0 14px 0;">Hi <?= e($name) ?>,</p>
<p style="margin:0 0 14px 0;">
    Your appointment with <strong><?= e($businessName) ?></strong> is confirmed.
    We&rsquo;re looking forward to seeing you.
</p>
<?php
$bodyHtml = ob_get_clean();

include __DIR__ . '/_layout.php';
