<?php
declare(strict_types=1);

namespace FormCompose\Form\Field;

use Cake\View\HtmlStringable;
use FormCompose\Rendering\Attributes;

/**
 * Holds template data for one select option, radio/checkbox choice, or choice group.
 *
 * Choices builds these records; NativeType selects the matching option or choice
 * template. An item has input attributes and optional label/container attributes.
 * A group carries child Choice records so templates can render nested options.
 */
final readonly class Choice
{
    /**
     * Describe an option or group and its presentation attributes.
     *
     * @param list<\FormCompose\Form\Field\Choice> $children Nested choices.
     * @param array<string, \FormCompose\Rendering\Attributes> $presentation Label and container attributes.
     */
    public function __construct(
        public string $kind,
        public string|HtmlStringable $label,
        public Attributes $attributes,
        public array $children = [],
        public array $presentation = [],
    ) {
    }
}
