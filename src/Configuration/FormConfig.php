<?php
declare(strict_types=1);

namespace FormCompose\Configuration;

use Cake\View\HtmlStringable;
use FormCompose\Rendering\RendererInterface;
use FormCompose\Rendering\Theme;

/** Immutable form configuration captured before binding or rendering. */
final class FormConfig extends Configuration
{
    /** Initialize common named settings without losing explicit null values. */
    public function __construct(
        array|string|false|Unspecified|null $url = Unspecified::Value,
        string|Unspecified|null $method = Unspecified::Value,
        string|Unspecified|null $id = Unspecified::Value,
    ) {
        parent::__construct(array_filter(
            ['url' => $url, 'method' => $method, 'id' => $id],
            static fn(mixed $value): bool => $value !== Unspecified::Value,
        ));
    }

    /** Complete form presentation selects its layout part. */
    protected function primaryPart(): string
    {
        return 'layout';
    }

    /** Start an immutable fluent configuration; every setting returns a new instance. */
    public static function builder(): self
    {
        return new self();
    }

    /** @var array|string|false|null A CakePHP route or URL. */
    public array|string|false|null $url {
        get {
            /** @var mixed $value */
            $value = $this->values['url'] ?? null;
            assert(
                $value === null || $value === false || is_array($value) || is_string($value),
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** @param array|string|false|null $value A CakePHP route or URL. */
    public function url(array|string|false|null $value): static
    {
        return $this->changed('url', $value);
    }

    /** The configured method setting. */
    public ?string $method {
        get {
            /** @var mixed $value */
            $value = $this->values['method'] ?? null;
            assert($value === null || is_string($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit method setting. */
    public function method(?string $value): static
    {
        return $this->changed('method', $value);
    }

    /** The configured id setting. */
    public ?string $id {
        get {
            /** @var mixed $value */
            $value = $this->values['id'] ?? null;
            assert($value === null || is_string($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit id setting. */
    public function id(?string $value): static
    {
        return $this->changed('id', $value);
    }

    /** The configured multipart setting. */
    public bool $multipart {
        get {
            /** @var mixed $value */
            $value = $this->values['multipart'] ?? false;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit multipart setting. */
    public function multipart(bool $value = true): static
    {
        return $this->changed('multipart', $value);
    }

    /** The configured enctype setting. */
    public ?string $enctype {
        get {
            /** @var mixed $value */
            $value = $this->values['enctype'] ?? null;
            assert($value === null || is_string($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit enctype setting. */
    public function enctype(?string $value): static
    {
        return $this->changed('enctype', $value);
    }

    /** The configured encoding setting. */

    public ?string $encoding {
        get {
            /** @var mixed $value */
            $value = $this->values['encoding'] ?? null;
            assert($value === null || is_string($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit encoding setting. */
    public function encoding(?string $value): static
    {
        return $this->changed('encoding', $value);
    }

    /** @var list<string> Configured valueSources data. */
    public array $valueSources {
        get {
            /** @var mixed $value */
            $value = $this->values['valueSources'] ?? ['data', 'context'];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var list<string> $value */

            return $value;
        }
    }

    /** @param list<string> $value Configured valueSources data. */
    public function valueSources(array $value): static
    {
        return $this->changed('valueSources', $value);
    }

    /** @var list<string> Configured passwordFields data. */
    public array $passwordFields {
        get {
            /** @var mixed $value */
            $value = $this->values['passwordFields'] ?? ['password', 'passwd'];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var list<string> $value */

            return $value;
        }
    }

    /** @param list<string> $value Configured passwordFields data. */
    public function passwordFields(array $value): static
    {
        return $this->changed('passwordFields', $value);
    }

    /** @var array<string, string> Configured typeMap data. */
    public array $typeMap {
        get {
            /** @var mixed $value */
            $value = $this->values['typeMap'] ?? [];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var array<string, string> $value */

            return $value;
        }
    }

    /** @param array<string, string> $value Configured typeMap data. */
    public function typeMap(array $value): static
    {
        return $this->changed('typeMap', $value);
    }

    /** @var array<string, \FormCompose\Form\Field\FieldTypeInterface> Configured types data. */
    public array $types {
        get {
            /** @var mixed $value */
            $value = $this->values['types'] ?? [];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var array<string, \FormCompose\Form\Field\FieldTypeInterface> $value */

            return $value;
        }
    }

    /** @param array<string, \FormCompose\Form\Field\FieldTypeInterface> $value Configured types data. */
    public function types(array $value): static
    {
        return $this->changed('types', $value);
    }

    /** @var array<string, mixed> Configured context data. */

    public array $context {
        get {
            /** @var mixed $value */
            $value = $this->values['context'] ?? [];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var array<string, mixed> $value */

            return $value;
        }
    }

    /** @param array<string, mixed> $value Configured context data. */
    public function context(array $value): static
    {
        return $this->changed('context', $value);
    }

    /** @var list<string> Blueprint aliases or classes in rendering order. */
    public array $blueprints {
        get {
            /** @var mixed $value */
            $value = $this->values['blueprints'] ?? [];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var list<string> $value */

            return $value;
        }
    }

    /** Append a blueprint without changing this config; null clears the selection. */
    public function blueprint(?string $value): static
    {
        return $this->changed('blueprints', $value === null ? [] : [...$this->blueprints, $value]);
    }

    /** The configured theme setting. */
    public ?Theme $theme {
        get {
            /** @var mixed $value */
            $value = $this->values['theme'] ?? null;
            assert(
                $value === null || $value instanceof Theme,
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** Return configuration with an explicit theme setting. */
    public function theme(?Theme $value): static
    {
        return $this->changed('theme', $value);
    }

    /** The configured renderer setting. */
    public ?RendererInterface $renderer {
        get {
            /** @var mixed $value */
            $value = $this->values['renderer'] ?? null;
            assert(
                $value === null || $value instanceof RendererInterface,
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** Return configuration with an explicit renderer setting. */
    public function renderer(?RendererInterface $value): static
    {
        return $this->changed('renderer', $value);
    }

    /** The configured errorSummary setting. */
    public bool|SummaryConfig $errorSummary {
        get {
            /** @var mixed $value */
            $value = $this->values['errorSummary'] ?? false;
            assert(
                is_bool($value) || $value instanceof SummaryConfig,
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** Return configuration with an explicit errorSummary setting. */
    public function errorSummary(bool|SummaryConfig $value = true): static
    {
        return $this->changed('errorSummary', $value);
    }

    /** The configured submit setting. */

    public string|HtmlStringable|false|null $submit {
        get {
            /** @var mixed $value */
            $value = $this->values['submit'] ?? null;
            assert(
                $value === null || $value === false || is_string($value) || $value instanceof HtmlStringable,
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** Return configuration with an explicit submit setting. */
    public function submit(string|HtmlStringable|false|null $value, ?ButtonConfig $config = null): static
    {
        $updated = $this->changed('submit', $value);

        return $config instanceof ButtonConfig ? $updated->submitConfig($config) : $updated;
    }

    /** Return configuration that omits the complete form's default action. */
    public function withoutSubmit(): static
    {
        return $this->submit(false);
    }

    /** The configured submitConfig setting. */
    public ButtonConfig $submitConfig {
        get {
            /** @var mixed $value */
            $value = $this->values['submitConfig'] ?? new ButtonConfig();
            assert($value instanceof ButtonConfig, 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit submitConfig setting. */
    public function submitConfig(ButtonConfig $value): static
    {
        return $this->changed('submitConfig', $value);
    }
}
