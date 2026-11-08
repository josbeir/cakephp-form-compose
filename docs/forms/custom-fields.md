---
title: Custom field types
description: Build a reusable Markdown input or a composite date-range control with discovered aliases and PHP elements.
---

# Custom field types

Use a custom type to reuse a widget by name. Start with a Markdown input that
submits one value, or [combine two dates](#create-a-date-range) into one control.
Both use discovered aliases and ordinary PHP elements.

For wrappers, classes, or icons around an existing input, a
[control template](/templates) is usually enough.

## Create and use a Markdown type

Create the type and element in the first two tabs, then use the third tab in your
article view. The plugin discovers the `markdown` alias from `#[AsFieldType]`;
no manual instance registration is needed.

::: code-group

```php [src/Form/Field/MarkdownType.php]
<?php
declare(strict_types=1);

namespace App\Form\Field;

use FormCompose\Attribute\AsFieldType;
use FormCompose\Form\Field\SingleInputType;

#[AsFieldType('markdown')]
class MarkdownType extends SingleInputType
{
    protected ?string $template = 'forms/fields/markdown';
}
```

```php [templates/element/forms/fields/markdown.php]
<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var string $value
 */
?>
<textarea<?= $attributes->merge([
    'data-editor' => 'markdown',
    'rows' => 12,
]) ?>><?= HtmlString::render($value) ?></textarea>
```

```php [templates/Articles/edit.php]
<?php
$form = $this->Form->bind($article);
?>
<?= $form->open() ?>
<?= $form->control('body')
    ->type('markdown')
    ->label('Article body') ?>
<?= $form->submit('Save article') ?>
<?= $form->close() ?>
```

:::

`SingleInputType` supplies the field's name, ID, and feedback attributes through
`$attributes`, and its displayed text through `$value`. Preserve those attributes
and escape the text with `HtmlString::render()`. The normal label, help, and errors still
render around the textarea.

Your editor can find `data-editor="markdown"` and enhance the textarea. Keep its
value synchronized so the ordinary form submission continues to work.

## Register a type with AsFieldType

Application types belong in `src/Form/Field/` under `App\Form\Field`. Give each
one a unique alias with `#[AsFieldType('markdown')]`, then select that same alias
with `type('markdown')`.

Loaded plugins can provide types too. Prefix their aliases to avoid clashes,
for example `travel-date-range`. If a new type is missing in production, clear
[discovery metadata](/blueprint-configuration#discovery-and-cache-configuration)
during deployment.

## Combine two dates in one control {#create-a-date-range}

A composite type groups ordinary child inputs. For a booking, `type('date-range')`
can display arrival and departure dates under one legend.

Create the type in the first tab. `inputs()` declares two fields relative to
`period`: `period.start` and `period.end`. Use it in the booking view shown in
the second tab; the bundled layout already renders both children.

::: code-group

```php [src/Form/Field/DateRangeType.php]
<?php
declare(strict_types=1);

namespace App\Form\Field;

use FormCompose\Attribute\AsFieldType;
use FormCompose\Configuration\FieldConfig;
use FormCompose\Form\Field\BoundField;
use FormCompose\Form\Field\CompositeType;
use FormCompose\Form\Field\FieldSpec;
use function Cake\I18n\__;

#[AsFieldType('date-range')]
class DateRangeType extends CompositeType
{
    /**
     * @return list<FieldSpec>
     */
    public function inputs(BoundField $field): array
    {
        return [
            new FieldSpec(
                'start',
                FieldConfig::builder()
                    ->type('date')
                    ->label(__('Arrival')),
            ),
            new FieldSpec(
                'end',
                FieldConfig::builder()
                    ->type('date')
                    ->label(__('Departure')),
            ),
        ];
    }
}
```

```php [templates/Bookings/edit.php]
<?php
$form = $this->Form->bind($booking);
?>
<?= $form->open() ?>
<?= $form->control('period')
    ->type('date-range')
    ->label('Your stay')
    ->help('Choose arrival and departure dates.') ?>
<?= $form->submit('Book') ?>
<?= $form->close() ?>
```

:::

The control renders a fieldset with the legend “Your stay” and two date inputs.
They submit `period[start]` and `period[end]`. Bind a booking whose `period` value
contains `start` and `end`, or whose context exposes those nested paths. Each child
uses ordinary values and validation feedback from its own path.

### Arrange the children

To put the dates side by side, select a composite-body element. Add this setting
to the `DateRangeType` class above:

```php
protected ?string $template = 'forms/fields/date-range';
```

Create its element:

`templates/element/forms/fields/date-range.php`

```php [templates/element/forms/fields/date-range.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var array<string, \FormCompose\Form\Field\BoundField> $inputs
 */
?>
<div<?= $attributes->merge(['class' => 'date-range']) ?>>
    <div><?= $inputs['start']->control() ?></div>
    <div><?= $inputs['end']->control() ?></div>
</div>
```

Add layout rules in your application stylesheet:

```css
.date-range {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
```

This changes only the inner layout. The outer fieldset, legend, group help, and
errors remain. To arrange a child's parts independently, replace its `control()`
call with its `label()`, `input()`, `help()`, and `errors()` calls.

::: warning Render the child inputs
Declaring a child does not submit it. Render its `control()` or `input()` so its
name is registered with CakePHP form protection. Do not also render the same input
elsewhere in the form.
:::

### Supply an explicit value

Normally the booking and request supply both dates. For an intentional override:

```php
echo $form->control('period')
    ->type('date-range')
    ->value([
        'start' => '2026-11-01',
        'end' => '2026-11-05',
    ]);
```

## Advanced contracts

The [custom field reference](/custom-field-reference) covers constructors with
injected services, explicit instance overrides, composite binding rules, and custom
submission protocols.
Use these when the base classes do not represent the widget you need.

<span id="low-level-submission-records"></span>
<span id="submission-protection"></span>
<span id="custom-submission-protocols"></span>

For exact registration and protection rules, see
[submission records](/custom-field-reference#low-level-submission-records).
