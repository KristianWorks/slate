<?php
/**
 * app/views/emails/_layout.php
 *
 * Shared email shell. Each template defines:
 *   $title, $headline, $bodyHtml, $details (list<['label','value']>),
 *   $primaryCta (?['url','label']), $secondaryCta (?['url','label']),
 *   plus $businessName, $location, $contactEmail (from Mailer commonVars).
 *
 * Email-client rules followed here:
 *   - Tables for layout (Outlook still hates flexbox).
 *   - Inline styles only. <style> blocks are stripped by Gmail clipping.
 *   - Web-safe fonts with Inter / Fraunces preference if installed.
 *   - No external resources / images / fonts — they're blocked by default.
 *   - Background colour repeated on body AND on the outer table (Outlook).
 *   - Max width 560px with width:100% for fluid mobile.
 */

/** @var string                                                          $title */
/** @var string                                                          $headline */
/** @var string                                                          $bodyHtml */
/** @var string                                                          $businessName */
/** @var string                                                          $location */
/** @var string                                                          $contactEmail */
/** @var list<array{label:string,value:string}>                          $details */
/** @var ?array{url:string,label:string}                                 $primaryCta */
/** @var ?array{url:string,label:string}                                 $secondaryCta */
/** @var ?string                                                         $footerHtml */

$details      = $details      ?? [];
$primaryCta   = $primaryCta   ?? null;
$secondaryCta = $secondaryCta ?? null;
$footerHtml   = $footerHtml   ?? null;
$location     = $location     ?? '';

$fontSans  = "'Inter', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Arial, sans-serif";
$fontSerif = "'Fraunces', Georgia, 'Times New Roman', serif";
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= e($title) ?></title>
</head>
<body style="margin:0; padding:0; background-color:#FAF8F5; font-family:<?= $fontSans ?>; color:#1A1A1A;">
<!-- preheader: short summary visible in inbox previews -->
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
    <?= e($headline) ?> · <?= e($businessName) ?>
</div>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#FAF8F5;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="560" style="max-width:560px; width:100%; background-color:#FFFFFF; border-radius:16px; border:1px solid #E8E4DD;">
                <!-- Brand -->
                <tr>
                    <td style="padding:32px 32px 0 32px;">
                        <div style="font-family:<?= $fontSerif ?>; font-size:20px; font-weight:500; letter-spacing:-0.01em; color:#1A1A1A;">
                            <?= e($businessName) ?>
                        </div>
                    </td>
                </tr>

                <!-- Headline -->
                <tr>
                    <td style="padding:24px 32px 0 32px;">
                        <h1 style="margin:0; font-family:<?= $fontSerif ?>; font-weight:500; font-size:30px; line-height:1.18; letter-spacing:-0.02em; color:#1A1A1A;">
                            <?= e($headline) ?>
                        </h1>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:20px 32px 0 32px; font-family:<?= $fontSans ?>; font-size:15px; line-height:1.6; color:#1A1A1A;">
                        <?= $bodyHtml ?>
                    </td>
                </tr>

                <!-- Details box -->
                <?php if ($details !== []): ?>
                <tr>
                    <td style="padding:24px 32px 0 32px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#F5F2EE; border-radius:12px;">
                            <?php foreach ($details as $i => $row): ?>
                                <tr>
                                    <td style="padding:<?= $i === 0 ? '16px' : '0' ?> 18px 0 18px; font-family:<?= $fontSans ?>; font-size:11px; text-transform:uppercase; letter-spacing:0.08em; color:#5A5A5A; font-weight:500;">
                                        <?= e($row['label']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 18px <?= $i === count($details) - 1 ? '16px' : '12px' ?> 18px; font-family:<?= $fontSans ?>; font-size:15px; color:#1A1A1A;">
                                        <?= $row['value'] /* may include pre-escaped <s>, <span>, etc. — composer is trusted */ ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </table>
                    </td>
                </tr>
                <?php endif ?>

                <!-- Primary CTA -->
                <?php if ($primaryCta !== null): ?>
                <tr>
                    <td style="padding:28px 32px 0 32px;" align="left">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="background-color:#C84B31; border-radius:12px;">
                                    <a href="<?= e($primaryCta['url']) ?>"
                                       style="display:inline-block; padding:12px 22px; font-family:<?= $fontSans ?>; font-size:14px; font-weight:500; color:#FFFFFF; text-decoration:none; border-radius:12px; line-height:1.2;">
                                        <?= e($primaryCta['label']) ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <?php endif ?>

                <!-- Secondary link -->
                <?php if ($secondaryCta !== null): ?>
                <tr>
                    <td style="padding:14px 32px 0 32px; font-family:<?= $fontSans ?>; font-size:13px;">
                        <a href="<?= e($secondaryCta['url']) ?>" style="color:#5A5A5A; text-decoration:underline;">
                            <?= e($secondaryCta['label']) ?>
                        </a>
                    </td>
                </tr>
                <?php endif ?>

                <!-- Footer -->
                <tr>
                    <td style="padding:32px 32px 28px 32px; border-top:1px solid #E8E4DD; margin-top:24px;">
                        <div style="font-family:<?= $fontSans ?>; font-size:12px; color:#5A5A5A; line-height:1.55;">
                            <?php if ($footerHtml !== null): ?>
                                <?= $footerHtml ?>
                            <?php else: ?>
                                <strong style="color:#1A1A1A;"><?= e($businessName) ?></strong><?php if ($location !== ''): ?> &middot; <?= e($location) ?><?php endif ?><br>
                                If you didn&rsquo;t book this, simply reply to let us know.
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Outer copyright -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="560" style="max-width:560px; width:100%;">
                <tr>
                    <td align="center" style="padding:16px 0 0 0; font-family:<?= $fontSans ?>; font-size:11px; color:#5A5A5A;">
                        &copy; <?= date('Y') ?> <?= e($businessName) ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
