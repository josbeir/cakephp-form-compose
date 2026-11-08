<?php
declare(strict_types=1);

namespace FormCompose\Configuration;

use Cake\View\HtmlStringable;
use FormCompose\Rendering\Attributes;

/** Immutable authoring metadata for an individual select, radio, or checkbox choice. */
final readonly class ChoiceConfig
{
    /** Start immutable fluent metadata for one submitted choice value. */
    public static function builder(
        mixed $value,
        string|HtmlStringable|Unspecified|null $label = Unspecified::Value,
    ): self {
        return new self($value, $label);
    }

    /** @param array<string, mixed> $attributes Per-choice integration attributes. */
    public function __construct(
        public mixed $value,
        public string|HtmlStringable|Unspecified|null $label = Unspecified::Value,
        public array $attributes = [],
        public ?string $id = null,
    ) {
        new Attributes($attributes);
    }

    /** @param array<string, mixed> $attributes Per-choice integration attributes. */
    public function attributes(array $attributes): self
    {
        return clone($this, ['attributes' => new Attributes($this->attributes)->merge($attributes)->values]);
    }

    /** Set an application-owned unique choice identity. */
    public function id(?string $id): self
    {
        return clone($this, ['id' => $id]);
    }

    /** Return a choice with explicit text, including an intentionally blank label. */
    public function label(string|HtmlStringable|null $label): self
    {
        return clone($this, ['label' => $label]);
    }

    /** Return a choice with native disabled state. */
    public function disabled(bool $disabled = true): self
    {
        return $this->attributes(['disabled' => $disabled]);
    }
}
