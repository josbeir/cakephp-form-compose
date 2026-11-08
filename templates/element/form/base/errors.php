<?php
declare(strict_types=1);
use Cake\View\HtmlString;

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<ul<?= (string)$attributes ?>>
<?php foreach ($field->state->messages as $message): ?>
    <li><?= HtmlString::render($message) ?></li>
<?php endforeach; ?>
</ul>
