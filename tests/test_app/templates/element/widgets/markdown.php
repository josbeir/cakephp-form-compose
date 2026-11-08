<?php
declare(strict_types=1);

use Cake\View\HtmlString;
/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var string $value
 */
?>
<textarea<?= (string)$attributes->merge(['data-editor' => 'markdown']) ?>><?= HtmlString::render($value) ?></textarea>
