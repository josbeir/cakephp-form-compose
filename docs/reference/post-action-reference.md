---
title: POST action reference
description: Action configuration, JavaScript loading, confirmation events, protected payloads, and trigger templates.
---

# POST action reference

Start with [POST buttons and links](/post-actions) for ordinary actions and buttons
inside an edit form. This page covers configuration and integration contracts.

## Options and presentation

`postLink()` returns `ActionBuilder`. `postButton()` returns `PostButtonBuilder`,
which adds native button `name()`, `value()`, and `disabled()` setters.
Optional `config: $actionConfig` accepts an immutable `ActionConfig` directly.
`ActionConfig::builder()` returns a new `ActionConfig`; its fluent methods return
new configs.

| Setter | Behavior |
| --- | --- |
| `method(string)` | POST, PUT, PATCH, or DELETE; overrides the nested form method. |
| `data(array)` | Nested locked hidden payload. |
| `form(FormConfig)` | Typed configuration of the independent form. Its URL is controlled by the action URL argument. |
| `block(?string)` | Inline for null; a nonempty CakePHP view block name otherwise. |
| `confirm(?string)` | Confirmation text; null or empty string disables it. |
| `attributes(array)` | Presentation attributes on the visible button/link. |
| `name(?string)`, `value(mixed)`, `disabled(bool = true)` | Post buttons only: optional submitted identity and native disabled state. |
| `template()`, `partTemplate()`, `variant()`, `templateData()` | Shared trigger presentation. |

Omitting the URL for `postLink()` uses the current request target. Managed
attributes such as `href`, `formaction`, and `formmethod` cannot redirect the
protected action. Set button name, value, and disabled state with typed setters;
those keys are rejected in `attributes()`. An action configuration containing
button-only state is also rejected by `postLink()`. Supplied labels are escaped unless wrapped in `HtmlString`.

```php
<?= $this->Form
    ->postButton(label: 'Archive', url: ['action' => 'archive', $article->id])
    ->name('intent')
    ->value('archive')
    ->disabled($article->archived) ?>
```

Post buttons reuse `button/control.php` and its `button` attribute defaults. Links
use the `action` scope's `control` part, with `action.control` attribute defaults.
Both reuse the existing `form` and hidden-field templates. The script tag is
registered through CakePHP's HTML helper, independently of action themes and
renderers; use `autoActionScript` to control its loading.

Pass a `Theme` or custom renderer with the helper's named `theme` and `renderer` arguments:

```php
<?= $this->Form
    ->postButton(label: 'Delete', url: ['action' => 'delete', $article->id], theme: $theme)
    ->method('delete')
    ->template('forms/delete-button') ?>
```

Custom button elements preserve `$attributes`, including `type` and `form`.
Custom link elements preserve `href` and `data-form-compose-action`. Both receive
`$form`, `$label`, and `$templateData`. Behavior services can be specialized
through `FormHelper::createPostActionRenderer()`, whose protected `trigger()`
method uses the existing renderer and session.

## Load the enhancement {#load-the-enhancement}

The helper's `autoActionScript` option defaults to true. When the helper is loaded,
it registers the deferred external `FormCompose.actions` asset in CakePHP's
`script` view block, even if the initial page has no post buttons or links.
Include the block in your layout, usually in `<head>`:

```php
<?= $this->fetch('script') ?>
```

Register the helper in `AppView::initialize()` so CakePHP loads it when rendering
the host page, including pages that will receive actions later:

```php
$this->addHelper('Form', [
    'className' => 'FormCompose.Form',
    'autoActionScript' => true,
]);
```

CakePHP's shared HTML helper deduplicates the asset within the view and includes
the request's `cspScriptNonce` when available. There are no generated inline event
handlers. All action calls return markup without script tags; adding more actions
does not add more scripts. The script also guards against duplicate listener
installation if it is included again.

The asset follows CakePHP's plugin asset routing and deployment conventions;
publish the plugin's `webroot/` assets along with your other plugin assets in
production. Your CSP must permit the external script through its source policy
or nonce. No JavaScript framework is required.

### Manual loading or application-owned behavior

Set `autoActionScript` to false when registering the helper to omit automatic
registration. Set this before the helper is initialized; changing it later does
not remove a script already registered in a view block.

```php
// AppView::initialize()
$this->addHelper('Form', [
    'className' => 'FormCompose.Form',
    'autoActionScript' => false,
]);
```

Load the enhancement explicitly in the layout instead:

```php
<?= $this->Html->script('FormCompose.actions', ['defer' => true]) ?>
```

Alternatively, include `webroot/js/actions.js` in your application bundle, or own
the behavior entirely. Disabling automatic loading changes asset registration;
action markup and confirmation metadata are still rendered. Native buttons work
without the asset, while post links and confirmation need an implementation.

### Dynamically inserted actions

Load the script on the host page before users can interact with inserted actions.
Its delegated listeners handle forms, buttons, and links added later through
`innerHTML` or other DOM APIs without an initialization call. Do not depend on a
script tag in a fetched fragment: `innerHTML` does not execute inserted scripts,
and a fragment's view blocks are separate from the host page's layout.

Give each independently rendered fragment's forms explicit, page-unique IDs.
The helper's generated `form-compose-N` counter belongs to one helper instance;
it cannot coordinate IDs across separate HTTP responses:

```php
<?php
use FormCompose\Configuration\FormConfig;

?>
<?= $this->Form
    ->postLink(label: 'Delete', url: ['action' => 'delete', $article->id])
    ->method('delete')
    ->form(
        FormConfig::builder()->id('delete-article-' . $article->id),
    ) ?>
```

If the same record appears more than once, include a unique occurrence identifier
in that form ID too. When using `block`, insert the buffered forms along with
their triggers, outside any existing form.

## Custom confirmation dialogs {#custom-confirmation-dialogs}

The script dispatches a bubbling, cancelable `formcompose:confirm` event on the
form before showing a native dialog. Listen on an individual form or on
`document` for a page-wide integration, including dynamically inserted actions.
This code belongs in your application JavaScript:

```js
document.addEventListener('formcompose:confirm', event => {
  const { message, respondWith } = event.detail;
  respondWith(applicationDialog.confirm(message)); // Promise<boolean>
});
```

`applicationDialog.confirm()` represents your own dialog implementation. It
should display `message` as text and resolve to true for approval or false for
cancellation. The event does not insert the message into HTML.

| Detail | Contract |
| --- | --- |
| `message` | The form's confirmation message as a string. |
| `form` | The `HTMLFormElement` being submitted; also the event target. |
| `submitter` | The original submit button/input, or null for a post link or submission without a button. |
| `respondWith(response)` | Accepts a boolean or `Promise<boolean>`. Call synchronously during dispatch, once per event. |

::: tip Register an asynchronous response immediately
Call `respondWith()` during event dispatch, before awaiting other work. Pass the
promise itself; returning a promise from the listener does not claim the event.
:::

A second call, or a call after dispatch finishes, throws `InvalidStateError`.
Only a result strictly equal to true approves. False, a rejected promise, or a
non-boolean result cancels without a native dialog. If no listener responds,
the script falls back to `window.confirm(message)`.

Calling `event.preventDefault()` cancels the action regardless of any supplied
response. Use it to veto an action without opening a dialog:

```js
document.addEventListener('formcompose:confirm', event => {
  if (applicationIsBusy()) event.preventDefault();
});
```

While a response is pending, repeated submissions of that form are ignored;
other forms remain independent. Paused or canceled submits are stopped before
form-level submit handlers run, so an AJAX handler receives only an approved
submission. Approval calls native `requestSubmit()` with the original submitter,
retaining its name/value and rerunning browser validation without another
confirmation. Your ordinary `submit` handler can still cancel or handle that
approved submission. If the form is removed, or the submitter is removed,
disabled, reassociated, or changed into a non-submit control while waiting, the
action is canceled.

## Pass protected data

```php
<?= $this->Form
    ->postButton(label: 'Archive', url: ['action' => 'archive', $article->id])
    ->data(['version' => $article->version, 'audit' => ['source' => 'index']]) ?>
```

`data` accepts nested arrays with scalar, null, or stringable leaves. Each leaf
renders as a locked hidden input; false and zero are preserved as `"0"`. These
explicit values take precedence over the current request's data. Empty nested
arrays and invalid field paths are rejected. `_method`, `_csrfToken`, and `_Token`
are reserved for the form session.

Locked paths must end in a named field: `records.2.id` works, while a scalar list
such as `ids => [1, 2]` is rejected. CakePHP's form protector normalizes trailing
numeric indexes and cannot lock their individual values. The helper rejects this
shape rather than producing a form that fails protection or unlocking its data.

A named button can use `name` and `value`, but its name must be independent of the
locked data paths and infrastructure fields. Overlapping paths are rejected so
optional button registration cannot unlock the hidden payload.
