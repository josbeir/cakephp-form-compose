---
title: Use your own templates
description: Select a PHP element for one control or part, pass presentation data, and see exactly which markup changes.
---

# Use your own templates

Use `template` when you want a field to render a specific PHP element. It selects
the complete control layout: the wrapper around the label, input, help, and
errors. The field's value and input type still come from normal binding.

For a shared default selected automatically, use a [theme](/themes).
For a named alternative inside a theme, use a [variant](/variants).

## Select a control element

This example puts an `@` decoration beside an email input. Create the element at
the path shown in the second tab:

::: code-group

```php [templates/Users/edit.php]
<?php
$form = $this->Form->bind($user);
?>
<?= $form->open() ?>
<?= $form->control('email')
    ->type('email')
    ->label('Work email')
    ->help('Use your company address.')
    ->template('forms/email') ?>
<?= $form->submit('Save') ?>
<?= $form->close() ?>
```

```php [templates/element/forms/email.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<div<?= (string)$attributes->merge(['class' => 'email-field']) ?>>
    <?= $field->label() ?>
    <div class="input-with-icon">
        <span aria-hidden="true">@</span>
        <?= $field->input() ?>
    </div>
    <?= $field->help() ?>
    <?= $field->errors() ?>
</div>
```

:::

The result is an `email-field` wrapper containing the label, a decorated input
row, help, and any validation errors. The decoration is hidden from assistive
technology. Add styling for these classes in your application's stylesheet.

The element receives a bound `$field`, escaped `$attributes`, custom
`$templateData`, and a `$parent()` callback. You only need the first two here.
The part methods retain the generated input names, IDs, feedback associations,
escaping, and submission registration.

## Replace just one part

Use `partTemplate()` to select a named part:

::: code-group

```php [Inside an open form]
echo $form->control('email')
    ->type('email')
    ->help('Use your company address.')
    ->partTemplate('help', 'forms/email-help');
```

```php [templates/element/forms/email-help.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 */
?>
<p<?= (string)$attributes->merge(['class' => 'email-hint']) ?>>
    <?= $field->state->helpText() ?>
</p>
```

:::

Only the help element changes. Preserving `$attributes` keeps its generated ID
so the input's `aria-describedby` reference still points to the help. `helpText()`
returns escaped text or explicitly trusted markup. With no configured help, the
help part is empty and this element is not rendered.

`template()` selects the whole control; `partTemplate()` selects only the named
part. Other parts keep using the theme. See [all field parts](/template-reference#field-templates)
when changing the actual input markup rather than its wrapper.

## Pass custom presentation data

Pass data when the same layout needs different decorations. Here the prefix is
configured by the caller. Replace the email element with the version in the
second tab:

::: code-group

```php [Inside an open form]
echo $form->control('email')
    ->type('email')
    ->template('forms/email')
    ->templateData(['prefix' => '@']);
```

```php [templates/element/forms/email.php]
<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Form\Field\BoundField $field
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var array{prefix?: string} $templateData
 */
?>
<div<?= $attributes ?>>
    <?= $field->label() ?>
    <div class="input-with-icon">
        <span aria-hidden="true"><?= HtmlString::render($templateData['prefix'] ?? '@') ?></span>
        <?= $field->input() ?>
    </div>
    <?= $field->help() ?>
    <?= $field->errors() ?>
</div>
```

:::

The element defaults to `@` when no prefix is supplied. Data is available in
`$templateData`, separate from HTML attributes. Escape it at the point of output. See [template-data scope and merging](/template-reference#template-data)
for its full contract.

## Reuse the layout automatically

An explicit template is selected wherever you pass its element name. To make
this email layout the default for all email fields, move it to
`templates/element/forms/application/email/control.php` and configure
`new Theme(['forms/application'])`. Callers can then use:

```php
echo $form->control('email')->type('email');
```

Continue with [Create a theme](/themes) for that setup, or
[Use variants](/variants) to offer several named layouts.
