<?php
/**
 * app/views/api_docs/_code_block.php
 *
 * Tiny partial that renders a syntax-highlighted code block with a
 * "Copy" button. Set $codeLang ('json' | 'bash' | 'javascript' | 'php'
 * | 'python') and $codeBody (raw string) before including.
 *
 * Each include resets the variables on next call, so blocks don't leak
 * state into each other.
 */

/** @var string $codeLang */
/** @var string $codeBody */

if (!isset($codeLang) || !isset($codeBody)) {
    return;
}

$lang = $codeLang;
$body = $codeBody;
// Defuse for the next include — important since this partial is
// included many times in the docs page.
unset($codeLang, $codeBody);
?>
<div class="api-code-wrap">
    <button type="button" class="api-copy-btn" aria-label="Copy code">Copy</button>
    <pre class="language-<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"><code class="language-<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?></code></pre>
</div>
