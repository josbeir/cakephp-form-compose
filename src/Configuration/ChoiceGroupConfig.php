<?php
declare(strict_types=1);

namespace FormCompose\Configuration;

use Cake\View\HtmlStringable;
use FormCompose\Rendering\Attributes;

/** Immutable named choice group; its iterable is consumed only by choice rendering. */
final readonly class ChoiceGroupConfig
{
    /** @var iterable<array-key, mixed> Captured array choices or an unconsumed lazy source. */
    public iterable $choices;

    /**
     * @param iterable<array-key, mixed> $choices Group children.
     * @param array<string, mixed> $attributes Group attributes.
     */
    public function __construct(
        public string|HtmlStringable $label,
        iterable $choices = [],
        public array $attributes = [],
    ) {
        new Attributes($attributes);
        $this->choices = $choices;
    }

    /** @param iterable<array-key, mixed> $choices Group children, consumed only when rendering. */
    public static function builder(string|HtmlStringable $label, iterable $choices = []): self
    {
        return new self($label, $choices);
    }

    /** @param iterable<array-key, mixed> $choices Replacement group children. */
    public function choices(iterable $choices): self
    {
        return clone($this, ['choices' => $choices]);
    }

    /** @param array<string, mixed> $attributes Per-group integration attributes. */
    public function attributes(array $attributes): self
    {
        return clone($this, ['attributes' => new Attributes($this->attributes)->merge($attributes)->values]);
    }
}
