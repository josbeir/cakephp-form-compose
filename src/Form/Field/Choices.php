<?php
declare(strict_types=1);

namespace FormCompose\Form\Field;

use BackedEnum;
use Cake\Database\Type\EnumLabelInterface;
use Cake\Utility\Inflector;
use Cake\View\HtmlStringable;
use FormCompose\Binding\EnumChoices;
use FormCompose\Configuration\ChoiceConfig;
use FormCompose\Configuration\ChoiceGroupConfig;
use FormCompose\Configuration\Unspecified;
use FormCompose\Rendering\Attributes;
use FormCompose\Rendering\Theme;
use InvalidArgumentException;
use UnitEnum;

/**
 * Turns native choice options and backed enums into a tree of Choice records.
 *
 * Used by NativeType for selects, radios, and checkbox groups. Supports simple
 * value/label pairs, backed enum classes or cases, nested groups, and typed choice/group
 * configurations. Assigns unique choice IDs and prepares label
 * and wrapper defaults from the theme before passing the records to templates. Override
 * createChoiceAttributes() to supply custom per-option attribute rules.
 */
class Choices
{
    /** @var array<string, true> */
    protected array $ids = [];

    /**
     * Use the selected theme's defaults for options, labels, and choice groups.
     */
    public function __construct(protected readonly Theme $theme)
    {
    }

    /**
     * Preserve nested groups, extended options, and per-choice metadata.
     *
     * @param iterable<array-key, mixed>|string $options Native choices or a backed enum class name.
     * @return list<\FormCompose\Form\Field\Choice>
     */
    public function resolve(FieldState $state, Attributes $attributes, iterable|string $options): array
    {
        $options = is_string($options) ? EnumChoices::cases($options) : $options;

        $choices = [];
        /** @var mixed $label */
        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $label = new ChoiceGroupConfig((string)$value, $label);
            }

            if ($label instanceof ChoiceGroupConfig) {
                $choices[] = new Choice(
                    'group',
                    $label->label,
                    $this->theme->attributes('choice-group', $state->type)->merge($label->attributes),
                    $this->resolve($state, $attributes, $label->choices),
                );
                continue;
            }

            $choices[] = $this->item($state, $attributes, $this->option($value, $label));
        }

        return $choices;
    }

    /** Prepare one choice's stable identity, native attributes, and label presentation. */
    protected function item(FieldState $state, Attributes $attributes, ChoiceConfig $option): Choice
    {
        $id = (string)(
            $option->id
            ?? $option->attributes['id']
            ?? $state->id
            . '-'
            . rawurlencode(NativeValue::scalar($option->value))
        );
        $base = $id;
        $suffix = 0;
        while (isset($this->ids[$id])) {
            $id = $base . '-' . ++$suffix;
        }

        $this->ids[$id] = true;
        $option = $option->id($id);
        /** @var mixed $text */
        $text = $option->label === Unspecified::Value ? '' : $option->label;

        return new Choice(
            'item',
            $text instanceof HtmlStringable ? $text : (string)$text,
            $this->createChoiceAttributes()->resolve($state, $attributes, $option),
            [],
            [
                'label' => $this->theme->attributes('choice-label', $state->type)->replace(['for' => $id]),
                'container' => $this->theme->attributes('choice-container', $state->type),
            ],
        );
    }

    /**
     * Expand enum cases without replacing explicitly supplied choice text or metadata.
     *
     */
    protected function option(int|string $value, mixed $label): ChoiceConfig
    {
        $option = match (true) {
            $label instanceof ChoiceConfig => $label,
            $label instanceof UnitEnum => new ChoiceConfig($label),
            default => new ChoiceConfig(
                $value,
                $label instanceof HtmlStringable || $label === null ? $label : (string)$label,
            ),
        };
        if ($option->value instanceof UnitEnum) {
            if (!$option->value instanceof BackedEnum) {
                throw new InvalidArgumentException('Choice enum cases must be backed enums.');
            }

            if ($option->label === Unspecified::Value) {
                return $option->label($this->enumLabel($option->value));
            }
        }

        return $option;
    }

    /**
     * Follow CakePHP's enum label contract; applications own translation of labels.
     */
    protected function enumLabel(BackedEnum $case): string
    {
        return $case instanceof EnumLabelInterface
            ? $case->label()
            : Inflector::humanize(Inflector::underscore($case->name));
    }

    /**
     * Create the attribute resolver used for each option in this choice tree.
     */
    protected function createChoiceAttributes(): ChoiceAttributes
    {
        return new ChoiceAttributes($this->theme);
    }
}
