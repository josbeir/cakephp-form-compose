<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var array{marker?: string} $templateData
 * @var \Closure(): string $parent
 */
?>
<!-- template-data: <?= HtmlString::render($templateData['marker'] ?? 'empty') ?> -->
<?= $parent() ?>
