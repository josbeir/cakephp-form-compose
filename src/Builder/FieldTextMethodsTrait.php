<?php
declare(strict_types=1);

namespace FormCompose\Builder;

use Cake\View\HtmlStringable;
use Closure;
use FormCompose\Configuration\FieldConfig;

/** Mutable field text setters.
 */
trait FieldTextMethodsTrait
{
    /** Set label on this mutable builder. */
    public function label(string|HtmlStringable|Closure|false|null $value): static
    {
        $this->updateFieldConfig($this->config()->label($value));

        return $this;
    }

    /** Set help on this mutable builder. */
    public function help(string|HtmlStringable|Closure|false|null $value): static
    {
        $this->updateFieldConfig($this->config()->help($value));

        return $this;
    }

    /** Set errors on this mutable builder. */
    public function errors(bool $value = true): static
    {
        $this->updateFieldConfig($this->config()->errors($value));

        return $this;
    }

    /** Capture domain-specific immutable configuration. */
    abstract public function config(): FieldConfig;

    /** Store a typed configuration update on the attached builder. */
    abstract protected function updateFieldConfig(FieldConfig $config): void;
}
