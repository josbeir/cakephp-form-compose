<?php
declare(strict_types=1);
use Cake\View\HtmlString;
/**
 * @var \FormCompose\Form\Field\Choice $choice
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<option<?= (string)$attributes ?>><?= HtmlString::render($choice->label) ?></option>
