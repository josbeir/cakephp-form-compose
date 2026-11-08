<?php
declare(strict_types=1);

namespace FormCompose\Configuration;

use Cake\View\HtmlStringable;

/** Immutable embed configuration captured before binding or rendering. */
final class EmbedConfig extends Configuration
{
    /** The configured label setting. */
    public string|HtmlStringable|null $label {
        get {
            /** @var mixed $value */
            $value = $this->values['label'] ?? null;
            assert(
                $value === null || is_string($value) || $value instanceof HtmlStringable,
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** Return configuration with an explicit label setting. */
    public function label(string|HtmlStringable|null $value): static
    {
        return $this->changed('label', $value);
    }

    /** The configured errors setting. */
    public bool $errors {
        get {
            /** @var mixed $value */
            $value = $this->values['errors'] ?? true;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit errors setting. */
    public function errors(bool $value = true): static
    {
        return $this->changed('errors', $value);
    }

    /** Initialize common named settings without losing explicit null values. */
    public function __construct(
        string|HtmlStringable|Unspecified|null $label = Unspecified::Value,
        bool|Unspecified $errors = Unspecified::Value,
    ) {
        parent::__construct(array_filter(
            ['label' => $label, 'errors' => $errors],
            static fn(mixed $value): bool => $value !== Unspecified::Value,
        ));
    }

    /** Start an immutable fluent configuration; every setting returns a new instance. */
    public static function builder(): self
    {
        return new self();
    }
}
