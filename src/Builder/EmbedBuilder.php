<?php
declare(strict_types=1);

namespace FormCompose\Builder;

use Cake\View\HtmlStringable;
use Closure;
use FormCompose\Configuration\EmbedConfig;
use FormCompose\Rendering\RenderableInterface;

/**
 * Configures one embed occurrence and renders it only on demand.
 * @extends ConfigurationBuilder<EmbedConfig>
 */
class EmbedBuilder extends ConfigurationBuilder implements RenderableInterface
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

    /** @param \Closure(EmbedConfig): string $render Rendering boundary retaining its owning session. */
    public function __construct(
        protected readonly Closure $render,
        EmbedConfig $config = new EmbedConfig(),
    ) {
        parent::__construct($config);
    }

    /** Produce markup using the final configuration. */
    public function render(): string
    {
        return ($this->render)($this->config());
    }

    /** Render at the point of string conversion; exceptions remain visible to callers. */
    public function __toString(): string
    {
        return $this->render();
    }
}
