<?php
/**
 * app/views/emails/reminder.php
 */

/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
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

$primaryCta   = ['url' => $viewUrl,   'label' => 'View booking'];
$secondaryCta = ['url' => $cancelUrl, 'label' => 'Need to cancel or move it?'];

ob_start();
?>
<p style="margin:0 0 14px 0;">Hi <?= e($name) ?>,</p>
<p style="margin:0 0 14px 0;">
    Just a quick reminder &mdash; your appointment with <strong><?= e($businessName) ?></strong> is tomorrow.
</p>
<?php
$bodyHtml = ob_get_clean();

include __DIR__ . '/_layout.php';
