<?php
declare(strict_types=1);
use Cake\View\HtmlString;
/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var \FormCompose\Rendering\Attributes $titleAttributes
 * @var string|\Cake\View\HtmlStringable $title
 * @var array<string, mixed> $templateData
 * @var list<\FormCompose\Form\FeedbackEntry> $entries
 */
?>
<aside<?= (string)$attributes ?>>
    <h2<?= (string)$titleAttributes ?>><?= HtmlString::render($title) ?></h2>
    <p><?= HtmlString::render((string)$templateData['intro']) ?></p>
    <?php foreach ($entries as $entry): ?>
        <?php foreach ($entry->messages as $message): ?>
            <p><?= HtmlString::render($message) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>
</aside>
