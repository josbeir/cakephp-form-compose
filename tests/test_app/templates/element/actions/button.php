<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var string|\Cake\View\HtmlStringable $label
 * @var array{marker: string} $templateData
 */
?>
<button<?= (string)$attributes->merge(['data-marker' => $templateData['marker']]) ?>><?= HtmlString::render($label) ?></button>
