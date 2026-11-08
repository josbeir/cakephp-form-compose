<?php
declare(strict_types=1);
use Cake\View\HtmlString;
/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var \FormCompose\Form\Field\Choice $choice
 */
?>
<option data-option<?= (string)$attributes ?>><?= HtmlString::render($choice->label) ?></option>
