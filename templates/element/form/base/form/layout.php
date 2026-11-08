<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\FormSession $form
 * @var string $body
 * @var string $summary
 * @var string|\Cake\View\HtmlStringable|false $submit
 * @var \FormCompose\Configuration\ButtonConfig $submitConfig
 */
echo $summary;
echo $body;
if ($submit !== false) {
    echo (string) $form->submit($submit)->apply($submitConfig);
}
