<?php
declare(strict_types=1);
use Cake\View\HtmlString;
/**
 * @var \FormCompose\Form\Field\Choice $choice
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<div<?= (string)$choice->presentation['container'] ?>>
    <input<?= (string)$attributes ?>>
    <label<?= (string)$choice->presentation['label'] ?>><?= HtmlString::render($choice->label) ?></label>
</div>
