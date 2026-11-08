<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var list<\FormCompose\Form\FeedbackEntry> $entries
 * @var string|\Cake\View\HtmlStringable $title
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var \FormCompose\Rendering\Attributes $titleAttributes
 */
?>
<section<?= (string)$attributes ?>>
    <h2<?= (string)$titleAttributes ?>><?= HtmlString::render($title) ?></h2>
    <ul>
    <?php foreach ($entries as $entry): ?>
        <?php foreach ($entry->messages as $message): ?>
        <li>
            <?php if ($entry->target !== null): ?>
            <a href="<?= HtmlString::render('#' . rawurlencode($entry->target)) ?>"><?= HtmlString::render($entry->label) ?>: <?= HtmlString::render($message) ?></a>
            <?php else: ?>
            <?= HtmlString::render($entry->label) ?>: <?= HtmlString::render($message) ?>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </ul>
</section>
