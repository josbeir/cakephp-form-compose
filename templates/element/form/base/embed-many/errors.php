<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var list<string> $messages
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<ul<?= (string)$attributes ?>>
<?php foreach ($messages as $message): ?>
    <li><?= HtmlString::render($message) ?></li>
<?php endforeach; ?>
</ul>
