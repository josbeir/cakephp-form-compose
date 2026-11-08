<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var array{marker?: string, content?: string|\Cake\View\HtmlStringable} $templateData
 * @var \Closure(): string $parent
 */
?>
<section<?= (string)$attributes->merge(['data-template-marker' => $templateData['marker'] ?? 'empty']) ?>>
    <span><?= HtmlString::render($templateData['content'] ?? '') ?></span>
    <?= $parent() ?>
</section>
