---
title: Field configuration
description: Fluent setters, immutable FieldConfig snapshots, merge rules, and native control behavior.
---

# Field configuration

Field behavior uses typed methods. HTML attributes and application-owned data
remain arrays because their keys are intentionally open-ended.

## Start with a control

```php
echo $form->control('email')
    ->type('email')
    ->label('Email address')
    ->help('Used for account notifications.')
    ->required()
    ->attributes(['autocomplete' => 'email']);
```

`control()` returns a mutable `ControlBuilder`. Echo or `render()` resolves and
renders it. `field()` returns a `FieldBuilder`; call `resolve()` for an immutable
`BoundField` whose parts can be arranged independently.

### Reuse configuration

```php
use FormCompose\Configuration\FieldConfig;

$emailConfig = new FieldConfig(type: 'email', label: 'Email address')
    ->required()
    ->attributes(['autocomplete' => 'email']);

echo $form->control('email', config: $emailConfig);
echo $form->control('billing.email', config: $emailConfig)->label('Billing email');
```

`FieldConfig` supports named constructor arguments and fluent methods on the same
immutable object. `FieldConfig::builder()` is a convenience factory returning a
new `FieldConfig`. Every fluent call returns a new config; keep that return value
when deriving a preset.
`merge()` overlays explicitly configured settings without changing either input.

Pass configs directly wherever a field config is accepted. Configuration setters
return new configs; rendering and declaration builders update their attached
occurrence. Explicit `render()` is useful when assigning HTML; echo already renders
controls and parts.

## Identity and type

| Setter | Behavior |
| --- | --- |
| `type(string)` | Override inference: native input type, `textarea`, `select`, `radio`, `multicheckbox`, or custom alias. |
| `name(string)` | Override the submitted bracket name, keeping the original context path. |
| `id(string)` | Set a unique explicit input ID. |

Paths are nonempty dot-separated letters, digits, underscores, or hyphens.
`comments.0.body` submits `comments[0][body]`. Generated IDs include the binding ID;
dots remain dots and literal hyphens are encoded as `:2d` to avoid collisions.
Read IDs from `$field->state->id`; use `getElementById()` or `CSS.escape()` in JS.
All rendered input, choice, container, and feedback IDs must be unique.

### Type inference

An explicit type wins. Otherwise inference applies association selectors and
primary keys, password terminal names, choices (including schema enums), exact
email/telephone terminal names, then schema mappings. Password fields never
prefill, even if choices or a value are supplied.

| Schema type | Control |
| --- | --- |
| `boolean` | `checkbox` |
| `text` | `textarea` |
| Integer, decimal, float types | `number` |
| `date`, `time` | `date`, `time` |
| Datetime and timestamp types | `datetime-local` |
| `binary` | `file` |
| Other | `text` |

Foreign keys ending in `_id` infer selects; `tags._ids` infers a multiple select.
Terminal `email` infers email; `tel`, `telephone`, and `phone` infer tel. Names are
case-sensitive. Override schema mappings with the form builder's `typeMap()` and
discover custom aliases with [`AsFieldType`](/custom-fields). Use `types()` for
explicit instance overrides. Non-choice types do not consume choice
iterators or reserve choice IDs.

## Values

| Setter | Behavior |
| --- | --- |
| `value(mixed)` | Explicit override, including null, false, zero, and empty string. |
| `default(mixed)` | Fallback when no source supplies a value; also passed to context lookup. |
| `schemaDefault(bool = true)` | Allow context schema defaults. |
| `timezone(DateTimeZone\|string\|null)` | Convert temporal objects/timestamps for native rendering; null preserves object timezone. |

Default lookup is explicit value, submitted `data`, then `context`, then default.
A submitted null counts as found; a context null falls through. Change source
order on the form builder with `valueSources()`. File and password inputs never
prefill. Enums become scalar backing values; native controls accept scalar or
stringable values, choice arrays, and supported temporal objects.

Temporal strings pass through as local wall-clock values. Date/time objects use
native HTML formats; integer Unix timestamps use UTC unless a timezone is set.
Date-only and time-only objects reject timezone conversion. Time inputs default
to `step="1"`; fractional schemas use `step="0.001"`. Fractional steps and `any`
format milliseconds. Set or clear the step through `attributes(['step' => ...])`.

## Labels and feedback

| Setter | Behavior |
| --- | --- |
| `label(string\|HtmlStringable\|Closure\|false\|null)` | Null infers a humanized label; false suppresses it. Groups use a legend. |
| `help(string\|HtmlStringable\|Closure\|false\|null)` | Nonempty text renders associated help; false/null omit it. |
| `errors(bool = true)` | Enable inline feedback, summary entries, and generated error descriptions. |

Ordinary text is escaped; trusted HTML uses `Cake\View\HtmlString` or another
implementation of `Cake\View\HtmlStringable`.
Call CakePHP translation functions in templates or blueprint hooks:

```php
use function Cake\I18n\__;

echo $form->control('email')
    ->label(__('Email address'))
    ->help(__('We keep this private.'));
```

Use zero-argument label/help closures when a reusable config must evaluate text
later. They resolve once per field snapshot after overrides merge and return
string, `HtmlStringable`, false, or null. Call CakePHP's translation functions inside the
closure for deferred translation. See [translation timing](/blueprint-reference#translation).

## Constraints and state

| Setter | Behavior |
| --- | --- |
| `required(?bool = true)` | Override or restore inferred required state. |
| `disabled(bool = true)` | Disable inputs, choices, fallbacks, and native submission registration. |
| `readonly(bool = true)` | Read-only where supported by native HTML. |
| `multiple(?bool = true)` | Override or restore inferred multiple selection. |

CakePHP validation supplies browser constraints where possible. Configure
additional native constraints with input attributes. Required checkbox groups do
not make every box required; validate collection cardinality in the application.

## Choices and fallbacks

| Setter | Behavior |
| --- | --- |
| `choices(iterable\|string\|null)` | Map, typed choices/groups, enum cases, or backed enum class. Explicit null/[] opts out of discovery. |
| `placeholder(bool\|string\|null)` | Select placeholder; false disables, true requests blank, null uses default behavior. |
| `checkedValue(mixed)` | Single checkbox submitted value; default `'1'`. |
| `uncheckedValue(mixed)` | Its hidden fallback value; default `'0'`. |
| `fallback(bool = true)` | Enable hidden fallbacks for unchecked/empty choices. |

Optional single selects get a blank placeholder unless a real empty-string
choice exists. Other choice fallbacks submit an empty string under the base
name. Multiple names gain `[]`. Disabled controls disable their fallbacks too.
See [choices](/choices) for `ChoiceConfig`, `ChoiceGroupConfig`, and enums.

### Typed choice metadata

`ChoiceConfig(value, label, attributes, id)` configures one option.
`ChoiceConfig::builder(value, label)` returns an immutable config with `label()`,
`id()`, `disabled()`, and `attributes()` setters. Omitted enum labels use the enum's
label; explicit null or empty string supplies a blank label.

`ChoiceGroupConfig(label, choices, attributes)` groups choices.
Its `builder(label, choices)` factory and `choices()` / `attributes()` methods
return immutable group configs. For example:

```php
use FormCompose\Configuration\ChoiceConfig;
use FormCompose\Configuration\ChoiceGroupConfig;

echo $form->control('audience')->choices([
    ChoiceGroupConfig::builder('Internal')
        ->choices([
            ChoiceConfig::builder('staff', 'Staff')->attributes(['data-tier' => 'internal']),
            ChoiceConfig::builder('legacy', 'Legacy team')->disabled(),
        ])
        ->attributes(['data-group' => 'internal']),
]);
```

Labels are escaped unless wrapped in trusted `HtmlString`. Lazy choice iterables are
consumed when rendering. An explicit non-choice type retains its type and does not
consume those iterators. Backed enum classes supply cases in declaration order;
iterable keys do not replace case backing values. Pure enums are rejected.
CakePHP schema `enum-*` types discover registered backed enums automatically,
including on associated fields. `choices(null)` or `choices([])` opts out.

A real empty-string choice takes precedence over a generated placeholder; both
submit an empty string. Zero remains a valid choice value. `readonly()` has no
native effect on selects, radios, or checkboxes. Validate permitted values and
collection cardinality in your application.

### Association conventions

For conventional choices, `_id` paths look for a pluralized view variable;
`tags._ids` looks for `$tags`. Other inferred choice fields use the pluralized
final property, such as `$statuses` for `status`. Variables must be iterable and
are read from the current view when resolving the field. Explicit `choices()`
takes priority, including null or an empty list.

Collection selectors work with `belongsToMany` and `hasMany`. Loaded associated
entities supply their identifiers, including single-column UUID/string keys.
Custom association properties use their property name: `topic_tags._ids` looks
for `$topicTags`. Nested selectors such as `comments.0.tags._ids` follow the same
rules. The context supplies association schemas even when no child entity exists.

Inferred `._ids` fields are multiple selects. An explicit `type('select')` keeps
its type; add `multiple()` if needed. `multiple(false)` overrides inferred state.
Collection validation and required metadata come from the association property;
errors on `tags` appear on `tags._ids`. Submitted empty arrays, empty strings,
and null do not restore old selected identifiers. Ordinary value-source ordering
and explicit field overrides still apply. See [association examples](/associations).

## Attributes

`attributes(array)` configures the input's HTML attributes. `partAttributes(target,
attributes)` configures another attribute bag:

| Attribute target | Applies to | Rendered by |
| --- | --- | --- |
| `container` | The complete control's outer wrapper. | `control.php` |
| `label` | Label or legend. | `label.php` |
| `help` | Help text element. | `help.php` |
| `errors` | Validation feedback element. | `errors.php` |

`container` is an attribute target, not a separate renderable or template part.
For example, `partAttributes('container', ['class' => 'article-field'])` adds a class
to the wrapper produced by `control.php`. A custom control element must output
its supplied `$attributes` for those settings to appear.
Arbitrary `data-*`, Alpine, HTMX, and other integration attributes are supported.
Native identity/state belongs in typed setters. A null attribute removes an
inherited value; false omits a boolean attribute. Values are escaped.

Attribute layers merge by key, combine classes, and deduplicate ARIA ID lists.
`replaceAttributes()` and `replacePartAttributes()` explicitly discard inherited
bags. Generated feedback identities are still managed for accessibility.

## Templates

Immutable configs and mutable rendering/declaration builders share these method names:

| Setter | Behavior |
| --- | --- |
| `template(string)` | Select the primary element: control, field part, blueprint layout, or complete form layout. |
| `partTemplate(string, string)` | Select an explicit element for one named part. |
| `variant(?string)` | Select or clear a theme variant. |
| `templateData(array)` | Merge application-owned variables, available in `$templateData`. |
| `replaceTemplates(array)` | Replace the complete part-to-element map. |
| `replaceTemplateData(array)` | Replace the entire template data bag. |

A field's template data is inherited by its parts. Local part settings stay local.
Templates use [lookup precedence](/template-reference#lookup-order). A config's
`presentation` property is a typed `PresentationConfig`, keeping these settings
separate from field behavior and bound state.

## Submission protection

`secure(bool = true)` controls CakePHP tamper protection for the field. Keep it
enabled for normal inputs. Disabled controls submit nothing. Rendering registers
the actual submitted names; inspecting a field registers nothing. Custom types
must describe their real submission contract. See [custom fields](/custom-fields).

## Configuration precedence

Blueprint defaults are overlaid by an explicit per-call config and then fluent
edits. Configs are already immutable. A rendering or declaration builder's `config()`
exposes its current authored settings;
`resolve()->state->config` exposes the final merged field settings. Only explicitly
supplied settings override earlier values. Explicit null
is preserved where accepted. Presentation maps merge by key, with class/ARIA
combining; replacement setters preserve their intent across config snapshots.
`extensionData(array)` is an explicit application/custom-type configuration bag
read through `$field->state->config->extensionData`; it replaces its previous bag.
Use `templateData()` for presentation variables instead.
