<?php
declare(strict_types=1);

namespace FormCompose\Blueprint;

use Cake\View\HtmlStringable;
use FormCompose\Binding\FieldPath;
use FormCompose\Binding\RepeatedErrors;
use FormCompose\Form\FormSession;
use FormCompose\Form\InputRegistry;
use FormCompose\Rendering\RenderContext;
use FormCompose\Rendering\RenderNode;

/**
 * Exposes scoped rows and collection-owned feedback to a repeated-embed template.
 *
 * Templates iterate rows and call each BlueprintView::render() or its individual
 * field methods. The existing session owns submissions and render transactions.
 */
class RepeatedView
{
    /** @var list<string> Direct validation errors, excluding nested row messages. */
    public readonly array $messages;

    /**
     * Capture compiled rows and presentation without rendering or registering inputs.
     *
     * @param array<int|string, \FormCompose\Blueprint\BlueprintView> $rows Scoped row views, preserving original keys.
     */
    public function __construct(
        public readonly RepeatedDefinition $definition,
        public readonly array $rows,
        protected readonly FormSession $session,
        protected readonly RenderContext $rendering,
        protected readonly InputRegistry $inputs,
    ) {
        $binding = $session->binding;
        $this->messages = RepeatedErrors::messages(
            FormContext::fromBinding($binding),
            $definition->path,
        );
    }

    /**
     * Read the same keyed views supplied as $rows to the PHP element.
     *
     * @return array<int|string, \FormCompose\Blueprint\BlueprintView>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Check collection-level feedback without rendering or registration.
     */
    public function hasErrors(): bool
    {
        return $this->definition->config->errors && $this->messages !== [];
    }

    /**
     * Render escaped direct errors through the repeated-embed template scope.
     */
    public function errors(): string
    {
        if (!$this->hasErrors()) {
            return '';
        }

        $parts = $this->definition->config->presentation->partAttributes;
        $attributes = $this->rendering
            ->theme
            ->attributes('embed-many.errors', 'default')
            ->merge([
                'id' => $this->session->binding->id . '-' . str_replace('.', '-', $this->definition->path) . '-errors',
            ])
            ->merge($parts['errors'] ?? []);

        return $this->inputs->render(fn(): string => $this->rendering->renderer->render('errors', new RenderNode(
            'embed-many',
            'default',
            ['embed' => $this, 'messages' => $this->messages, 'attributes' => $attributes],
            $this->definition->config->presentation,
        )), $attributes);
    }

    /**
     * Read an explicit or conventional collection label for feedback.
     */
    public function label(): string|HtmlStringable
    {
        /** @var mixed $label */
        $label = $this->definition->config->label ?? FieldPath::label($this->definition->path);

        return $label instanceof HtmlStringable ? $label : (string)$label;
    }
}
