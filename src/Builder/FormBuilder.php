<?php
declare(strict_types=1);

namespace FormCompose\Builder;

use Cake\View\HtmlStringable;
use Closure;
use FormCompose\Configuration\ButtonConfig;
use FormCompose\Configuration\FormConfig;
use FormCompose\Configuration\SummaryConfig;
use FormCompose\Form\FormSession;
use FormCompose\Rendering\RenderableInterface;
use FormCompose\Rendering\RendererInterface;
use FormCompose\Rendering\Theme;

/**
 * Configures a form before binding; every bind captures an independent session.
 * @extends ConfigurationBuilder<FormConfig>
 */
class FormBuilder extends ConfigurationBuilder implements RenderableInterface
{
    protected string $primaryPart = 'layout';

    /** Capture the domain-specific immutable settings. */
    public function config(): FormConfig
    {
        return $this->configuration;
    }

    /** Store domain settings through the shared immutable snapshot boundary. */
    protected function updateFormConfig(FormConfig $config): void
    {
        $this->configuration = $config;
    }

    /** Overlay explicitly supplied settings from the same configuration domain. */
    public function apply(FormConfig $config): static
    {
        $this->applyConfig($config);

        return $this;
    }

    /** @param \Closure(FormConfig): FormSession $bind Session factory preserving helper extension points. */
    public function __construct(
        protected readonly Closure $bind,
        FormConfig $config = new FormConfig(),
    ) {
        parent::__construct($config);
    }

    /** Bind data, blueprint defaults, and presentation into an isolated form lifecycle. */
    public function bind(): FormSession
    {
        return ($this->bind)($this->config());
    }

    /** Select the complete form's default action. */
    public function submit(string|HtmlStringable|false|null $value, ?ButtonConfig $config = null): static
    {
        $this->updateFormConfig($this->config()->submit($value, $config));

        return $this;
    }

    /** Omit the complete form's default action. */
    public function withoutSubmit(): static
    {
        return $this->submit(false);
    }

    /** Bind a fresh session and render its complete layout. */
    public function render(): string
    {
        return $this->bind()->render();
    }

    /** Render a complete form at the point of string conversion. */
    public function __toString(): string
    {
        return $this->render();
    }

    /** @param array|string|false|null $value A CakePHP route or URL. */
    public function url(array|string|false|null $value): static
    {
        $this->updateFormConfig($this->config()->url($value));

        return $this;
    }

    /** Set method on this mutable builder. */
    public function method(?string $value): static
    {
        $this->updateFormConfig($this->config()->method($value));

        return $this;
    }

    /** Set id on this mutable builder. */
    public function id(?string $value): static
    {
        $this->updateFormConfig($this->config()->id($value));

        return $this;
    }

    /** Set multipart on this mutable builder. */
    public function multipart(bool $value = true): static
    {
        $this->updateFormConfig($this->config()->multipart($value));

        return $this;
    }

    /** Set enctype on this mutable builder. */
    public function enctype(?string $value): static
    {
        $this->updateFormConfig($this->config()->enctype($value));

        return $this;
    }

    /** Set encoding on this mutable builder. */

    public function encoding(?string $value): static
    {
        $this->updateFormConfig($this->config()->encoding($value));

        return $this;
    }

    /** @param list<string> $value Configured valueSources data. */
    public function valueSources(array $value): static
    {
        $this->updateFormConfig($this->config()->valueSources($value));

        return $this;
    }

    /** @param list<string> $value Configured passwordFields data. */
    public function passwordFields(array $value): static
    {
        $this->updateFormConfig($this->config()->passwordFields($value));

        return $this;
    }

    /** @param array<string, string> $value Configured typeMap data. */
    public function typeMap(array $value): static
    {
        $this->updateFormConfig($this->config()->typeMap($value));

        return $this;
    }

    /** @param array<string, \FormCompose\Form\Field\FieldTypeInterface> $value Configured types data. */
    public function types(array $value): static
    {
        $this->updateFormConfig($this->config()->types($value));

        return $this;
    }

    /** @param array<string, mixed> $value Configured context data. */

    public function context(array $value): static
    {
        $this->updateFormConfig($this->config()->context($value));

        return $this;
    }

    /** Append a blueprint in rendering order; null clears the selection before binding. */
    public function blueprint(?string $value): static
    {
        $this->updateFormConfig($this->config()->blueprint($value));

        return $this;
    }

    /** Set theme on this mutable builder. */
    public function theme(?Theme $value): static
    {
        $this->updateFormConfig($this->config()->theme($value));

        return $this;
    }

    /** Set renderer on this mutable builder. */
    public function renderer(?RendererInterface $value): static
    {
        $this->updateFormConfig($this->config()->renderer($value));

        return $this;
    }

    /** Set errorSummary on this mutable builder. */
    public function errorSummary(bool|SummaryConfig $value = true): static
    {
        $this->updateFormConfig($this->config()->errorSummary($value));

        return $this;
    }

    /** Set submitConfig on this mutable builder. */
    public function submitConfig(ButtonConfig $value): static
    {
        $this->updateFormConfig($this->config()->submitConfig($value));

        return $this;
    }
}
