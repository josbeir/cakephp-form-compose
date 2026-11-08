<?php
declare(strict_types=1);

use Cake\View\HtmlString;
/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var array<string, mixed> $templateData
 */
?>
<p<?= (string)$attributes ?>><?= HtmlString::render((string)$templateData['message']) ?></p>
