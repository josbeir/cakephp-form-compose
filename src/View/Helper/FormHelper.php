<?php
declare(strict_types=1);

namespace FormCompose\View\Helper;

use Cake\View\Form\ContextFactory;
use Cake\View\Form\ContextInterface;
use Cake\View\Helper;
use Cake\View\Helper\HtmlHelper;
use Cake\View\HtmlStringable;
use FormCompose\Binding\FormBinding;
use FormCompose\Binding\TypeMap;
use FormCompose\Blueprint\BlueprintFactory;
use FormCompose\Blueprint\FormContext;
use FormCompose\Blueprint\FormDefinition;
use FormCompose\Builder\ActionBuilder;
use FormCompose\Builder\FormBuilder;
use FormCompose\Builder\PostButtonBuilder;
use FormCompose\Configuration\ActionConfig;
use FormCompose\Configuration\FormConfig;
use FormCompose\Form\FormIds;
use FormCompose\Form\FormSession;
use FormCompose\Rendering\PostActionRenderer;
use FormCompose\Rendering\RenderContext;
use FormCompose\Rendering\RendererInterface;
use FormCompose\Rendering\Theme;
use InvalidArgumentException;

/**
 * Creates bound form sessions, renders complete forms, and authors standalone protected actions.
 *
 * bind() selects a CakePHP form context for the supplied data and collects the
 * form's theme, renderer, and custom field types. Each returned FormSession has
 * its own field resolution, rendering, and submission lifecycle. Override
 * createSession() to select an application-specific FormSession subclass.
 *
 * ```php
 * $form = $this->Form->bind($article);
 * echo $form->open(), $form->control('title'), $form->submit('Save'), $form->close();
 * ```
 *
 * @extends \Cake\View\Helper<\Cake\View\View>
 */
class FormHelper extends Helper
{
    /**
     * Default presentation, type inference, and page-level action enhancement.
     *
     * @var array<string, mixed>
     */
    protected array $defaultConfig = [
        'theme' => 'Base',
        'themeDebug' => false,
        'passwordFields' => FormBinding::DEFAULT_PASSWORD_FIELDS,
        'typeMap' => [],
        'autoActionScript' => true,
    ];

    protected ?FormIds $formIds = null;

    protected ?PostActionRenderer $postActions = null;

    /**
     * Register delegated action behavior in the layout's script block on helper initialization.
     *
     * Configure autoActionScript before loading the helper. Disable it when the
     * application bundles or replaces actions.js. Cake's shared HTML helper
     * handles asset deduplication, routing, and the request's CSP script nonce.
     *
     * @param array<string, mixed> $config Helper configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        if ($this->getConfig('autoActionScript') === true) {
            $html = $this->getView()->loadHelper('Html');
            assert($html instanceof HtmlHelper, 'The Html helper must extend CakePHP HtmlHelper.');
            $html->script('FormCompose.actions', ['block' => 'script', 'defer' => true]);
        }
    }

    /** Configure a complete form or prepare a binding; override to supply a custom FormBuilder. */
    public function form(mixed $data = null, ?FormConfig $config = null): FormBuilder
    {
        return new FormBuilder(
            fn(FormConfig $settings): FormSession => $this->bind($data, $settings),
            $config ?? new FormConfig(),
        );
    }

    /**
     * Render a standalone protected action using a native submit button.
     *
     */
    public function postButton(
        string|HtmlStringable $label,
        array|string $url,
        ?ActionConfig $config = null,
        Theme|string|null $theme = null,
        ?RendererInterface $renderer = null,
    ): PostButtonBuilder {
        return new PostButtonBuilder(fn(ActionConfig $settings): string => ($this->postActions ??=
            $this->createPostActionRenderer())->render(
            $label,
            $url,
            $settings,
            'button',
            RenderContext::forView(
                $this->getView(),
                $this->resolveTheme($theme ?? $settings->form->theme),
                $renderer ?? $settings->form->renderer,
                themeDebug: $this->getConfig('themeDebug') === true,
            ),
        ), $config ?? new ActionConfig());
    }

    /**
     * Render a protected action link using the page's delegated JavaScript enhancement.
     *
     */
    public function postLink(
        string|HtmlStringable $label,
        array|string|null $url = null,
        ?ActionConfig $config = null,
        Theme|string|null $theme = null,
        ?RendererInterface $renderer = null,
    ): ActionBuilder {
        return new ActionBuilder(fn(ActionConfig $settings): string => ($this->postActions ??=
            $this->createPostActionRenderer())->render(
            $label,
            $url,
            $settings,
            'link',
            RenderContext::forView(
                $this->getView(),
                $this->resolveTheme($theme ?? $settings->form->theme),
                $renderer ?? $settings->form->renderer,
                themeDebug: $this->getConfig('themeDebug') === true,
            ),
        ), $config ?? new ActionConfig());
    }

    /**
     * Bind data for a manually arranged form, without rendering markup or registering inputs.
     *
     * Accepts the same CakePHP data and context providers as form(). Optional immutable
     * configuration supplies form-wide settings. Each call creates an independent session;
     * override createSession() to supply an application-specific session implementation.
     */
    public function bind(mixed $data = null, FormConfig $config = new FormConfig()): FormSession
    {
        $view = $this->getView();
        /** @var list<string> $passwordFields */
        $passwordFields = $this->getConfig('passwordFields', FormBinding::DEFAULT_PASSWORD_FIELDS);
        $config = FormConfig::builder()->passwordFields($passwordFields)->merge($config);
        $config = $config->typeMap(array_replace(
            TypeMap::normalize($this->getConfig('typeMap', [])),
            $config->typeMap,
        ));
        $types = $config->types;
        /** @var ContextFactory|null $contextFactory */
        $contextFactory = $this->getConfig('contextFactory');
        $contextFactory = $contextFactory instanceof ContextFactory
            ? $contextFactory
            : ContextFactory::createWithDefaults();
        $contextOptions = $config->context;
        $context = $data instanceof ContextInterface
            ? $data
            : $contextFactory
                ->get($view->getRequest(), ['entity' => $data] + $contextOptions);
        $theme = $this->resolveTheme($config->theme);

        $id = ($this->formIds ??= $this->createFormIds())->allocate(
            $config->id,
        );
        $factory = $this->createBlueprintFactory();
        $valueSources = $config->valueSources;
        $formContext = new FormContext($context, $view->getRequest(), valueSources: $valueSources);
        $compiled = array_map(
            static fn(string $blueprint): FormDefinition => $factory->compile($blueprint, $formContext),
            $config->blueprints,
        );

        $types = $factory->lazyTypes($types);

        return $this->createSession(
            new FormBinding($context, $view->getRequest(), $id, $config, $compiled),
            RenderContext::forView(
                $view,
                $theme,
                $config->renderer,
                $types,
                themeDebug: $this->getConfig('themeDebug') === true,
            ),
            $factory,
        );
    }

    /**
     * Construct the internal identity service with this helper's view dependency.
     */
    protected function createFormIds(): FormIds
    {
        return new FormIds($this->getView());
    }

    /**
     * Use an explicit helper factory, the controller's factory, or a standalone factory.
     *
     * The plugin supplies application services through view configuration during
     * controller rendering. Manually constructed views can set blueprintFactory on
     * the helper. Subclasses may override this factory without changing form().
     */
    protected function createBlueprintFactory(): BlueprintFactory
    {
        /** @var mixed $factory */
        $factory = $this->getConfig('blueprintFactory', $this->getView()->getConfig('formCompose.blueprintFactory'));
        if ($factory !== null && !$factory instanceof BlueprintFactory) {
            throw new InvalidArgumentException('blueprintFactory must be a BlueprintFactory instance.');
        }

        return $factory ?? new BlueprintFactory();
    }

    /**
     * Create the session used by bind(); override to supply an application session subclass.
     * Forward the factory so render-time definitions use the same discovery and DI services.
     */
    protected function createSession(
        FormBinding $binding,
        RenderContext $rendering,
        BlueprintFactory $blueprints,
    ): FormSession {
        return new FormSession($binding, $rendering, $blueprints);
    }

    /**
     * Select a per-call override, helper default, or the bundled base theme.
     *
     * Null inherits helper configuration; Base explicitly selects the base theme.
     * Each binding retains the selected immutable theme independently of later config changes.
     */
    protected function resolveTheme(Theme|string|null $theme): Theme
    {
        /** @var mixed $selected */
        $selected = $theme ?? $this->getConfig('theme');

        /** @var mixed $selected */
        return match ($selected) {
            null, 'Base' => Theme::base(),
            default => $selected instanceof Theme
                ? $selected
                : throw new InvalidArgumentException('Use Base or a Theme instance.'),
        };
    }

    /**
     * Replace action placement or trigger rendering through the helper's normal extension point.
     */
    protected function createPostActionRenderer(): PostActionRenderer
    {
        return new PostActionRenderer(
            $this->getView(),
            fn(FormConfig $config, RenderContext $rendering): FormSession => $this->bind(
                null,
                $config->theme($rendering->theme)->renderer($rendering->renderer),
            ),
        );
    }
}
