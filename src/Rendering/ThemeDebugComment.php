<?php
declare(strict_types=1);

namespace FormCompose\Rendering;

/**
 * Builds safe theme-debug comments, including compact CakePHP hidden-field notes.
 *
 * @internal
 */
final class ThemeDebugComment
{
    /**
     * Return the internal infrastructure field and purpose for compact output.
     *
     * @return array{string, string}|null
     */
    public static function internalField(RenderNode $node, string $part): ?array
    {
        if ($node->scope !== 'form' || $part !== 'hidden') {
            return null;
        }

        /** @var mixed $attributes */
        $attributes = $node->variables['attributes'] ?? null;
        /** @var mixed $name */
        $name = $attributes instanceof Attributes ? $attributes->values['name'] ?? null : null;
        if (!is_string($name)) {
            return null;
        }

        $purpose = match ($name) {
            '_csrfToken' => 'CSRF protection',
            '_method' => 'HTTP method override',
            '_Token[fields]' => 'Form protection signature',
            '_Token[unlocked]' => 'Form protection unlocked fields',
            '_Token[debug]' => 'Form protection diagnostics',
            default => null,
        };

        return $purpose === null ? null : [$name, $purpose];
    }

    /**
     * Add metadata for application-owned form hidden fields to the full trace.
     *
     * @param list<string> $lines
     */
    public static function appendHiddenFieldMetadata(array &$lines, RenderNode $node, string $part): void
    {
        if ($node->scope !== 'form' || $part !== 'hidden') {
            return;
        }

        /** @var mixed $attributes */
        $attributes = $node->variables['attributes'] ?? null;
        /** @var mixed $name */
        $name = $attributes instanceof Attributes ? $attributes->values['name'] ?? null : null;
        if (is_string($name)) {
            $lines[] = 'field: ' . $name;
            $lines[] = 'purpose: Internal form input';
        }
    }

    /**
     * Encode metadata as inert comment text, keeping identifiers on one line.
     *
     * @param list<string> $lines Diagnostic lines without values or template variables.
     */
    public static function format(array $lines, string $indent): string
    {
        $lines = array_map(
            static fn(string $line): string => str_replace(
                '--',
                '&#45;&#45;',
                Html::text(str_replace(["\r", "\n"], ['\\r', '\\n'], $line)),
            ),
            $lines,
        );

        if (count($lines) === 1) {
            return $indent . '<!-- ' . $lines[0] . " -->\n";
        }

        return $indent . '<!-- ' . implode("\n" . $indent, $lines) . "\n" . $indent . "-->\n";
    }
}
