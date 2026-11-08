---
title: Bound fields
description: Inspect BoundField state and look up rendering, attribute, and handwritten-input registration methods.
---

# Bound fields

`$form->field()` returns a `FieldBuilder`; `resolve()` captures a
`FormCompose\Form\Field\BoundField`. It connects immutable
resolved state with independently renderable parts. See
[Compose field parts](/field-parts) for a full view example.

## state

`$field->state` is a readonly `FieldState` snapshot. Reading it registers nothing.

| Property | Type | Meaning |
| --- | --- | --- |
| `path` | `string` | Context lookup path, such as `comments.0.body`. |
| `name` | `string` | Submitted input name before native multiple-value adaptation. |
| `id` | `string` | Resolved input ID, also used for feedback associations. |
| `type` | `string` | Inferred or explicit field type. |
| `value` | `mixed` | Resolved value, before native rendering formats it. |
| `messages` | `list<string>` | Context validation messages; composites exclude nested child errors. |
| `composite` | `bool` | Whether the type declares independently bound children. |
| `requiredMessage` | `?string` | Required-validation text supplied by the CakePHP context, or null. Plain text for application templates and enhancements. |
| `constraints` | `array<string, mixed>` | Inferred/explicit browser constraints. |
| `config` | `FieldConfig` | Typed merged field configuration, including its `PresentationConfig`. |

```php
$title = $form->field('title')->resolve();
$currentTitle = $title->state->value;
$hasErrors = $title->state->hasErrors();
```

| State method | Return and behavior |
| --- | --- |
| `grouped(): bool` | True for radio, multi-checkbox, and composite controls. |
| `hasHelp(): bool` | Whether nonempty help is enabled. |
| `hasErrors(): bool` | Whether messages exist and error rendering is enabled. |
| `labelText(): string` | Escaped generated/configured label, preserving trusted `HtmlString`. |
| `helpText(): string` | Escaped configured help, preserving trusted `HtmlString`. |

`requiredMessage` is context metadata, separate from the effective `required`
constraint. It remains available when a field override makes the control optional,
hidden, or disabled. Native input rendering does not attach inline event handlers
or replace browser validation messages. An application can opt into its own
progressive enhancement using an escaped data attribute:

```php
// Inside an open form.
$field = $form->field('name')->resolve();
$message = $field->attributes()->has('required')
    ? $field->state->requiredMessage
    : null;
echo $field->input()->attributes(['data-required-message' => $message]);
```

Keep the value as text and let attribute rendering escape it. Server-side
validation stays in the existing CakePHP validator. Association selectors use the
validated association property; nested and scoped bindings use their matching
context paths.

The snapshot does not track subsequent entity or request changes. Resolve a new
bound field when you need a new snapshot.

For inspection inside a layout, see
[inspection inside a blueprint layout](/blueprints#inspect-values-and-field-configuration).

## control() {#control}

```php
public function control(): string;
```

Renders the complete field through its control template, or only `input()` for a
hidden field. It uses the configuration supplied when the field was resolved; it accepts
no additional options. Input rendering requires an open session.

```php
// Inside an open form; equivalent to $form->control('title', ...).
$title = $form->field('title')
    ->label('Article title')
    ->resolve();
echo $title->control();
```

## input() {#input}

```php
public function input(): PartBuilder;
```

Returns a `PartBuilder`. Echo or `render()` renders the input element, then registers the field type's actual submitted inputs.
Requires an open session and an unused input ID. The part builder's `attributes()` augments its
prepared bag; behavior and identity belong in [field options](/field-options).

```php
echo $title->input()->attributes(['class' => 'wide', 'autocomplete' => 'off']);
```

Do not also call `register()` or render the same input through `control()`.
Disabled native inputs register no submitted names, but their IDs still represent
a rendered presentation.

## label(), help(), errors() {#parts}

```php
public function label(): PartBuilder;

public function help(): PartBuilder;

public function errors(): PartBuilder;
```

Each method returns a renderable part builder. Use `attributes()`, `template()`,
`variant()`, and `templateData()` on it before echo or `render()`. Rendering these
parts registers no inputs. A disabled label/help/errors part emits an empty string.
A grouped field uses a legend rather than an ordinary input label.

```php
echo $title->label()->attributes(['class' => 'caption']);
echo $title->input();
echo $title->help()->attributes(['class' => 'hint']);
echo $title->errors()->attributes(['class' => 'feedback']);
```

Render enabled feedback to preserve the input's generated ARIA relationships.
`close()` checks these generated references against rendered or registered part
IDs. It throws `LogicException` for missing targets and leaves the form open for
correction. Explicit external descriptions are application-owned.
Configure help/error IDs through field `partAttributes`; local ID overrides on
`help()` and `errors()` are rejected. Rendered part IDs join the session's
duplicate-ID checks. A complete control commits its input registrations only
after all parts succeed.

For summary-only error display, keep errors enabled and remove the generated
input description explicitly:

```php
echo $title->input()->attributes(['aria-describedby' => null]);
```

The application remains responsible for any external ARIA references it supplies.

## attributes() {#attributes}

```php
public function attributes(string $part = 'input', array $extra = []): Attributes;
```

Returns a `FormCompose\Rendering\Attributes` bag without rendering or registration.
Attribute targets include `container`, `input`, `label`, `help`, and `errors`.
`container` supplies attributes to the outer `control.php` element; it is not a
separate template part or rendering method. Input attributes include name, ID, constraints, and ARIA descriptions;
native type/value attributes are prepared later by the field type.

```php
$bag = $title->attributes('input', ['class' => 'wide']);
```

Cast the bag to a string inside your input tag to emit the attributes.

Input bags reject identity/behavior keys including `name`, `id`, `type`, and
`value`. See [attribute options](/field-options#attributes) for precedence and
the full reserved-key list, and the [attribute-bag API](/template-reference#attribute-bag-api)
for merging and serialization.

## register() {#register}

```php
public function register(array $attributes = []): void;
```

Registers a handwritten input through the same submission contract as `input()`.
Requires an open form and unused input ID. Pass the same local input attributes
used by the handwritten markup, especially `disabled`. It does not render markup,
add fallback inputs, or infer extra submitted names from HTML.
If the field has errors, registration adds an unlinked entry to an optional error
summary; it does not infer focus targets from configured choices or custom types.

Use it once, after emitting the input. This example belongs inside an open form
and handles an ordinary scalar text field:

```php
<?php

use Cake\View\HtmlString;

$title = $form->field('title')
    ->type('text')
    ->resolve();
?>
<?= $title->label() ?>
<input type="text"<?= $title->attributes() ?> value="<?= HtmlString::render((string)$title->state->value) ?>">
<?php $title->register(); ?>
<?= $title->help() ?>
<?= $title->errors() ?>
```

`attributes()` excludes the native type and formatted value. For local input
attributes, pass the same bag to `attributes('input', $local)` and `register($local)`.
File/password, checkbox, choice, and composite fields need their additional native
formatting and submission behavior. Prefer their `input()` methods, or supply a
custom type with matching [submission records](/custom-field-reference#low-level-submission-records).
For a composite, register each actual child; registering the parent does not
register its declared children.

For handwritten feedback, emit a `help` or `errors` element using
`attributes($part)`, then call `$form->registerFeedback($field, 'help')` or use
`'errors'`. This reserves the configured part ID without rendering
or registering submitted fields. Other part names are rejected. Do not also call
the part's rendering method. Pass the same local attributes to inspection and
registration; help/error identity overrides remain configured through
`partAttributes`.

## renderInput() {#render-input}

```php
public function renderInput(Attributes $attributes): string;
```

Template-facing hook that delegates to the selected field type. Used inside an
`input.php` element; it does not register again because the outer `input()` call
handles registration after successful rendering.

```php
<!-- Inside a custom input.php element, with prepared $attributes: -->
<?= $field->renderInput($attributes) ?>
```

Application views normally call `input()` instead. See the
[template reference](/template-reference#standard-input-markup) for native
markup and choice parts.

## Composite children

`$field->inputs` contains the declared relative paths mapped to bound children, or
`[]` for an ordinary field. Reading it resolves the declarations without rendering
or registering inputs. Each child supports the same state and field-part API.
`$field->parent` exposes its enclosing composite, or null for a root field.

`$field->focusTarget` is null until an eligible input renders successfully, then
contains its actual focus ID. Failed enclosing layouts roll that metadata back.
Custom types can use `$field->renderer` to render parts through the shared pipeline.
See [Custom field types](/custom-fields) for complete declarations and layouts.
