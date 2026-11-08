<?php
declare(strict_types=1);
use Cake\View\HtmlString;

/**
 * @var \FormCompose\Form\FormSession $form
 * @var string $body
 * @var string $summary
 * @var string|\Cake\View\HtmlStringable|false $submit
 * @var \FormCompose\Configuration\ButtonConfig $submitConfig
 * @var array{caption?: string} $templateData
 */
?>
<article data-form-layout>
    <h2><?= HtmlString::render($templateData['caption'] ?? 'Editor') ?></h2>
    <?= $summary ?>
    <div data-form-body><?= $body ?></div>
    <footer>
        <?= (string) $form
            ->submit('Check')->name('intent')->value('check')->attributes(['formnovalidate' => true]) ?>
        <?php if ($submit !== false): ?>
            <?= (string) $form->submit($submit)->apply($submitConfig) ?>
        <?php endif; ?>
    </footer>
</article>
