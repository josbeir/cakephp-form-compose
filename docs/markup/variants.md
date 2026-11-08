---
title: Use variants
description: Add a compact presentation to a theme and select it for individual fields or buttons.
---

# Use variants

A variant offers a named alternative inside a theme. For example,
`variant('compact')` selects your compact layout; you supply its markup and CSS.

## Create and request a compact variant

With a theme layer at `forms/application`, create the first file and use the
variant as shown in the second tab:

::: code-group

```php [templates/element/forms/application/variants/compact/control.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 */
$tag = $field->state->grouped() ? 'fieldset' : 'div';
?>
<<?= $tag ?><?= $attributes->merge(['class' => 'compact-field']) ?>>
    <?= $field->label() ?>
    <div>
        <?= $field->input() ?>
        <?= $field->help() ?>
        <?= $field->errors() ?>
    </div>
</<?= $tag ?>>
```

```php [templates/Users/edit.php]
<?php

use FormCompose\Rendering\Theme;

$form = $this->Form
    ->form($user)
    ->theme(new Theme(['forms/application']))
    ->bind();
?>
<?= $form->open() ?>
<?= $form->control('email')
    ->type('email')
    ->variant('compact') ?>
<?= $form->control('name') ?>
<?= $form->submit('Save') ?>
<?= $form->close() ?>
```

:::

Add these rules to your application's stylesheet:

```css
.compact-field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
  gap: .75rem;
  border: 0;
  margin: 0 0 .75rem;
  padding: 0;
}
```

The email control uses the compact layout; the name control keeps the default.
If the helper already selects this theme, omit the per-form `theme()` call.

## Understand what gets selected

Within a layer, `variant('compact')` tries `variants/compact/control.php` before
`email/control.php` and `control.php`. A missing variant falls back to ordinary
markup. The setting applies to each part of that field, so a variant can also
provide its own `label.php`, `help.php`, or `errors.php`.

## Layers take priority before specificity

An earlier layer is searched before a later one. An application `control.php`
therefore wins over a shared plugin's compact variant. See the
[worked lookup example](/template-reference#layer-priority) for exact paths.

## Buttons and form layouts have their own variants

A field variant does not change its submit button. To offer a primary button,
create the element in the second tab and select it on `submit()`:

::: code-group

```php [Inside an open form]
echo $form->submit('Save')->variant('primary');
```

```php [templates/element/forms/application/button/variants/primary/control.php]
<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var string|\Cake\View\HtmlStringable $label
 */
?>
<button<?= $attributes->merge(['class' => 'button-primary']) ?>>
    <?= HtmlString::render($label) ?>
</button>
```

:::

Style `button-primary` in your stylesheet. Form and blueprint layouts use their
own scopes too; see [lookup scope and filenames](/template-reference#lookup-order).

## When a variant seems to do nothing

Check the theme directory, filename, and requested variant. A name alone adds no
styling. Use [template debugging](/theme-debug) to see whether an explicit
template or an earlier layer selected another file.
