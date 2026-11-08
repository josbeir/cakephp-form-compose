<?php
declare(strict_types=1);

use Cake\View\HtmlString;
/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var array<string, \FormCompose\Form\Field\BoundField> $inputs
 * @var array{caption?: string} $templateData
 */
?>
<section id="<?= HtmlString::render((string)$attributes->values['id']) ?>" class="inline-range" data-caption="<?= HtmlString::render($templateData['caption'] ?? '') ?>">
    <aside><?= (string) $inputs['end']->label() ?><?= (string) $inputs['end']->input() ?><?= (string) $inputs['end']->errors() ?></aside>
    <main><?= (string) $inputs['start']->label() ?><?= (string) $inputs['start']->input() ?><?= (string) $inputs['start']->errors() ?></main>
</section>
