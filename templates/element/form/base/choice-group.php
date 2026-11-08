<?php
declare(strict_types=1);
use Cake\View\HtmlString;
/**
 * @var \FormCompose\Form\Field\Choice $choice
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var \Closure(\FormCompose\Form\Field\Choice): string $renderChoice
 */
?>
<fieldset<?= (string)$attributes ?>>
    <legend><?= HtmlString::render($choice->label) ?></legend>
<?php foreach ($choice->children as $child): ?>
<?= $renderChoice($child) ?>
<?php endforeach; ?>
</fieldset>
