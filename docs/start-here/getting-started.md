---
title: Your first form
description: Install FormCompose and render an article edit form with labels, values, and validation feedback.
---

# Your first form

Build an article editing form using your existing CakePHP entity and controller.
You need PHP 8.5+ and CakePHP 6 development builds.

## Install the plugin

The current CakePHP dependency requires your application's Composer configuration
to allow development packages: `"minimum-stability": "dev"` with
`"prefer-stable": true`.

```sh
composer require josbeir/cakephp-form-compose:dev-main
bin/cake plugin load FormCompose
```

The Cake CLI registers the plugin in `config/plugins.php`.

## Load the helper

Configure FormCompose as your `Form` helper:

`src/View/AppView.php`

```php [src/View/AppView.php]
<?php
declare(strict_types=1);

namespace App\View;

use Cake\View\View;

class AppView extends View
{
    public function initialize(): void
    {
        parent::initialize();
        $this->addHelper('Form', ['className' => 'FormCompose.Form']);
    }
}
```

All examples use this alias. FormCompose has its own form API; if you are moving
an existing core FormHelper form, see [Migrate a core form](/migration).

::: details Keep the core FormHelper alongside FormCompose
Inside `AppView::initialize()`, load both helpers under different aliases instead:

```php
$this->addHelper('Form');
$this->addHelper('Compose', ['className' => 'FormCompose.Form']);
```

Use `$this->Compose` for FormCompose calls in that application.
:::

## Build your first form

Your controller passes an `$article` entity to this view. Keep your existing
record loading and submission handler.

`templates/Articles/edit.php`

```php [templates/Articles/edit.php]
<?php
$form = $this->Form->bind($article);
?>
<?= $form->open() ?>
<?= $form->control('title') ?>
<?= $form->control('body')->type('textarea') ?>
<?= $form->control('published')->type('checkbox') ?>
<?= $form->submit('Save article') ?>
<?= $form->close() ?>
```

- `bind($article)` prepares a form using the article's values and metadata.
- `open()` starts the form. Each `control()` includes its label, input, and errors.
- `submit()` adds the button; `close()` finishes the form and protection fields.

The form submits to the current URL. New entities use POST; existing entities use
PUT through CakePHP's method override. For a different destination or method,
configure a fluent builder before binding; see [form configuration](/form-reference#bind).

Keep CakePHP's CSRF middleware and FormProtectionComponent configured in your
application. Loading the helper does not enable them.

## Add labels, help, and attributes

Replace the title control with:

```php
echo $form->control('title')
    ->label('Article title')
    ->help('Choose a title readers can recognize.')
    ->attributes(['class' => 'article-title', 'autocomplete' => 'off']);
```

`attributes()` applies to the input. The [field guide](/usage) shows defaults,
wrapper attributes, and other common settings.

## Show validation feedback

When your normal CakePHP submission handler reports validation errors, render the
same view with that entity again. Controls show those errors next to their inputs,
and submitted values remain filled in. FormCompose renders the feedback; your
application continues to validate and save.

## Choose your next step

| Task | Guide |
| --- | --- |
| Configure fields and their values | [Fields and values](/usage) |
| Add a select, radio group, or checkbox | [Choice controls](/choices) |
| Build a search form or accept files | [Search forms](/search-forms) · [File uploads](/file-uploads) |
| Change a control's HTML | [Use your own templates](/templates) |
| Share markup across forms | [Create a theme](/themes) |
| Share a set of fields | [Form blueprints](/blueprints) |
