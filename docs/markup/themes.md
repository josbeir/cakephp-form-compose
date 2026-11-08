---
title: Create a theme
description: Make your PHP elements and default attributes apply across forms.
---

# Create a theme

A theme makes your elements the shared defaults. You only create the files you
want to change; bundled templates supply the rest.

## Select your element directory {#select-theme}

This example gives every email field a decorated input. Configure the helper,
create the element, then use ordinary controls in your views:

::: code-group

```php [src/View/AppView.php]
<?php
declare(strict_types=1);

namespace App\View;

use Cake\View\View;
use FormCompose\Rendering\Theme;

class AppView extends View
{
    public function initialize(): void
    {
        parent::initialize();
        $this->addHelper('Form', [
            'className' => 'FormCompose.Form',
            'theme' => new Theme(layers: ['forms/application']),
        ]);
    }
}
```

```php [templates/element/forms/application/email/control.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<div<?= $attributes->merge(['class' => 'contact-field']) ?>>
    <?= $field->label() ?>
    <div class="input-with-icon">
        <span aria-hidden="true">@</span>
        <?= $field->input() ?>
    </div>
    <?= $field->help() ?>
    <?= $field->errors() ?>
</div>
```

```php [templates/Users/edit.php]
<?php
$form = $this->Form->bind($user);
?>
<?= $form->open() ?>
<?= $form->control('email')->type('email') ?>
<?= $form->submit('Save') ?>
<?= $form->close() ?>
```

:::

The theme directory `forms/application` maps to
`templates/element/forms/application/`. Its `email/control.php` handles email
controls. Other types and parts continue to use the bundled markup.

A directory in the theme's search list is called a **layer**. For a one-off
selection, use [an explicit template](/templates) instead.

## Add default classes

Replace the helper configuration in `AppView::initialize()` with:

```php
$this->addHelper('Form', [
    'className' => 'FormCompose.Form',
    'theme' => new Theme(
        layers: ['forms/application'],
        defaults: [
            'input' => ['class' => 'field-input'],
            'label' => ['class' => 'field-label'],
            'help' => ['class' => 'field-help'],
        ],
    ),
]);
```

Now inputs, labels, and help receive those classes. Field attributes merge on top:

```php
echo $form->control('email')
    ->type('email')
    ->attributes(['class' => 'wide', 'x-model' => 'email']);
```

The input has `field-input wide` and the integration attribute `x-model="email"`.
Style those classes in your application stylesheet.

## Customize another part when needed

Use the same directory for other parts. A shared submit button lives at
`forms/application/button/control.php`:

`templates/element/forms/application/button/control.php`

```php [templates/element/forms/application/button/control.php]
<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var string|\Cake\View\HtmlStringable $label
 */
?>
<button<?= $attributes ?>>
    <span aria-hidden="true">→</span>
    <?= HtmlString::render($label) ?>
</button>
```

`$form->submit('Save')` now uses this element. See
[template filenames and variables](/template-reference) for other parts.

## Select a theme for one form

Override the helper's theme on the form builder before binding:

```php
use FormCompose\Rendering\Theme;

$form = $this->Form
    ->form($user)
    ->theme(new Theme(['forms/compact']))
    ->bind();
```

Use `theme(Theme::base())` for bundled markup. Omit `theme()` to inherit the
helper's setting. See [theme configuration](/template-reference#theme-api) for
replacement behavior and accepted helper values.

## Share and layer themes

To override a shared design-system plugin, put application elements first:

```php
use FormCompose\Rendering\Theme;

$theme = new Theme(layers: ['forms/application', 'MyDesignSystem.forms']);
```

Each part searches the application layer before the plugin and bundled layers.
An earlier generic `control.php` wins over a later type-specific element. For
exact candidates, see [lookup order](/template-reference#lookup-order).

### Wrap the next layer with `$parent()`

An application element can wrap the shared layout:

`templates/element/forms/application/control.php`

```php [templates/element/forms/application/control.php]
<?php
declare(strict_types=1);

/** @var \Closure(): string $parent */
?>
<section class="application-field">
    <?= $parent() ?>
</section>
```

`$parent()` continues in the next layer. In an explicit template override, it
starts normal theme lookup. See [parent lookup](/template-reference#calling-the-parent-layer).

## Offer a named alternative

Use [variants](/variants) when callers should choose a presentation such as compact.
Use [template debugging](/theme-debug) to see why a particular element was selected.

## Use your CSS framework

Theme elements can use Bootstrap, Tailwind, daisyUI, or your own CSS. For Tailwind,
write complete class names and include your element files in source scanning.
The base theme uses semantic HTML without a CSS framework.

## A complete starter theme

The email example changes only email fields. This optional starter provides a
generic layout and stylesheet for text inputs, selects, textareas, files, a
single checkbox, and grouped choices.

::: details Complete starter: control element, form, and CSS

Start with a generic control element:

`templates/element/forms/starter/control.php`

```php [templates/element/forms/starter/control.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 */
$tag = $field->state->grouped() ? 'fieldset' : 'div';
?>
<<?= $tag ?><?= (string)$attributes->merge(['class' => 'form-field']) ?>>
    <?php if ($field->state->type === 'checkbox'): ?>
        <div class="checkbox-row">
            <?= $field->input() ?>
            <?= $field->label() ?>
        </div>
    <?php else: ?>
        <?= $field->label() ?>
        <?= $field->input() ?>
    <?php endif; ?>
    <?= $field->help() ?>
    <?= $field->errors() ?>
</<?= $tag ?>>
```

Grouped fields get a fieldset and the base label part supplies a legend. A single
checkbox puts its label after the input. Hidden fields bypass this wrapper.
All other parts fall back to the bundled elements, retaining escaped values,
choice labels, help/error IDs, and hidden fallbacks.

Use the theme in an existing article view:

`templates/Articles/edit.php`

```php [templates/Articles/edit.php]
<?php

use FormCompose\Rendering\Theme;

$form = $this->Form
    ->form($article)
    ->theme(new Theme(
        layers: ['forms/starter'],
        defaults: [
            'input' => ['class' => 'field-input'],
            'choice' => ['class' => 'choice-input'],
            'label' => ['class' => 'field-label'],
            'help' => ['class' => 'field-help'],
            'errors' => ['class' => 'field-errors'],
        ],
    ))
    ->multipart()
    ->bind();
?>
<?= $form->open() ?>
<?= $form->control('title')->help('Shown in the article list.') ?>
<?= $form->control('body')->type('textarea') ?>
<?= $form->control('author_id')
    ->choices($authors)
    ->placeholder('No author') ?>
<?= $form->control('published')->type('checkbox') ?>
<?= $form->control('visibility')
    ->type('radio')
    ->choices(['public' => 'Public', 'private' => 'Private']) ?>
<?= $form->control('topics')
    ->type('multicheckbox')
    ->choices(['php' => 'PHP', 'cakephp' => 'CakePHP']) ?>
<?= $form->control('attachment')->type('file') ?>
<?= $form->submit('Save article') ?>
<?= $form->close() ?>
```

Add these classes to your application's existing stylesheet, adjusting colors
to your design system:

```css
.form-field {
  border: 0;
  margin: 0 0 1.25rem;
  padding: 0;
}

.field-label {
  display: block;
  font-weight: 600;
  margin-bottom: .375rem;
}

.field-input:not([type="checkbox"]):not([type="radio"]) {
  box-sizing: border-box;
  width: 100%;
  padding: .625rem;
  border: 1px solid #64748b;
  border-radius: .375rem;
}

.checkbox-row {
  display: flex;
  align-items: baseline;
  gap: .5rem;
}

.choice-input {
  margin-right: .5rem;
}

.field-help {
  margin-top: .375rem;
  color: #475569;
}

.field-errors {
  margin-top: .375rem;
  color: #b91c1c;
}

[aria-invalid="true"] {
  outline: 2px solid #b91c1c;
  outline-offset: 2px;
}
```

The theme requires no JavaScript or CSS framework.

:::
