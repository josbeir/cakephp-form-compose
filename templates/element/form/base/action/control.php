<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var string|\Cake\View\HtmlStringable $label
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<a<?= (string)$attributes ?>><?= HtmlString::render($label) ?></a>
