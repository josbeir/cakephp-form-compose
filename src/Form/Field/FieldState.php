<?php
declare(strict_types=1);

namespace FormCompose\Form\Field;

use Cake\View\HtmlString;
use Cake\View\HtmlStringable;
use FormCompose\Binding\FieldPath;
use FormCompose\Configuration\FieldConfig;

/**
 * Contains the resolved identity, value, constraints, and feedback for one field.
 *
 * Created by FieldResolver and exposed through BoundField::state. Templates and
 * custom field types read this immutable snapshot to build markup. path identifies
 * the context value (for example comments.0.body); name is the corresponding HTML
 * input name (comments[0][body]). Reading state does not register an input.
 */
final readonly class FieldState
{
    /**
     * Capture field identity, value, configuration, constraints, and validation feedback.
     *
     * @param array{name: string, id: string, messages: list<string>, requiredMessage: ?string, composite: bool, constraints: array<string, mixed>, config: \FormCompose\Configuration\FieldConfig} $data Presentation metadata.
     */
    public function __construct(public string $path, public string $type, public mixed $value, array $data)
    {
        $this->name = $data['name'];
        $this->id = $data['id'];
        $this->messages = $data['messages'];
        $this->requiredMessage = $data['requiredMessage'];
        $this->composite = $data['composite'];
        $this->constraints = $data['constraints'];
        $this->config = $data['config'];
    }

    public string $name;

    public string $id;

    public bool $composite;

    /** @var list<string> */
    public array $messages;

    /** Context required-validation text; check effective required state before enhancing validity. */
    public ?string $requiredMessage;

    /** @var array<string, mixed> */
    public array $constraints;

    /** Immutable effective field configuration. */
    public FieldConfig $config;

    /**
     * Identify controls that require a group legend.
     */
    public function grouped(): bool
    {
        return $this->composite || in_array($this->type, ['radio', 'multicheckbox'], true);
    }

    /**
     * Determine whether help markup is part of this field's presentation.
     */
    public function hasHelp(): bool
    {
        return isset($this->config->help) && $this->config->help !== false && $this->config->help !== '';
    }

    /**
     * Determine whether validation messages should be rendered and referenced.
     */
    public function hasErrors(): bool
    {
        return $this->config->errors !== false && $this->messages !== [];
    }

    /**
     * Resolve and escape label content, unless explicitly marked as trusted HTML.
     */
    public function labelText(): string
    {
        /** @var string|HtmlStringable|false $label Resolved by FieldText before state construction. */
        $label = $this->config->label ?? FieldPath::label($this->path);

        return HtmlString::render($label instanceof HtmlStringable ? $label : (string)$label);
    }

    /**
     * Resolve escaped or explicitly trusted help content.
     */
    public function helpText(): string
    {
        /** @var string|HtmlStringable|false $help Resolved by FieldText before state construction. */
        $help = $this->config->help ?? '';

        return HtmlString::render($help instanceof HtmlStringable ? $help : (string)$help);
    }
}
