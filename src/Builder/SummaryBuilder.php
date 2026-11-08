<?php
declare(strict_types=1);

namespace FormCompose\Builder;

use Cake\View\HtmlStringable;
use Closure;
use FormCompose\Configuration\SummaryConfig;
use FormCompose\Rendering\RenderableInterface;

/**
 * Configures one summary occurrence and renders it only on demand.
 * @extends ConfigurationBuilder<SummaryConfig>
 */
class SummaryBuilder extends ConfigurationBuilder implements RenderableInterface
{
    /** Set title on this mutable builder. */
    public function title(string|HtmlStringable|null $value): static
    {
        $this->configuration = $this->config()->title($value);

        return $this;
    }

    /** Capture the domain-specific immutable settings. */
    public function config(): SummaryConfig
    {
        return $this->configuration;
    }

    /** Overlay explicitly supplied settings from the same configuration domain. */
    public function apply(SummaryConfig $config): static
    {
        $this->applyConfig($config);

        return $this;
    }

    /** @param \Closure(SummaryConfig): string $render Rendering boundary retaining its owning session. */
    public function __construct(
        protected readonly Closure $render,
        SummaryConfig $config = new SummaryConfig(),
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
