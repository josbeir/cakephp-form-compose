<?php
declare(strict_types=1);

namespace FormCompose\Form;

use Cake\View\HtmlStringable;
use FormCompose\Binding\FieldPath;
use FormCompose\Form\Field\BoundField;
use FormCompose\Form\Field\ErrorTargetProviderInterface;
use FormCompose\Form\Field\FieldTypeInterface;
use FormCompose\Rendering\Attributes;
use InvalidArgumentException;

/**
 * Captures rendered validation feedback without retaining a bound field or security service.
 */
final readonly class FeedbackEntry
{
    /**
     * Capture a display label, ordered Cake messages, and an optional emitted control ID.
     *
     * @param list<string> $messages Existing validation messages, always escaped when rendered.
     */
    public function __construct(
        public string|HtmlStringable $label,
        public array $messages,
        public ?string $target = null,
    ) {
        if ($target === '') {
            throw new InvalidArgumentException('Error summary targets must be nonempty IDs or null.');
        }
    }

    /**
     * Describe eligible rendered fields without changing their inline feedback behavior.
     *
     * Pass null for handwritten registration, whose markup has no renderer-reported target.
     */
    public static function field(BoundField $field, ?FieldTypeInterface $type, Attributes $attributes): ?self
    {
        if (!$field->state->hasErrors()) {
            return null;
        }

        /** @var string|HtmlStringable|false $label */
        $label = $field->state->config->label ?? FieldPath::label($field->state->path);

        return new self(
            $label === false || $label === '' ? FieldPath::label($field->state->path) : $label,
            $field->state->messages,
            self::target($field, $type, $attributes),
        );
    }

    /**
     * Describe the actual focus target independently of validation-message presence.
     */
    public static function target(BoundField $field, ?FieldTypeInterface $type, Attributes $attributes): ?string
    {
        return match (true) {
            !$type instanceof FieldTypeInterface || $attributes->has('disabled') || $field->state->type === 'hidden'
                => null,
            $type instanceof ErrorTargetProviderInterface => $type->errorTarget($field, $attributes),
            default => $field->state->id,
        };
    }
}
