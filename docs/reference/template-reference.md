---
title: Template reference
description: Look up field, form, and button elements, their variables, layer precedence, variants, and attribute options.
---

# Template reference

Use this page to look up exact paths, variables, and APIs. For a first example,
start with [How rendering works](/rendering), then follow
[Use your own templates](/templates), [Create a theme](/themes), or
[Use variants](/variants). For control configuration, see
[Field configuration](/field-options).

All paths below are relative to a theme layer, such as
`templates/element/forms/application/`.

## Lookup order

Lookup runs independently for each rendered part:

1. Use an explicit `partTemplate(part, element)` element when configured.
2. Otherwise search the theme layers in their supplied order, followed by the
   bundled `FormCompose.form/base` layer.
3. Within each layer, try the requested variant, then the type-specific file,
   then the generic part. Stop at the first match.

For an email control with `variant('compact')`, each layer tries:

```text
variants/compact/control.php
email/control.php
control.php
```

Without a variant, skip the first candidate. A valid variant with no matching
file falls back to type-specific and generic candidates. If every candidate in
every layer is absent, rendering throws `RuntimeException`.

::: info Layer order comes first
A generic file in an earlier layer wins over a variant or type-specific file in
a later layer. See the [worked layer example](/variants#layers-take-priority-before-specificity).
:::

Form, button, and blueprint paths have scope prefixes. A primary submit button tries:

```text
button/variants/primary/control.php
button/submit/control.php
button/control.php
```

Variants, types, and part names use lowercase letters, numbers, underscores, and
hyphens, beginning with a letter. Variants apply to each part of the receiving
field, form, button, or blueprint; selecting one on a form or blueprint does not
select it on child fields or buttons. Field variant paths are
`variants/{variant}/{part}.php`; there is no additional type directory inside a
variant.

### Layer priority

For `new Theme(['forms/application', 'MyDesignSystem.forms'])` and an email field
requesting `compact`, lookup tries:

```text
1. forms/application/variants/compact/control
2. forms/application/email/control
3. forms/application/control
4. MyDesignSystem.forms/variants/compact/control
5. MyDesignSystem.forms/email/control
6. MyDesignSystem.forms/control
7. Bundled base candidates
```

An application generic control wins over a plugin variant. Avoid that generic
override or use `$parent()` to delegate if the shared variant should remain reachable.

### Explicit overrides

Use `template()` to select the complete field control element. It takes
priority over variant and layer lookup:

```php
echo $form->control('email')
    ->type('email')
    ->template('forms/special-email');
```

Here the control uses `templates/element/forms/special-email.php`. Use CakePHP
element names without `.php`, including plugin-qualified names such as
`MyDesignSystem.forms/email`. [Use your own templates](/templates)
shows the call and a complete element together.

`partTemplate()` selects individual parts. For a field's outer
wrapper, select the `control` part:

```php
echo $form->control('email')
    ->type('email')
    ->template('forms/special-email')
    ->partTemplate('input', 'forms/email-input')
    ->partTemplate('help', 'forms/email-help');
```

Each setter selects one part's element. Parts without an explicit selection keep
normal theme lookup. `template()` selects this builder's primary part, while
`partTemplate()` names a part explicitly. Calling either again for the same part
uses the last selection. Empty part/element names fail validation.

For fields, blueprints, buttons, summaries, and repeated collections, the primary
part is `control`. For a complete form it is `layout`; for a field's `input()`
builder it is `input`, and likewise for label/help/errors. Hidden fields render
the input directly. Configure form `open`, `close`, and `hidden` explicitly with
`partTemplate()`. Custom renderers receive typed `$node->presentation`.

### Calling the parent layer

Elements receive a `$parent()` closure that renders the same part in the next
layer. For example, an application can wrap a shared control:

```php
<?php

/** @var \Closure(): string $parent */
?>
<section class="application-field">
    <?= $parent() ?>
</section>
```

In an explicit override, `$parent()` resumes normal theme lookup. Within a layer,
it moves to the next layer rather than trying another file in that same layer.

## Template data

Pass custom presentation data through `templateData(array)`. Each element
receives it as a `$templateData` array alongside its prepared variables:

```php
echo $form->control('email')
    ->template('forms/special-email')
    ->templateData(['prefix' => '@', 'account' => $account]);
```

The [template guide](/templates#pass-custom-presentation-data) shows the call and
its PHP element together. No custom keys are extracted into local variables:
`$templateData['field']` is ordinary custom data and cannot replace `$field`.
The same applies to `$attributes`, `$parent`, and other prepared variables.

### Availability and scope

| Supplied through | Available to |
| --- | --- |
| Field builder `templateData()` | That field's control, label, input, help, errors, native markup, choices, and fallback elements. |
| Form builder `templateData()` before binding | That form's opening, closing, and infrastructure hidden-input elements. |
| Button builder `templateData()` | That button's element. |
| Blueprint or occurrence builder `templateData()` | That blueprint's layout, independently of child field or embedded layout data. |

Theme layers, explicit overrides, and `$parent()` calls receive the same bag for
their field, form, or button. Form data does not become a default for its fields
or buttons; supply their own data explicitly. Metadata is removed from form and
button HTML attributes before rendering.

For example, form and button elements can use separate configuration:

```php
$form = $this->Form
    ->form($article)
    ->templateData(['layout' => 'stacked'])
    ->bind();

echo $form->open();
echo $form->control('title');
echo $form->submit('Save')->templateData(['icon' => 'save']);
echo $form->close();
```

Here form elements receive `['layout' => 'stacked']`, the title field receives
`[]`, and the button receives `['icon' => 'save']`.

### Values, replacement, and escaping

When omitted, the bag is `[]`. The native array parameter rejects null and
non-array containers with `TypeError`. Values inside the bag may include objects,
nested arrays, false, zero, null, and trusted `HtmlString`. Objects retain their identity;
data is not serialized, stringified, or pre-escaped.

`templateData()` merges named entries over earlier layers. Use
`replaceTemplateData([])` to clear inherited data, or supply another replacement
bag. Replacement intent survives reusable config snapshots. A field's parts
inherit its bag; local part data remains local.

::: tip Escape at the point of output
Templates receive original PHP values. Use `HtmlString::render()` for text; it escapes
ordinary strings and preserves explicitly trusted `HtmlString`. Use an `Attributes` bag
when intentionally putting a value into an HTML attribute.
:::

Custom renderers receive the validated bag through
`$node->variables['templateData']`, including `[]` when none was supplied.

## Field templates

Field elements receive `$field` (`BoundField`), `$attributes` (`Attributes`), and
the `$templateData` array.
The field's immutable `state` exposes its path, name, ID, type, value, messages,
constraints, and typed `config`.

| Part | Purpose |
| --- | --- |
| `control.php` | Arrange the complete field. Receives the wrapper attributes from the `container` attribute target. |
| `label.php` | Render a label, or a legend for a grouped control. |
| `input.php` | Render the input hook; normally calls `$field->renderInput($attributes)`. |
| `help.php` | Render enabled help content. |
| `errors.php` | Render enabled validation messages. |

Type-specific overrides work for every part, for example `email/label.php`.
Hidden fields render their input parts directly rather than using the outer
`control.php` layout.

### Standard input markup

The standard field type prepares browser values and choices, then renders:

| Part | Purpose |
| --- | --- |
| `native.php` | The ordinary input tag. |
| `textarea/native.php` | Textarea structure and content. |
| `select/native.php` | Select structure and its options. |
| `radio/native.php`, `multicheckbox/native.php` | Grouped choices. |
| `fallback.php` | An unchecked or empty-selection hidden input. |
| `option.php`, `option-group.php` | Select options and groups. |
| `choice.php`, `choice-group.php` | Radio/checkbox choices and groups. |

Native templates receive additional prepared data as needed, such as `$value`,
`$choices`, and `$renderChoice`. Choice templates receive a `$choice` object with
its label, attributes, children, and presentation attributes.

## Form and button templates

| Element | Purpose |
| --- | --- |
| `form/layout.php` | Complete-form body, summary, and actions used by `render()`. |
| `form/open.php` | Opening form tag and initial method/CSRF inputs. |
| `form/close.php` | Final protection inputs and closing form tag. |
| `form/hidden.php` | One infrastructure hidden input. |
| `button/control.php` | Submit, reset, or ordinary button structure. |

Forms also support method-specific paths such as `form/patch/open.php`.

Opening and closing templates receive `$form`, prepared `$state`, `$attributes`,
and a `$hidden` list of attribute bags. Render the supplied hidden inputs with
`$form->hiddenFields($hidden, $state->presentation)`. The hidden element receives
an `$attributes` bag for one input. All three form elements also receive the
form's `$templateData` bag.

Retain the supplied infrastructure fields when replacing opening and closing
markup. Token generation belongs to the form lifecycle; the elements output its
prepared fields.

Button templates receive `$form`, `$label`, `$attributes`, and `$templateData`. Use
`HtmlString::render($label)` to escape ordinary labels while preserving explicitly trusted
`HtmlString` labels.

### Complete-form layouts

Complete-form `render()` invokes `form/layout.php` after buffering the selected
blueprint and preparing its optional error summary. The bundled element outputs
the summary, body, and optional submit button. Ordinary `open()` / `close()` calls
do not invoke this part.

| Variable | Meaning |
| --- | --- |
| `$form` | The open `FormSession`; use `submit()` for custom action buttons. |
| `$state` | Prepared form metadata, including the effective method. |
| `$body` | Already rendered blueprint HTML, including nested/custom controls. Emit it once. |
| `$summary` | Already rendered summary HTML, or an empty string. Emit it once. |
| `$submit` | Requested text/trusted `HtmlString` label, translated default Submit label, or `false`. |
| `$submitConfig` | Immutable `ButtonConfig` configured on the form builder. |
| `$templateData` | The form's application-owned presentation data. |
| `$parent` | Callback that resumes normal theme lookup for this part. |

Put the shared element at `form/layout.php` inside your configured theme; ordinary
`render()` calls then require no template option. The root `control.php` in that
theme still wraps individual fields. Normal theme lookup supports method-specific
and variant overrides, for example `form/patch/layout.php` and
`form/variants/compact/layout.php`.

For an exceptional per-form selection, use `template('forms/editor')` or
`partTemplate('layout', 'forms/editor')` on the form builder before binding.

The plugin outputs the opening before this layout and generates protection fields
and closing markup afterward. The blueprint owns validation-bearing controls;
the form layout arranges their prepared HTML and renders actions. See the
[complete-form layout example](/error-summaries#keep-custom-layouts-and-actions-without-manual-buffering).

## Blueprint layouts

Blueprint nodes use scope `blueprint`, type `default`, and part `control`.
Each theme layer tries `blueprint/variants/{variant}/control.php` when selected,
then `blueprint/default/control.php`, then `blueprint/control.php`.
The bundled default renders ordered fields and embeds without a wrapper.

| Variable | Meaning |
| --- | --- |
| `$form` | A scoped `BlueprintView` with `has()`, `field()`, `control()`, and `embedded()`. |
| `$definition` | Internal compiled layout metadata, relative to this mount; use `$form` for field access. |
| `$attributes` | Escaped attribute bag from theme defaults and local definition options. |
| `$templateData` | This blueprint layout's application-owned presentation data. |
| `$parent` | The standard callback to resume theme lookup. |

Use theme defaults keyed by `blueprint.control` for layout attributes. The default
layout adds no element, so a custom wrapper must emit `$attributes` when needed.
Selecting a layout does not forward its data to individual controls or children.
See [blueprint layouts](/blueprints#keep-control-of-the-layout) for a complete
class/element example, and [nested blueprints](/composition) for child layouts.

## Repeated-embed layouts

The `embed-many` scope renders `control` and `errors` through the standard theme
lookup. The control receives `$embed` (`RepeatedView`), `$rows` (original keys
mapped to scoped `BlueprintView` instances), `$attributes`, and `$templateData`.
The error element receives `$embed`, `$messages`, `$attributes`, and `$templateData`.

The base control renders rows and direct collection errors without a wrapper.
Emitted wrapper attributes require a custom control element; render `$attributes`
on its wrapper to preserve configured attributes and duplicate-ID checks.

Use a normal `foreach` for row wrappers and `$row->render()` for each child's own
layout. There is no `row` template part. See the
[complete repeated layout example](/repeated-embeds#arrange-rows-with-a-php-element).

## Error-summary elements

The `error-summary` scope renders `control`. Its element receives `$form`,
`$entries` (`list<FeedbackEntry>`), `$title`, `$attributes`, `$titleAttributes`,
and `$templateData`. An entry exposes `label` (text or trusted `HtmlString`), `messages`
(raw validation strings; escape them when outputting), and `target` (an emitted DOM ID or null for an unlinked message).

The default section and heading IDs are associated with `aria-labelledby` and
checked for collisions. Preserve those supplied attributes in custom markup.
Create the element in the second tab, then select it on a summary builder inside
an open form after rendering the fields (or pass it through `SummaryConfig` when
configuring a complete form):

::: code-group

```php [Inside an open form, after its controls]
echo $form->errorSummary()->template('forms/summary');
```

```php [templates/element/forms/summary.php]
<?php
declare(strict_types=1);

use Cake\View\HtmlString;

/**
 * @var list<\FormCompose\Form\FeedbackEntry> $entries
 * @var string|\Cake\View\HtmlStringable $title
 * @var \FormCompose\Rendering\Attributes $attributes
 * @var \FormCompose\Rendering\Attributes $titleAttributes
 */
?>
<section<?= $attributes ?>>
    <h2<?= $titleAttributes ?>><?= HtmlString::render($title) ?></h2>
    <ul>
        <?php foreach ($entries as $entry): ?>
            <?php foreach ($entry->messages as $message): ?>
                <li>
                    <?php if ($entry->target !== null): ?>
                        <a href="<?= HtmlString::render('#' . rawurlencode($entry->target)) ?>">
                            <?= HtmlString::render($entry->label) ?>: <?= HtmlString::render($message) ?>
                        </a>
                    <?php else: ?>
                        <?= HtmlString::render($entry->label) ?>: <?= HtmlString::render($message) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</section>
```

:::


## POST action templates

Standalone action buttons use `button/control.php`; action links use
`action/control.php`. Both receive `$form`, `$label`, `$attributes`, and
`$templateData`. Preserve the supplied trigger associations in custom markup.
The external action script is registered through CakePHP's HTML helper and the
`script` view block; control loading with the `autoActionScript` helper option. See [POST action templates](/post-action-reference#options-and-presentation).

## Attributes

Theme defaults use part keys, with optional type prefixes:

| Defaults key | Applies to |
| --- | --- |
| `container`, `input`, `label`, `help`, `errors` | Field parts. |
| `email.input` | Email input defaults replacing generic input defaults. |
| `form.open`, `form.close`, `form.hidden` | Form parts. |
| `button` | Buttons. |
| `option`, `choice`, `choice-label`, `choice-container`, `choice-group` | Choice parts. |

Use a field builder's `attributes()` for input attributes and `partAttributes()` for
other parts:

```php
echo $form->control('email')
    ->type('email')
    ->attributes(['class' => 'wide'])
    ->partAttributes('label', ['class' => 'caption']);
```

Attribute bags merge class tokens and `aria-describedby`/`aria-labelledby`
references. Null removes an attribute; `replace()` replaces tokens explicitly.
False omits an attribute and true emits its name alone. Values are escaped when
the bag is serialized.

Configure input identity and behavior, such as `name`, `id`, `type`, `value`, and
`secure`, as field options rather than through input attribute bags.


## Theme API

`FormCompose\Rendering\Theme` is immutable. Pass it to the form builder's `theme($theme)` method before binding:

```php
use FormCompose\Rendering\Theme;

$theme = new Theme(
    layers: ['forms/application'],
    defaults: [
        'input' => ['class' => 'field-input'],
        'email.input' => ['class' => 'email-input'],
        'blueprint.control' => ['class' => 'form-section'],
    ],
);
$form = $this->Form
    ->form($article)
    ->theme($theme)
    ->bind();
```

`new Theme(array $layers = [], array $defaults = [])` stores readonly `layers`
and `defaults`. Layers are element prefixes in priority order. Empty prefixes or
prefixes containing `..` throw `InvalidArgumentException`. `Theme::base(): self`
returns a theme with no custom layers or defaults; bundled elements remain available.

The helper's `theme` setting accepts a `Theme`, `'Base'`, or null (bundled markup).
An explicit form theme replaces the helper theme, including layers and defaults.
Omitting the form setting or passing null inherits the helper theme. Helper
`setConfig()` changes future bindings and standalone actions; existing sessions
retain their selected theme. `themeDebug` defaults to false and requires true to
emit lookup comments. An explicitly supplied renderer keeps its own tracing
behavior; construct `PhpRenderer` with `themeDebug: true` when needed.

`attributes(string $part, string $type): Attributes` starts with generic defaults,
then replaces matching keys with type-specific defaults. An email class default
replaces the generic input class; subsequent field/local attributes merge on top.
This distinction matters when using CSS utility classes.

## Attribute bag API

`FormCompose\Rendering\Attributes` stores readonly `values` and escapes them when
cast to a string. It includes a leading space when attributes are present, so
`<div<?= (string)$attributes ?>>` is valid.

| Signature | Behavior |
| --- | --- |
| `new Attributes(array $values = [])` | Validates attribute names and normalizes class tokens. |
| `merge(array $values): self` | New bag; combines class and ARIA ID-reference tokens, replaces other keys. |
| `replace(array $values): self` | New bag; replaces matching keys without token merging. |
| `has(string $name): bool` | Whether the attribute would be emitted; null/false count as absent. |
| `__toString(): string` | Escaped attributes; false/null omitted, true emitted as a bare name. |

```php
use FormCompose\Rendering\Attributes;

$attributes = new Attributes(['class' => 'field', 'required' => true]);
$wide = $attributes->merge(['class' => ['wide' => true, 'hidden' => false]]);
// (string)$wide: ' class="field wide" required'
$replacement = $wide->replace(['class' => 'compact', 'required' => null]);

// (string)$replacement: ' class="compact"'
```

Arbitrary integration attributes such as `data-*`, `x-model`, `@click`, and
`:class` retain their names. Class values support strings, arrays of tokens, and
conditional class maps. Invalid attribute names throw `InvalidArgumentException`.
The bag does not resolve conflicting CSS utilities.

## Trusted markup

`Cake\View\HtmlString` explicitly marks developer-controlled markup for
labels, help, or button content. It does not sanitize HTML.

```php
use function Cake\View\html;

// Inside an open form.
echo $form->submit(html('<strong>Save article</strong>'));
```

`Cake\View\html(string $html): HtmlString` wraps trusted markup. The explicit
`new HtmlString(string $value)` constructor is also available. Cast the result to a
string to read its markup.
`HtmlString::render(string|HtmlStringable $text): string` escapes ordinary strings and preserves an
implementation of `Cake\View\HtmlStringable`, including `HtmlString` and application-owned
components. Custom templates should use this method when outputting content.
Attribute values always go through the attribute bag's escaping.

The former `FormCompose\Rendering\Html` wrapper has been removed. Replace its import
with `use function Cake\View\html;` and `new Html(...)` with `html(...)`.
Replace `Html::text(...)` with `HtmlString::render(...)`, importing `Cake\View\HtmlString`
where needed. Custom method signatures should accept `Cake\View\HtmlStringable` for
trusted content.

## Custom widget bodies

[Custom field types](/custom-fields) use two additional field body parts:

| Part | Prepared locals | Contract |
| --- | --- | --- |
| `widget` | `$field`, `$attributes`, scalar `$value` | `SingleInputType`: emit one editable input with the supplied name and ID. |
| `composite` | `$field`, `$attributes`, `$inputs` keyed by relative path | `CompositeType`: arrange child controls or their independently renderable parts. |

Both receive `$templateData` and `$parent()`, and support normal layers, variants,
and explicit `templates` overrides. Outer control elements can access children
through `$field->inputs`. Composite bodies receive wrapper-ready attributes with their supplied ID and
feedback references. `CompositeType` filters input-only attributes before rendering
the body, while child inputs retain their effective constraints.
The type declaration supplies semantics, and the PHP element owns layout.
