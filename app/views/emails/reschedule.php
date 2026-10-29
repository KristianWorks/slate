<?php
/**
 * app/views/emails/reschedule.php
 *
 * Shows the OLD time struck through and the NEW time clearly highlighted.
 */

/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $viewUrl */
/** @var string $cancelUrl */
/** @var string $oldStartsAtLong */

$details = [
    ['label' => 'Service',   'value' => e($serviceName)],
    [
        'label' => 'When',
        'value' =>
            '<s style="color:#5A5A5A;">' . e($oldStartsAtLong) . '</s>'
            . '<br>'
            . '<strong style="color:#C84B31;">' . e($startsAt->format('l, F j · g:i A')) . '</strong>',
    ],
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
$secondaryCta = ['url' => $cancelUrl, 'label' => 'Cancel or reschedule'];

ob_start();
?>
<p style="margin:0 0 14px 0;">Hi <?= e($name) ?>,</p>
<p style="margin:0 0 14px 0;">
    Your appointment has been moved. Here&rsquo;s the updated time:
</p>
<?php
$bodyHtml = ob_get_clean();

include __DIR__ . '/_layout.php';
