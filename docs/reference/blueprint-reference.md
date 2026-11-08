---
title: Blueprint reference
description: Blueprint authoring, scoped views, context, discovery, and compiled configuration.
---

# Blueprint reference

Blueprints author reusable configuration through PHP hooks. Rendering and
submission state belong to the enclosing form session.

## Presentation options

Configure the blueprint layout directly on `$form`: `template()`, `partTemplate()`,
`variant()`, `attributes()`, `partAttributes()`, `templateData()`, and explicit
replacement methods. These update the attached blueprint.
Embed declaration builders and renderable scoped occurrences use the same methods.

## Blueprint declarations

### Form

`#[Form('article')]` supplies an optional discovery alias. It accepts no field,
layout, template, or data settings. Class references remain supported explicitly.

### Field

Declare fields with `BlueprintBuilder::add(string $path, FieldConfig|null $config = null)`.
Pass an immutable `FieldConfig` directly. The returned attached `FieldDeclarationBuilder`
exposes all [field setters](/field-options) and compiles a field declaration during `build()`.

### Embed

`embed(string $path, string $blueprint)` declares one child; `embedMany(...)`
declares keyed rows. A single declaration returns `EmbeddedBlueprintBuilder`
with layout presentation methods. Repeated declarations return
`RepeatedBlueprintBuilder`, adding `keys(?array)`, `label()`, and `errors()`.
Configure occurrence presentation on either builder.

### AsFieldType

`#[AsFieldType('date-range')]` declares the discovery alias for a
`FieldTypeInterface` implementation. See [custom field types](/custom-fields).

### Declaration behavior

Hooks use normal PHP inheritance. Call `parent::configure()` explicitly to retain
parent declarations. Duplicate field/embed paths fail; use `field()` to edit or
`remove()` followed by a new declaration for an intentional replacement.
Cycles and collisions across embedded children also fail.

## Translation

Use CakePHP's translation functions in hooks for labels, help, and choices.
Ordinary strings capture the locale when the hook compiles at binding. Set the
request locale before that point; bind a new form to rerun those calls.

For reusable configs that must evaluate text at resolution, use a zero-argument
label/help closure:

```php
use FormCompose\Configuration\FieldConfig;
use function Cake\I18n\__;

$title = FieldConfig::builder()
    ->label(static fn(): string => __('Article title'));
```

Closures return string, trusted `HtmlStringable`, false, or null. They run after overrides
merge, once per bound-field snapshot; overridden callbacks do not run. An existing
snapshot retains its text after locale changes. Resolve a fresh field to reevaluate
its closure. Choices use ordinary translated labels or CakePHP's
`EnumLabelInterface`. Literal translation calls, including those inside closures,
remain visible to CakePHP's message extractor.

## configure() and BlueprintBuilder

```php
use FormCompose\Blueprint\BlueprintBuilder;
use FormCompose\Blueprint\FormContext;
use function Cake\I18n\__;

public function configure(BlueprintBuilder $form, FormContext $context): void
{
    $form->add('title')->required();
    $form->embed('author', 'author');
    $form->field('author.email')->label(__('Contact email'));
    $form->template('forms/article');
}
```

Add the imports at the top of the blueprint file and the method inside its class.
The `author` blueprint is defined in [composition](/composition#nest-a-child-blueprint);
`forms/article` is the [article layout](/blueprints#keep-control-of-the-layout).

| Method | Result |
| --- | --- |
| `add(string, ?FieldConfig)` | Attached field builder for a new declaration. |
| `field(string)` | Edit an existing field or stage a qualified child override. Unknown targets fail at build. |
| `embed(string, string)` | Deferred single-child declaration builder. |
| `embedMany(string, string)` | Deferred repeated declaration builder; select keys before compilation. |
| `remove(string)` | Remove a field or entire embed; retains omission history. |
| `template()`, `partTemplate()`, `attributes()`, `templateData()` | Configure the attached collection layout. |
| `build()` | Immutable compiled `FormDefinition`; normally called by the compiler. |

## FormContext

| Member | Meaning |
| --- | --- |
| `request` | Original CakePHP request. |
| `context` | CakePHP context scoped to the occurrence. |
| `path` | Absolute mount within the root form, or empty at root. |
| `at(string)` | New context scoped below this occurrence. |
| `value(string)` | Effective relative value using the binding's source order. |

Request lookups retain full paths; context and `value()` lookups are relative. Services belong
in blueprint constructor dependencies. Hooks change configuration, never values,
validation, or persistence.

A layout is a CakePHP element: `$this->getRequest()->getData('author.email')`
reads a full request path, while `$form->field('email')->resolve()->state->value`
reads the scoped field's effective value after sources and overrides.

## Configuration order

A fresh blueprint is configured once for each occurrence. Parent authoring
finishes before children compile; repeated keys therefore exclude unnecessary
row hooks. Qualified parent edits overlay compiled child fields. Per-call configs
and fluent edits overlay those defaults at resolution. Only explicitly configured
settings override prior values. Presentation maps merge; explicit replacement
setters retain their intent through snapshots.

## BlueprintView

A blueprint PHP layout receives a scoped `BlueprintView` as `$form`.

| Method | Contract |
| --- | --- |
| `has(string): bool` | Final field/embed membership without side effects. |
| `field(string, ?FieldConfig): FieldBuilder` | Relative field authoring; `resolve()` captures state. |
| `control(string, ?FieldConfig): ControlBuilder` | Relative control; removed fields emit nothing, unknown paths fail. |
| `controls(iterable): ControlBatch` | Relative batch with atomic registration rollback. |
| `embedded(string): PartBuilder` | Render an already compiled single child. |
| `embeddedMany(string): EmbedBuilder` | Render compiled rows with local presentation; cannot change selected keys. |
| `render(?PresentationConfig = null): string` | Render this occurrence's layout without opening another form. |

Common fluent presentation setters apply to returned renderables. Layouts cannot
open/close the session. Field inspection remains free of rendering/registration.
Removed controls retain the full fluent API and emit nothing; resolving a removed
field is an error, so guard manual parts with `has()`.

### Inspect values in a layout

Resolve a field when its effective value is needed for view logic:

```php
$title = $form->field('title')->resolve();
$currentTitle = $title->state->value;
```

This reads the scoped value after binding sources and overrides without rendering
or registering an input. The [bound-field reference](/field-reference) lists the
available state. Read raw full request paths through `$this->getRequest()` when
needed; that request is not scoped to an embedded blueprint.

## BlueprintFactory

`compile(string $blueprint, FormContext $context): FormDefinition` resolves aliases
or class names and compiles an isolated tree. `repeated()` compiles a repeated
occurrence. Protected compiler/builder factories are extension boundaries; use
the configured factory so DI, cycle detection, and custom services are preserved.
See [services and discovery](/blueprint-configuration).

## Repeated blueprints and occurrence options

`EmbedConfig::builder()` exposes `label(string|HtmlStringable|null)`,
`errors(bool = true)`, and shared presentation methods. The factory returns an
immutable `EmbedConfig`; fluent calls return new configs, and `merge(EmbedConfig)`
overlays explicit settings. Constructors accept label and errors by name. Row selection belongs to `keys(?array)` on the
`RepeatedBlueprintBuilder` returned by blueprint `embedMany()` and the
`RepeatedEmbedBuilder` returned by session `embedMany()`. Null keys infer rows;
empty keys render no rows. Each selected row has its own scoped blueprint hook. Keys must be unique valid
path segments; sparse integer and string keys are preserved. Inferred submitted
collections, including explicit empty collections, take priority over entity rows.
Keys are selected before row hooks run, so excluded rows are not compiled. Layout
presentation changes do not recompile rows.
`embeddedMany()` on a compiled layout returns `EmbedBuilder`, which only changes
occurrence presentation and exposes no row-selection method.

`RepeatedView` exposes keyed `rows`, `rows()`, direct `messages`, `hasErrors()`,
`label()`, and `errors()`. Layouts render collection errors separately from row
field feedback. See [repeated embeds](/repeated-embeds).

## Internal compiled configuration

`FormDefinition`, `FieldDefinition`, `EmbeddedDefinition`, and `RepeatedDefinition`
are immutable compiled records, not the application authoring API. Field records
hold `FieldConfig`; collection records hold `PresentationConfig` or `EmbedConfig`.
`FieldSpec(path, config)` is a public reusable declaration for control batches
and composite children.
