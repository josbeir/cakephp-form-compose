<?php
declare(strict_types=1);

namespace FormCompose\Rendering;

use Cake\View\HtmlString;
use InvalidArgumentException;
use Stringable;

/**
 * Stores HTML attributes and serializes them with escaping and boolean semantics.
 *
 * merge() combines class and ARIA ID-reference tokens; replace() replaces them.
 * Null or false omits an attribute, while true emits its name alone. Operations
 * return new bags, allowing templates to customize a field's prepared attributes.
 *
 * ```php
 * $attributes = new Attributes(['class' => 'control', 'required' => true]);
 * echo (string)$attributes->merge(['class' => ['wide' => true]]);
 * // Emits: class="control wide" required (with a leading space).
 * ```
 */
final readonly class Attributes implements Stringable
{
    /**
     * Construct an attribute bag.
     *
     * @param array<string, mixed> $values Attribute values, including conditional class arrays.
     */
    public function __construct(array $values = [])
    {
        /** @var array<array-key, mixed> $unchecked Native PHP arrays do not enforce generic key types. */
        $unchecked = $values;
        foreach (array_keys($unchecked) as $name) {
            if (!is_string($name) || !preg_match('/^[a-zA-Z_:@][a-zA-Z0-9_.:@-]*$/D', $name)) {
                throw new InvalidArgumentException('Invalid HTML attribute name: ' . $name);
            }
        }

        if (array_key_exists('class', $values) && $values['class'] !== null) {
            $values['class'] = AttributeTokens::normalize($values['class']);
        }

        $this->values = $values;
    }

    /** @var array<string, mixed> */
    public array $values;

    /**
     * Merge attributes; null removes an attribute, classes and ID references combine.
     *
     * @param array<string, mixed> $values Additional attributes.
     */
    public function merge(array $values): self
    {
        $merged = $this->values;
        /** @var mixed $value */
        foreach ($values as $name => $value) {
            if ($value !== null && in_array($name, ['class', 'aria-describedby', 'aria-labelledby'], true)) {
                $value = AttributeTokens::normalize([
                    AttributeTokens::normalize($merged[$name] ?? ''),
                    AttributeTokens::normalize($value),
                ]);
            }

            $merged[$name] = $value;
        }

        return new self($merged);
    }

    /**
     * Replace attributes without merging class or ID-reference tokens.
     *
     * @param array<string, mixed> $values Replacement attributes.
     */
    public function replace(array $values): self
    {
        return new self(array_replace($this->values, $values));
    }

    /**
     * Determine whether an attribute will be emitted, following native presence semantics.
     */
    public function has(string $name): bool
    {
        return isset($this->values[$name]) && $this->values[$name] !== false;
    }

    /**
     * Serialize attributes with safe HTML escaping and correct boolean semantics.
     */
    public function __toString(): string
    {
        $html = '';
        /** @var mixed $value */
        foreach ($this->values as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $html .= ' ' . $name;
                continue;
            }

            $html .=
                ' '
                . $name
                . '="'
                . HtmlString::render($name === 'class' ? AttributeTokens::normalize($value) : (string)$value)
                . '"';
        }

        return $html;
    }
}
