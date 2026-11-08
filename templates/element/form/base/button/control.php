<?php
declare(strict_types=1);
use Cake\View\HtmlString;
/**
 * @var string|\Cake\View\HtmlStringable $label
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<button<?= (string)$attributes ?>><?= HtmlString::render($label) ?></button>
