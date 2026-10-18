<?php
declare(strict_types=1);

namespace FormCompose\Form;

use Closure;
use FormCompose\Binding\FieldResolver;
use FormCompose\Binding\FormBinding;
use FormCompose\Blueprint\BlueprintFactory;
use FormCompose\Blueprint\BlueprintRenderer;
use FormCompose\Blueprint\FormContext;
use FormCompose\Blueprint\FormDefinition;
use FormCompose\Blueprint\RepeatedDefinition;
use FormCompose\Builder\ButtonBuilder;
use FormCompose\Builder\ControlBatch;
use FormCompose\Builder\ControlBuilder;
use FormCompose\Builder\FieldBuilder;
use FormCompose\Builder\PartBuilder;
use FormCompose\Builder\RepeatedEmbedBuilder;
use FormCompose\Builder\SummaryBuilder;
use FormCompose\Configuration\ButtonConfig;
use FormCompose\Configuration\EmbedConfig;
use FormCompose\Configuration\FieldConfig;
use FormCompose\Configuration\FormConfig;
use FormCompose\Configuration\PresentationConfig;
use FormCompose\Configuration\SummaryConfig;
use FormCompose\Form\Field\BoundField;
use FormCompose\Form\Field\FieldSpec;
use FormCompose\Rendering\CompleteFormRenderer;
use FormCompose\Rendering\ErrorSummaryRenderer;
use FormCompose\Rendering\Html;
use FormCompose\Rendering\RenderContext;
use FormCompose\Rendering\RenderNode;
use InvalidArgumentException;
use Throwable;

/**
 * Coordinates the public API for building and rendering one bound form.
 *
 * Created by FormHelper::bind() or FormBuilder::bind(). Uses the binding to resolve
 * fields, the renderer to produce themed markup, and the lifecycle to track submitted inputs and tokens.
 * A session can open once; render its inputs before closing it. Override
 * createFieldResolver() or createLifecycle() to supply specialized collaborators.
 *
 * ```php
 * $form = $this->Form->bind($article);
 * echo $form->open(), $form->control('title');
 * echo $form->submit('Save'), $form->close();
 * ```
 *
 * Keep complete-form and batch shortcuts alongside the explicit session operations.
 */
class FormSession
{
    protected readonly FormLifecycle $lifecycle;

    protected readonly BlueprintRenderer $blueprintRenderer;

    protected readonly ErrorSummaryRenderer $summaries;

    /**
     * Create a session with binding, rendering, and blueprint construction dependencies.
     */
    public function __construct(
        public readonly FormBinding $binding,
        protected readonly RenderContext $rendering,
        protected readonly BlueprintFactory $blueprintFactory = new BlueprintFactory(),
        ?BlueprintRenderer $blueprintRenderer = null,
        ?ErrorSummaryRenderer $summaries = null,
    ) {
        $this->lifecycle = $this->createLifecycle();
        $this->blueprintRenderer = $blueprintRenderer ?? new BlueprintRenderer($rendering);
        $this->summaries = $summaries ?? new ErrorSummaryRenderer($rendering);
    }

    /**
     * Open, buffer the selected blueprints, arrange their summary/body/actions, and close this fresh session.
     *
     * A null blueprint uses binding defaults; without defaults the body is empty.
     * A null label translates Submit at rendering time; false omits the button.
     * The form/layout element receives the buffered body, summary, submit label,
     * and submitConfig while the session remains open for custom action buttons.
     * Select it with template() on the form builder before binding; blueprint layouts
     * continue to arrange the fields inside the body.
     * Returns all markup without echoing. Exceptions retain the existing lifecycle
     * state at the failed step; retry a complete form using a new session.
     *
     */
    public function render(): string
    {
        return $this->createCompleteRenderer()->render($this, fn(): FormState => $this->lifecycle->assertOpen());
    }

    /**
     * Prepare and render the opening through the same theme system as fields.
     *
     */
    public function open(): string
    {
        $state = $this->lifecycle->prepare($this->rendering->theme, new FormConfig());
        $html = $this->rendering->renderer->render(
            'open',
            new RenderNode(
                'form',
                $state->method,
                [
                    'form' => $this,
                    'state' => $state,
                    'attributes' => $state->attributes,
                    'hidden' => $state->hidden,
                ],
                $state->presentation,
            ),
        );
        $this->lifecycle->opened($state);

        return $html;
    }

    /**
     * Finalize security data and render closing markup before committing the closed state.
     */
    public function close(): string
    {
        $state = $this->lifecycle->assertOpen();
        try {
            $html = $this->rendering->renderer->render(
                'close',
                new RenderNode(
                    'form',
                    $state->method,
                    [
                        'form' => $this,
                        'state' => $state,
                        'hidden' => $this->lifecycle->tokens(),
                        'attributes' => $this->rendering->theme->attributes('form.close', $state->method),
                    ],
                    $state->presentation,
                ),
            );
        } catch (Throwable $throwable) {
            $this->lifecycle->cancelClose();
            throw $throwable;
        }

        $this->lifecycle->close();

        return $html;
    }

    /**
     * Allow an explicitly named field or collection to change outside the rendered input set.
     *
     * Call after open() and before close(), using CakePHP dot paths such as comments.
     * The path may be unlocked before or after rendering its inputs.
     * Unlocking a collection permits added, removed, or changed descendants; validate
     * and authorize their submitted values in the application as usual.
     */
    public function unlock(string $path): void
    {
        $this->lifecycle->unlock($path);
    }

    /**
     * Resolve a field snapshot without emitting markup or registering submissions.
     *
     */
    public function field(string $field, ?FieldConfig $config = null): FieldBuilder
    {
        return $this->createFieldBuilder($field, $config ?? new FieldConfig());
    }

    /** Create a configurable field through an application extension point. */
    protected function createFieldBuilder(string $field, FieldConfig $config): FieldBuilder
    {
        return new FieldBuilder(fn(FieldConfig $settings): BoundField => $this->createFieldResolver()->resolve(
            $field,
            $settings,
            $this->lifecycle,
        ), $config);
    }

    /** Configure a complete control, deferring resolution and registration until rendering. */
    public function control(string $field, ?FieldConfig $config = null): ControlBuilder
    {
        return $this->createControlBuilder($field, $config ?? new FieldConfig());
    }

    /** Create a renderable control while retaining field() customization. */
    protected function createControlBuilder(string $field, FieldConfig $config): ControlBuilder
    {
        return new ControlBuilder(
            fn(FieldConfig $settings): BoundField => $this->field($field, $settings)->resolve(),
            $config,
            $this,
        );
    }

    /**
     * Render ordered controls without a wrapper, committing inputs as one batch.
     *
     * Entries are paths, FieldSpec objects, or controls from this session.
     * Failed batches roll back their input registrations, like blueprint layouts.
     * Each control uses this session's existing defaults and extension points.
     *
     * @param iterable<array-key, mixed> $fields Batch control declarations.
     * @throws \InvalidArgumentException For invalid batch declarations.
     */
    public function controls(iterable $fields): ControlBatch
    {
        return $this->controlBatch($fields, fn(FieldSpec $field): string => $this->control(
            $field->path,
            $field->config,
        )->render());
    }

    /**
     * Share registration transactions with scoped blueprint views.
     *
     * @param iterable<array-key, mixed> $fields Paths, specs, or controls from this session.
     * @param \Closure(FieldSpec): string $control Scoped control renderer.
     * @internal Used by blueprint layout views.
     */
    public function controlBatch(iterable $fields, Closure $control): ControlBatch
    {
        return new ControlBatch($fields, $control, $this, $this->lifecycle->inputs->render(...));
    }

    /**
     * Render a reusable field collection here, through the normal control templates.
     *
     * With no argument, render all blueprints selected at bind(), in order. An explicitly
     * supplied blueprint alias or class stays local to this call and
     * does not replace defaults. Blueprints use this session's factory and context.
     * The form must be open; a session without a selected blueprint renders an empty string.
     * at mounts relative fields at a root context path. Blueprint layout options
     * stay local.
     *
     */
    public function blueprint(?string $blueprint = null, ?string $at = null): PartBuilder
    {
        return new PartBuilder(
            fn(PresentationConfig $presentation): string => $this->renderBlueprint($blueprint, $at, $presentation),
        );
    }

    /** Compile an explicit occurrence only at its rendering boundary. */
    protected function renderBlueprint(?string $blueprint, ?string $at, PresentationConfig $presentation): string
    {
        $this->lifecycle->assertOpen();
        $context = FormContext::fromBinding($this->binding);
        if ($at !== null) {
            $context = $context->at($at);
        }

        $definitions = $blueprint === null
            ? $this->binding->definitions
            : [$this->blueprintFactory->compile($blueprint, $context)];

        return $this->lifecycle->inputs->render(
            fn(): string => $this->blueprintRenderer->renderAll(
                $this,
                $definitions,
                $at,
                $presentation,
            ),
        );
    }

    /**
     * Render compiled layout state without resolving its blueprint again.
     *
     * @internal Used by scoped blueprint views.
     */
    public function renderDefinition(
        FormDefinition $definition,
        ?string $at = null,
        PresentationConfig $presentation = new PresentationConfig(),
    ): string {
        $this->lifecycle->assertOpen();

        return $this->lifecycle->inputs->render(
            fn(): string => $this->blueprintRenderer->render($this, $definition, $at ?? '', $presentation),
        );
    }

    /**
     * Render a repeated blueprint at an association path in this open session.
     *
     */
    public function embedMany(
        string $path,
        string $blueprint,
        ?EmbedConfig $config = null,
    ): RepeatedEmbedBuilder {
        return new RepeatedEmbedBuilder(
            /** @param list<int|string>|null $keys Selected row keys. */
            function (EmbedConfig $settings, ?array $keys) use ($path, $blueprint): string {
                $this->lifecycle->assertOpen();
                $repeat = new RepeatedDefinition($path, $blueprint, $keys, $settings);
                $compiled = $this->blueprintFactory->repeated($repeat, FormContext::fromBinding($this->binding));

                return $this->renderRepeated($path, $compiled);
            },
            $config ?? new EmbedConfig(),
        );
    }

    /**
     * Render compiled rows at their existing absolute mount.
     *
     * @internal Used by scoped blueprint views without re-running hooks.
     */
    public function renderRepeated(
        string $path,
        RepeatedDefinition $compiled,
        ?EmbedConfig $config = null,
    ): string {
        $this->lifecycle->assertOpen();
        $compiled = new RepeatedDefinition(
            $path,
            $compiled->blueprint,
            $compiled->keys,
            $compiled->config,
            $compiled->rows,
        );

        return $this->lifecycle->inputs->render(fn(): string => $this->blueprintRenderer->renderMany(
            $this,
            $compiled,
            $config ?? new EmbedConfig(),
            $this->lifecycle->inputs,
        ));
    }

    /**
     * Summarize successfully rendered controls and repeated sections in this open form.
     *
     * Render or buffer the body first; inspecting fields never contributes entries.
     */
    public function errorSummary(?SummaryConfig $config = null): SummaryBuilder
    {
        return new SummaryBuilder(function (SummaryConfig $settings): string {
            $this->lifecycle->assertOpen();

            return $this->summaries->render($this, $this->lifecycle->inputs, $settings);
        }, $config ?? new SummaryConfig());
    }

    /**
     * Register a handwritten feedback element after emitting its prepared attributes.
     *
     * Use field->attributes($part) on the element, then registerFeedback($field, $part).
     * This reserves its configured ID without rendering or registering submissions.
     * Do not also render that element with the field's help() or errors() method.
     *
     * @param array<string, mixed> $attributes Local feedback attributes used in the markup.
     * @throws \InvalidArgumentException For parts other than help and errors.
     */
    public function registerFeedback(BoundField $field, string $part, array $attributes = []): void
    {
        if (!in_array($part, ['help', 'errors'], true)) {
            throw new InvalidArgumentException('Only handwritten help and errors parts can be registered.');
        }

        $this->lifecycle->assertOpen();
        $this->lifecycle->inputs->render(static fn(): string => '', $field->attributes($part, $attributes));
    }

    /**
     * Render a themed button and register its optional submission only after rendering succeeds.
     *
     */
    public function submit(string|Html $label, ?ButtonConfig $config = null): ButtonBuilder
    {
        return new ButtonBuilder(fn(ButtonConfig $settings): string => $this->renderButton(
            $label,
            $settings,
        ), $config ?? new ButtonConfig());
    }

    /** Commit a named button only after its template has succeeded. */
    protected function renderButton(string|Html $label, ButtonConfig $config): string
    {
        $this->lifecycle->assertOpen();
        $attributes = $this->rendering
            ->theme
            ->attributes('button', $config->type)
            ->merge($config->presentation->attributes)
            ->replace([
                'type' => $config->type,
                'name' => $config->name,
                'value' => $config->value,
                'disabled' => $config->disabled,
            ]);
        $html = $this->rendering->renderer->render(
            'control',
            new RenderNode(
                'button',
                $config->type,
                [
                    'form' => $this,
                    'label' => $label,
                    'attributes' => $attributes,
                ],
                $config->presentation,
            ),
        );
        $this->lifecycle->button($attributes);

        return $html;
    }

    /**
     * Render prepared infrastructure fields through themed hidden-input elements.
     *
     * @param list<\FormCompose\Rendering\Attributes> $fields Method, CSRF, or final protection fields.
     */
    public function hiddenFields(array $fields, PresentationConfig $presentation = new PresentationConfig()): string
    {
        $html = '';
        foreach ($fields as $field) {
            $attributes = $this->rendering->theme->attributes('form.hidden', 'hidden')->merge($field->values);
            $html .= $this->rendering->renderer->render(
                'hidden',
                new RenderNode(
                    'form',
                    'hidden',
                    [
                        'attributes' => $attributes,
                    ],
                    $presentation,
                ),
            );
        }

        return $html;
    }

    /**
     * Create lifecycle coordination for this session before opening the form.
     */
    protected function createLifecycle(): FormLifecycle
    {
        return new FormLifecycle($this->binding);
    }

    /**
     * Create the resolver used by field() and control().
     */
    protected function createFieldResolver(): FieldResolver
    {
        return new FieldResolver($this->binding, $this->rendering);
    }

    /** Supply complete-form orchestration through an application extension point. */
    protected function createCompleteRenderer(): CompleteFormRenderer
    {
        return new CompleteFormRenderer($this->rendering);
    }
}
