---
title: Repeated embeds
description: Render a reusable comment form for every loaded or submitted association row.
---

# Repeated embeds

Use a repeated embed when a collection needs the same fields for each row. This
example edits an article's comments within the article form.

## Define a row and its parent

Create the comment blueprint, declare its repeated embed in the existing
[article blueprint](/blueprints), and place the collection in the article's layout.
The tabs show those additions and the rendering call. Load the article with its
Comments association in your controller first.

::: code-group

```php [src/Form/CommentForm.php]
<?php
declare(strict_types=1);

namespace App\Form;

use FormCompose\Attribute\Form;
use FormCompose\Blueprint\BlueprintBuilder;
use FormCompose\Blueprint\FormBlueprint;
use FormCompose\Blueprint\FormContext;

#[Form('comment')]
class CommentForm extends FormBlueprint
{
    public function configure(BlueprintBuilder $form, FormContext $context): void
    {
        $form->add('id')->type('hidden');
        $form->add('body')->type('textarea');
    }
}
```

```php [Add to ArticleForm::configure()]
$form->embedMany('comments', 'comment')->label('Comments');
```

```php [Add inside templates/element/forms/article.php]
<?= $form->embeddedMany('comments') ?>
```

```php [templates/Articles/edit.php]
<?php
echo $this->Form
    ->form($article)
    ->blueprint('article')
    ->submit('Save article');
```

:::

Each comment supplies a row. Its fields submit under paths such as
`comments[0][id]` and `comments[0][body]`. After a failed submission, submitted
rows take precedence over the loaded collection, including an empty collection.

## Select rows or add blank presentations

To choose an order and include a blank row, replace the parent declaration with:

```php
$form->embedMany('comments', 'comment')
    ->label('Comments')
    ->keys([2, 7, 'new_row']);
```

Keys are preserved in submitted names, so the blank textarea submits
`comments[new_row][body]`. A blank row renders inputs; it does not create or save
an entity. Your CakePHP submission handler still marshals and saves the data.

## Arrange rows with a PHP element

To wrap each row, select a collection template in the parent declaration and
create the element in the second tab:

::: code-group

```php [Inside ArticleForm::configure()]
$form->embedMany('comments', 'comment')
    ->label('Comments')
    ->template('forms/comments');
```

```php [templates/element/forms/comments.php]
<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Blueprint\RepeatedView $embed
 * @var array<int|string, \FormCompose\Blueprint\BlueprintView> $rows
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<section<?= $attributes ?>>
    <h2><?= HtmlString::render($embed->label()) ?></h2>
    <?= $embed->errors() ?>
    <?php foreach ($rows as $row): ?>
        <article class="comment-row">
            <?= $row->render() ?>
        </article>
    <?php endforeach; ?>
</section>
```

:::

`$row->render()` uses the child blueprint's layout. To arrange fields yourself,
use `$row->controls(['id', 'body'])` instead. Render each row once.

## Configure and render in PHP

In a custom article blueprint layout, render the declared collection with:

```php
<?= $form->embeddedMany('comments') ?>
```

For view-created occurrences without a parent declaration, or exact key and
feedback rules, see [repeated blueprint configuration](/blueprint-reference#repeated-blueprints-and-occurrence-options).
