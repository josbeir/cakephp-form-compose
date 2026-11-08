<?php
declare(strict_types=1);

namespace FormCompose\Builder;

use Cake\View\HtmlStringable;
use Closure;
use FormCompose\Blueprint\RepeatedDefinition;
use FormCompose\Configuration\EmbedConfig;

/**
 * Stages a repeated child, selecting final keys before compiling any row hooks.
 * @extends ConfigurationBuilder<EmbedConfig>
 */
class RepeatedBlueprintBuilder extends ConfigurationBuilder
{
    /** Set label on this mutable builder. */
    public function label(string|HtmlStringable|null $value): static
    {
        $this->configuration = $this->config()->label($value);

        return $this;
    }

    /** Set errors on this mutable builder. */
    public function errors(bool $value = true): static
    {
        $this->configuration = $this->config()->errors($value);

        return $this;
    }

    /** Capture the domain-specific immutable settings. */
    public function config(): EmbedConfig
    {
        return $this->configuration;
    }

    /** Overlay explicitly supplied settings from the same configuration domain. */
    public function apply(EmbedConfig $config): static
    {
        $this->applyConfig($config);

        return $this;
    }

    use RowSelectionTrait;

    protected ?RepeatedDefinition $compiled = null;

    /** @var list<int|string>|null Last compiled row selection. */
    protected ?array $compiledKeys = null;

    /** @param \Closure(list<int|string>|null): RepeatedDefinition $compile Compile selected rows in their scoped contexts. */
    public function __construct(protected readonly string $path, protected readonly Closure $compile)
    {
        parent::__construct(new EmbedConfig());
    }

    /** Capture selected rows and current occurrence presentation independently. */
    public function build(): RepeatedDefinition
    {
        $compiled = $this->compiled();

        return new RepeatedDefinition(
            $this->path,
            $compiled->blueprint,
            $compiled->keys,
            $this->config(),
            $compiled->rows,
        );
    }

    /** Reuse compiled rows until the explicit row selection changes. */
    protected function compiled(): RepeatedDefinition
    {
        $keys = $this->selectedKeys;
        if ($this->compiled instanceof RepeatedDefinition && $this->compiledKeys === $keys) {
            return $this->compiled;
        }

        $compiled = ($this->compile)($keys);
        $this->compiledKeys = $keys;

        return $this->compiled = $compiled;
    }
}
