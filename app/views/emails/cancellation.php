<?php
/**
 * app/views/emails/cancellation.php
 */

/** @var string $name */
/** @var string $serviceName */
/** @var \DateTimeImmutable $startsAt */
/** @var int $duration */
/** @var string $location */
/** @var string $reference */
/** @var string $bookUrl */

$details = [
    ['label' => 'Service',   'value' => '<s style="color:#5A5A5A;">' . e($serviceName) . '</s>'],
    ['label' => 'Date',      'value' => '<s style="color:#5A5A5A;">' . e($startsAt->format('l, F j, Y')) . '</s>'],
    ['label' => 'Time',      'value' => '<s style="color:#5A5A5A;">' . e($startsAt->format('g:i A')) . '</s>'],
    ['label' => 'Duration',  'value' => '<s style="color:#5A5A5A;">' . (int) $duration . ' min</s>'],
];
if ($location !== '') {
    $details[] = ['label' => 'Location', 'value' => '<s style="color:#5A5A5A;">' . e($location) . '</s>'];
}
$details[] = [
    'label' => 'Reference',
    'value' => '<span style="font-family:ui-monospace,Menlo,Consolas,monospace; letter-spacing:0.06em; color:#5A5A5A;">' . e($reference) . '</span>',
];

$primaryCta   = ['url' => $bookUrl, 'label' => 'Book again'];
$secondaryCta = null;

ob_start();
?>
<p style="margin:0 0 14px 0;">Hi <?= e($name) ?>,</p>
<p style="margin:0 0 14px 0;">
    Your appointment has been cancelled. If this was a mistake, you can book again at any time.
</p>
<p style="margin:0 0 14px 0; color:#5A5A5A;">
    We&rsquo;d love to see you another day.
</p>
<?php
$bodyHtml = ob_get_clean();

include __DIR__ . '/_layout.php';
