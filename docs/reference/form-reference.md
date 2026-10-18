---
title: Helper and form session
description: Typed fluent form setup, binding, rendering, and submission lifecycle contracts.
---

# Helper and form session

Use `FormHelper::bind()` to start a manually arranged form directly. Use
`FormHelper::form()` for fluent configuration before binding or rendering a
complete form. Both use the same binding, rendering, and CakePHP protection services.

## bind() {#bind}

```php
$form = $this->Form->bind($article);
```

`bind(mixed $data = null, FormConfig $config = new FormConfig()): FormSession`
accepts ORM entities, CakePHP `Form` objects, array contexts, `ContextInterface`,
or null. It prepares an isolated session without rendering markup or registering
submitted inputs. Use its `open()`, `control()`, `submit()`, and `close()` methods
to arrange the form in your view.

For additional form settings, configure a fluent builder before binding:

```php
$form = $this->Form
    ->form($article)
    ->method('patch')
    ->url(['action' => 'edit', $article->id])
    ->attributes(['class' => 'article-form'])
    ->bind();
```

Each call creates a fresh session. Deriving another config or changing helper
defaults leaves existing sessions unchanged.

If you already have a reusable `FormConfig`, pass it directly as the `config`
argument to either helper method.

## form() {#form}

```php
echo $this->Form
    ->form($article)
    ->blueprint('article')
    ->submit('Save article');
```

`form(mixed $data = null, FormConfig|null $config = null): FormBuilder` configures
a form with the same accepted data and configuration. Echoing it opens
the form, renders the selected blueprints and actions, and closes it.

For fluent configuration before manual rendering, `FormBuilder::bind(): FormSession`
is also available. Each call captures a fresh session from the builder's current
settings; later builder changes do not change existing sessions.

### Binding options

These settings are available on both `FormBuilder` and immutable `FormConfig`
instances, including those created by `FormConfig::builder()`:

| Method | Contract |
| --- | --- |
| `url(array\|string\|false\|null)` | Cake route or URL; null uses the request URL, false omits the action. |
| `method(?string)` | GET, POST, PUT, PATCH, or DELETE. Null uses context defaults. Non-native methods use a POST method override. |
| `id(?string)` | Explicit page-unique form ID; null generates one. |
| `multipart(bool = true)` | Enable multipart uploads without changing the HTTP method. |
| `enctype(?string)`, `encoding(?string)` | Form encoding and accepted character set. |
| `valueSources(array)` | Ordered list of `data`, `query`, and `context`; default `['data', 'context']`. |
| `context(array)` | CakePHP context-provider options. |
| `passwordFields(array)` | Replace the terminal password field names; default `['password', 'passwd']`. |
| `typeMap(array)` | Override schema-type to control-type mappings. |
| `types(array)` | Register aliases mapped to `FieldTypeInterface` instances. |
| `blueprint(?string)` | Append reusable field defaults and body declarations by alias or class. Calls accumulate in order; null clears the selection. |
| `theme(Theme\|null)`, `renderer(?RendererInterface)` | Per-form rendering collaborators. |
| `errorSummary(bool\|SummaryConfig = true)` | Configure the complete form's summary. |
| `submit(string\|Html\|false\|null, ?ButtonConfig)` | Configure the complete form's action; null uses translated Submit. |
| `withoutSubmit()` | Omit that action. |
| `submitConfig(ButtonConfig)` | Configure its presentation and native button state. |

On `FormBuilder`, setters update that builder and `apply(FormConfig)` overlays
explicit settings. On `FormConfig`, fluent methods return new configs and
`merge(FormConfig)` combines explicit settings. `FormConfig::builder()` returns
`FormConfig` itself; pass it directly wherever a config is accepted. Common settings
also have named constructor arguments: `new FormConfig(url: ..., method: ..., id: ...)`.
`FormConfig::blueprints` exposes the ordered list of selected aliases or classes.
When a config explicitly supplies a blueprint selection, `apply()` and `merge()`
replace the whole selection; an unspecified selection preserves existing defaults.
The shared [presentation API](/field-options#templates) configures attributes,
templates, variants, and template data.

### Value sources

`valueSources(array $sources)` selects where displayed field values are read.
It does not change where or how the browser submits the form. Configure it before
`bind()`. The [search-form guide](/search-forms) shows a complete GET example.

| Source | Reads from |
| --- | --- |
| `data` | Parsed request body: `$request->getData()`. |
| `query` | URL query parameters: `$request->getQueryParams()`. |
| `context` | The entity, CakePHP Form, or context passed to `bind()` or `form()`. |

The default order is `['data', 'context']`. Sources are checked in order;
`['query', 'context']` reads URL parameters first and uses the bound object as a
fallback. The list must be nonempty and contain only these source names.
An explicit field `value()` overrides every source. Missing sources fall back
to the field's `default()`; context lookup also receives `default()` and
`schemaDefault()` according to CakePHP's context contract. Submitted null, false,
zero, and empty strings count as present. A context null falls through.

### Helper configuration

CakePHP helper loading still uses CakePHP's own configuration array:

```php
$this->addHelper('Form', [
    'className' => 'FormCompose.Form',
    'theme' => $theme,
]);
```

The helper also accepts `passwordFields`, `typeMap`, `contextFactory`,
`themeDebug`, and `autoActionScript`. Use a form builder for `valueSources`,
custom type instances, and a renderer, or pass them through `FormConfig` to `bind()`.
Per-form settings override helper defaults.
During normal controller rendering, the plugin supplies the application's
container-backed blueprint factory automatically. The optional helper setting
`blueprintFactory` takes precedence; use it for manually constructed views or a
custom factory. See [blueprint services](/blueprint-configuration) for examples.

## open() {#open}

```php
echo $form->open();
```

`open(): string` renders the form tag, CSRF field where supplied by CakePHP,
and method override. Configure the form **before** binding; `open()` accepts no options.
Existing entities default to PUT, new entities to POST. GET uses native query submission.
A file input requires a multipart form.

### Form-opening options

Use the form builder's `url()`, `method()`, `id()`, `multipart()`, `enctype()`,
`encoding()`, and `attributes()` methods. Select opening/closing elements with
`partTemplate('open', ...)` and `partTemplate('close', ...)`. `template()` selects
the complete form's `layout` element.

## control() {#control}

```php
echo $form->control('email')
    ->label('Email address')
    ->required()
    ->attributes(['autocomplete' => 'email']);
```

`control(string $field, FieldConfig|null $config = null): ControlBuilder` configures
a complete control. Echo or `render(): string` resolves its final settings and
renders it. Creating or configuring a control renders nothing. See
[field configuration](/field-options) for every setter.

## controls() {#controls}

```php
echo $form->controls([
    'title',
    $form->control('body')
        ->type('textarea')
        ->attributes(['rows' => 12]),
    $form->control('published')->type('checkbox'),
]);
```

Accepts an iterable of paths, `FieldSpec` objects, or `ControlBuilder` objects
from this session. It validates declarations and ownership before rendering.
The batch has no wrapper and rolls back registrations if any control fails.
Use `new FieldSpec('email', $emailConfig)` for reusable configured lists.

## field() {#field}

```php
$title = $form->field('title')
    ->help('Visible in article lists.')
    ->resolve();
$currentTitle = $title->state->value;
```

`field(string $field, FieldConfig|null $config = null): FieldBuilder` configures a
field; `resolve(): BoundField` captures immutable state. Resolution, state reads,
and attribute inspection render nothing and register no inputs. Use
[bound field parts](/field-reference) to arrange custom HTML.

## blueprint() {#blueprint}

```php
echo $form->blueprint('article')->template('forms/article');
```

`blueprint(?string $blueprint = null, ?string $at = null): PartBuilder` renders
a collection inside an open form. Null renders all of the binding's selected
blueprints in order, retaining their individual layouts. The composition shares
one input-registration transaction, so a later layout failure rolls back earlier
sections too.
An explicit selection stays local to this occurrence. `at: 'author'` scopes it
to that context path. Layout presentation uses the shared fluent methods.
With several selected blueprints, presentation overrides apply to each one.
A shared occurrence `id` is rejected before rendering; use a surrounding wrapper
or configure separate blueprint IDs. A single selection or explicit local
occurrence still accepts `attributes(['id' => 'details'])`.

## embedMany() {#embed-many}

```php
echo $form->embedMany('comments', 'comment')
    ->keys([0, 2])
    ->label('Comments');
```

`embedMany(string $path, string $blueprint, EmbedConfig|null $config = null): RepeatedEmbedBuilder`
compiles and renders repeated rows at that path. Null keys infer bound keys;
`[]` renders no rows. See [repeated embeds](/repeated-embeds).

## submit() {#submit}

```php
echo $form->submit('Save')
    ->name('intent')
    ->value('save')
    ->attributes(['class' => 'button']);
```

`submit(string|Html $label, ButtonConfig|null $config = null): ButtonBuilder` creates
a renderable button. Setters include `type(string)`, `name(?string)`,
`value(mixed)`, `disabled(bool = true)`, and common presentation methods.
Named enabled buttons participate in the submission contract after successful
rendering. Ordinary labels are escaped; trusted markup requires `Html`.

## unlock() {#unlock}

For a collection whose rows are added or removed in JavaScript, explicitly unlock
its CakePHP dot path while the form is open:

```php
$form = $this->Form->bind($team);
echo $form->open();
$form->unlock('members');
echo $form->control('members.0.name');
echo $form->close();
```

`unlock(string $path): void` allows the named field and its descendants to be
omitted, changed, or added without failing CakePHP form protection. Other fields
and form sessions retain their protection. Call it before or after rendering the
collection's controls, while the form is open. Use it only for application-managed
inputs, and validate and authorize submitted rows in the application. It requires
an open session and does not enable protection when FormProtectionComponent is absent.

## close() {#close}

`close(): string` validates rendered identities and feedback targets, renders
final tamper-protection fields, and closes the form. Inputs may only render while
it is open. Configure CakePHP's CSRF middleware and FormProtectionComponent in
the application; loading this helper does not enable them.

## Complete-form render() {#render}

```php
echo $this->Form
    ->form($article)
    ->blueprint('article')
    ->errorSummary()
    ->submit('Save article');
```

`FormBuilder::render()` binds a fresh session and renders the whole form.
Echoing the builder has the same effect. `FormSession::render(): string` opens
that existing session, buffers its selected blueprints, renders the summary and
layout, then closes it. Configure complete rendering before binding; session
`render()` accepts no settings.
Chain `blueprint('article')->blueprint('article_more')` before binding to compose
several blueprints. Their field defaults are combined, and duplicate field or
embed paths fail before the form opens. See [blueprint composition](/composition#combine-blueprints).

### Application-owned complete-form layouts

Select `template()` on the form builder for an outer layout. Create the element
in the second tab; the `article` blueprint is defined in the
[blueprint guide](/blueprints).

::: code-group

```php [View]
echo $this->Form
    ->form($article)
    ->blueprint('article')
    ->errorSummary()
    ->template('forms/editor')
    ->submit('Save article');
```

```php [templates/element/forms/editor.php]
<?php
declare(strict_types=1);

/**
 * @var \FormCompose\Form\FormSession $form
 * @var \FormCompose\Configuration\ButtonConfig $submitConfig
 * @var string $summary
 * @var string $body
 * @var string|\FormCompose\Rendering\Html|false $submit
 */
?>
<?= $summary ?>
<section class="form-body"><?= $body ?></section>
<div class="form-actions">
    <?php if ($submit !== false): ?>
        <?= $form->submit($submit, config: $submitConfig) ?>
    <?php endif; ?>
</div>
```

:::

The form is already open. Emit the prepared `$summary` and `$body` once, then
render actions; do not open or close another form. Fields added by this outer
layout after summary preparation do not appear in that summary. See
[layout variables](/template-reference#complete-form-layouts) for the full contract.

## errorSummary() {#error-summary}

```php
echo $form->errorSummary()
    ->title('Please review these fields')
    ->attributes(['class' => 'errors']);
```

`errorSummary(SummaryConfig|null $config = null): SummaryBuilder` renders a summary
of errors from successfully rendered controls. Render it after the controls;
buffer their HTML first if the summary should appear above them. Inspection
alone adds no summary entries. `errors(false)` suppresses that field's entry.
See [error summaries](/error-summaries).

### Summary entries

Controls contribute errors in rendering order with their resolved labels. Hidden,
disabled, empty/all-disabled choice controls do not become focus targets.
`errors(false)` suppresses inline messages and summary entries. Collection errors
from repeated embeds are unlinked; row-field errors link to their actual controls.
Composite group errors target the first eligible rendered child and do not
duplicate nested child errors. Handwritten registrations contribute unlinked
messages because registration cannot prove which focusable element was emitted.

Suppressed or blank field labels use the humanized field name in the summary. Text is escaped, and summary
links target only IDs actually recorded by the session. Failed rendering rolls
back feedback along with submitted names and IDs.

## hiddenFields() {#hidden-fields}

`hiddenFields(array $fields, PresentationConfig $presentation = new PresentationConfig()): string`
renders internal metadata name/value pairs through the shared hidden element.
For application fields, set `type('hidden')` on a `control('record_id')` builder so binding,
registration, and protection use the normal field workflow.

## registerFeedback() {#register-feedback}

`registerFeedback(BoundField $field, string $part, array $attributes = []): void`
registers a handwritten help or errors element after you emit it. It reserves
its configured ID and participates in rollback without registering submissions.
See [handwritten feedback](/field-parts#preserve-feedback-and-accessible-names).

## postButton() and postLink() {#post-actions}

`postLink()` returns `ActionBuilder`; `postButton()` returns `PostButtonBuilder`,
which also exposes native button `name()`, `value()`, and `disabled()` setters.
Both offer `method()`, `data()`, `confirm()`, `block()`, `form(FormConfig)`,
and common presentation setters.
`block()` takes a nonempty block name or null. See [POST actions](/post-actions)
for ordinary usage, or the [POST action reference](/post-action-reference) for
JavaScript and protection contracts.

## Lifecycle and failures

Open a session once, render inputs, close once. Repeated opening or closing,
rendering inputs before opening or after closing, and reusing rendered IDs fail
with `LogicException`. Renderable builders do not cache HTML; rendering one twice
attempts to render it twice. Do not use both a whole control and its input.

Failed control, batch, and blueprint renders roll back pending registrations.
A failed close leaves the session open, allowing missing feedback to be emitted
before retrying. Exceptions propagate; configuration and inspection never unlock
fields to bypass protection.
