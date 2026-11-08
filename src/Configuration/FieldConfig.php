<?php
declare(strict_types=1);

namespace FormCompose\Configuration;

use BackedEnum;
use Cake\View\HtmlStringable;
use Closure;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Immutable unbound field settings with named arguments and fluent configuration.
 * Explicit values, including null, remain distinct from missing configuration.
 */
final class FieldConfig extends Configuration
{
    /** @var array<string, mixed> Application-owned custom type settings. */
    public array $extensionData {
        get {
            /** @var mixed $value */
            $value = $this->values['extensionData'] ?? [];
            assert(is_array($value), 'Typed configuration must retain its declared value type.');

            /** @var array<string, mixed> $value */

            return $value;
        }
    }

    /** @param array<string, mixed> $value Application-owned custom type settings. */
    public function extensionData(array $value): static
    {
        return $this->changed('extensionData', $value);
    }

    /** Whether an explicit field value was supplied. */
    public bool $hasValue {
        get => array_key_exists('value', $this->values);
    }

    /** Whether disabled state was explicitly configured. */
    public bool $hasDisabled {
        get => array_key_exists('disabled', $this->values);
    }

    /** Whether readonly state was explicitly configured. */
    public bool $hasReadonly {
        get => array_key_exists('readonly', $this->values);
    }

    /** Whether choices were supplied, including an explicit discovery opt-out. */
    public bool $hasChoices {
        get => array_key_exists('choices', $this->values);
    }

    /** Initialize common named settings; fluent methods return independent configurations. */
    public function __construct(
        string|Unspecified|null $type = Unspecified::Value,
        string|HtmlStringable|Closure|Unspecified|false|null $label = Unspecified::Value,
        string|HtmlStringable|Closure|Unspecified|false|null $help = Unspecified::Value,
    ) {
        parent::__construct(array_filter(
            ['type' => $type, 'label' => $label, 'help' => $help],
            static fn(mixed $value): bool => $value !== Unspecified::Value,
        ));
    }

    /** Start an immutable fluent configuration; every setting returns a new instance. */
    public static function builder(): self
    {
        return new self();
    }

    /** The configured type setting. */
    public ?string $type {
        get {
            /** @var mixed $value */
            $value = $this->values['type'] ?? null;
            assert($value === null || is_string($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** The configured name setting. */
    public ?string $name {
        get {
            /** @var mixed $value */
            $value = $this->values['name'] ?? null;
            assert($value === null || is_string($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
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

    /** Return configuration with an explicit type setting. */
    public function type(?string $value): static
    {
        return $this->changed('type', $value);
    }

    /** Return configuration with an explicit name setting. */
    public function name(?string $value): static
    {
        return $this->changed('name', $value);
    }

    /** Return configuration with an explicit id setting. */
    public function id(?string $value): static
    {
        return $this->changed('id', $value);
    }

    /** The configured label setting. */
    public string|HtmlStringable|Closure|false|null $label {
        get {
            /** @var mixed $value */
            $value = $this->values['label'] ?? null;
            assert(
                $value === null
                    || $value === false
                    || is_string($value)
                    || $value instanceof HtmlStringable
                    || $value instanceof Closure,
                'Field text retains a supported text or deferred value.',
            );

            return $value;
        }
    }

    /** The configured help setting. */
    public string|HtmlStringable|Closure|false|null $help {
        get {
            /** @var mixed $value */
            $value = $this->values['help'] ?? null;
            assert(
                $value === null
                    || $value === false
                    || is_string($value)
                    || $value instanceof HtmlStringable
                    || $value instanceof Closure,
                'Field text retains a supported text or deferred value.',
            );

            return $value;
        }
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

    /** Return configuration with an explicit label setting. */
    public function label(string|HtmlStringable|Closure|false|null $value): static
    {
        return $this->changed('label', $value);
    }

    /** Return configuration with an explicit help setting. */
    public function help(string|HtmlStringable|Closure|false|null $value): static
    {
        return $this->changed('help', $value);
    }

    /** Return configuration with an explicit errors setting. */
    public function errors(bool $value = true): static
    {
        return $this->changed('errors', $value);
    }

    /** The configured value setting. */
    public mixed $value {
        get {
            return $this->values['value'] ?? null;
        }
    }

    /** The configured defaultValue setting. */
    public mixed $defaultValue {
        get {
            return $this->values['defaultValue'] ?? null;
        }
    }

    /** The configured schemaDefault setting. */
    public bool $schemaDefault {
        get {
            /** @var mixed $value */
            $value = $this->values['schemaDefault'] ?? true;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** The configured timezone setting. */
    public DateTimeZone|string|null $timezone {
        get {
            /** @var mixed $value */
            $value = $this->values['timezone'] ?? null;
            assert(
                $value === null || is_string($value) || $value instanceof DateTimeZone,
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** Return configuration with an explicit value setting. */
    public function value(mixed $value): static
    {
        return $this->changed('value', $value);
    }

    /** Return configuration with an explicit defaultValue setting. */
    public function default(mixed $value): static
    {
        return $this->changed('defaultValue', $value);
    }

    /** Return configuration with an explicit schemaDefault setting. */
    public function schemaDefault(bool $value = true): static
    {
        return $this->changed('schemaDefault', $value);
    }

    /** Return configuration with an explicit timezone setting. */
    public function timezone(DateTimeZone|string|null $value): static
    {
        return $this->changed('timezone', $value);
    }

    /** The configured required setting. */
    public ?bool $required {
        get {
            /** @var mixed $value */
            $value = $this->values['required'] ?? null;
            assert($value === null || is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** The configured disabled setting. */
    public bool $disabled {
        get {
            /** @var mixed $value */
            $value = $this->values['disabled'] ?? false;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** The configured readonly setting. */
    public bool $readonly {
        get {
            /** @var mixed $value */
            $value = $this->values['readonly'] ?? false;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** The configured multiple setting. */
    public ?bool $multiple {
        get {
            /** @var mixed $value */
            $value = $this->values['multiple'] ?? null;
            assert($value === null || is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** The configured secure setting. */
    public bool $secure {
        get {
            /** @var mixed $value */
            $value = $this->values['secure'] ?? true;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** Return configuration with an explicit required setting. */
    public function required(?bool $value = true): static
    {
        return $this->changed('required', $value);
    }

    /** Return configuration with an explicit disabled setting. */
    public function disabled(bool $value = true): static
    {
        return $this->changed('disabled', $value);
    }

    /** Return configuration with an explicit readonly setting. */
    public function readonly(bool $value = true): static
    {
        return $this->changed('readonly', $value);
    }

    /** Return configuration with an explicit multiple setting. */
    public function multiple(?bool $value = true): static
    {
        return $this->changed('multiple', $value);
    }

    /** Return configuration with an explicit secure setting. */
    public function secure(bool $value = true): static
    {
        return $this->changed('secure', $value);
    }

    /** The configured choices setting. */
    public iterable|string|null $choices {
        get {
            /** @var mixed $value */
            $value = $this->values['choices'] ?? null;
            assert(
                $value === null || is_iterable($value) || is_string($value),
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** The configured placeholder setting. */
    public bool|string|null $placeholder {
        get {
            /** @var mixed $value */
            $value = $this->values['placeholder'] ?? null;
            assert(
                $value === null || is_bool($value) || is_string($value),
                'Typed configuration must retain its declared value type.',
            );

            return $value;
        }
    }

    /** The configured checkedValue setting. */
    public mixed $checkedValue {
        get {
            return $this->values['checkedValue'] ?? '1';
        }
    }

    /** The configured uncheckedValue setting. */
    public mixed $uncheckedValue {
        get {
            return $this->values['uncheckedValue'] ?? '0';
        }
    }

    /** The configured fallback setting. */
    public bool $fallback {
        get {
            /** @var mixed $value */
            $value = $this->values['fallback'] ?? true;
            assert(is_bool($value), 'Typed configuration must retain its declared value type.');

            return $value;
        }
    }

    /** @param iterable<mixed, mixed>|string|null $value Choice maps, lazy sources, or an enum class. */
    public function choices(iterable|string|null $value): static
    {
        if (is_string($value) && !is_subclass_of($value, BackedEnum::class)) {
            throw new InvalidArgumentException('Choices must use a backed enum class name or an iterable.');
        }

        return $this->changed('choices', $value);
    }

    /** Return configuration with an explicit placeholder setting. */
    public function placeholder(bool|string|null $value): static
    {
        return $this->changed('placeholder', $value);
    }

    /** Return configuration with an explicit checkedValue setting. */
    public function checkedValue(mixed $value): static
    {
        return $this->changed('checkedValue', $value);
    }

    /** Return configuration with an explicit uncheckedValue setting. */
    public function uncheckedValue(mixed $value): static
    {
        return $this->changed('uncheckedValue', $value);
    }

    /** Return configuration with an explicit fallback setting. */
    public function fallback(bool $value = true): static
    {
        return $this->changed('fallback', $value);
    }
}
