<?php
declare(strict_types=1);

use Cake\View\HtmlString;
/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var array<string, \FormCompose\Form\Field\BoundField> $inputs
 */
?>
<section id="<?= HtmlString::render((string)$attributes->values['id']) ?>"><?= $inputs['start']->control() ?></section>
