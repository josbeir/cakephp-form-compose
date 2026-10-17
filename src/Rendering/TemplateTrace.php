<?php
declare(strict_types=1);

namespace FormCompose\Rendering;

use FormCompose\Field\BoundField;

/**
 * Describes one successful PHP element lookup for optional HTML comment diagnostics.
 *
 * PhpRenderer creates this local record after rendering succeeds. Only template
 * identifiers and field paths are exposed; values and other variables stay private.
 *
 * @internal Rendering diagnostic metadata, not an application configuration API.
 */
final readonly class TemplateTrace
{
    /**
     * Capture the selected element and the candidates actually attempted.
     *
     * @param list<string> $lookup Ordered MISS/MATCH entries; explicit overrides have no lookup.
     */
    public function __construct(
        public string $part,
        public string $element,
        public string $source,
        public array $lookup = [],
    ) {
    }

    /**
     * Put diagnostic boundaries on separate lines while keeping empty parts empty.
     *
     * @param non-negative-int $depth Render nesting level for comments and the first HTML tag.
     */
    public function wrap(string $html, RenderNode $node, int $depth = 0): string
    {
        if ($html === '') {
            return $html;
        }

        $indent = str_repeat('  ', $depth);
        $internal = ThemeDebugComment::internalField($node, $this->part);
        if ($internal !== null) {
            [$name, $purpose] = $internal;
            $html = (string)preg_replace('/\\A((?:[ \\t]*\\r?\\n)*)[ \\t]*(?=<[a-zA-Z\\/])/', '$1' . $indent, $html);
            $separator = str_ends_with($html, "\n") ? '' : "\n";

            return (
                "\n"
                    . ThemeDebugComment::format(
                        ['FormCompose internal hidden field: ' . $name . ' (' . $purpose . ')'],
                        $indent,
                    )
                    . $html
                    . $separator
                    . ThemeDebugComment::format(['FormCompose END part=' . $this->part], $indent)
            );
        }

        $lines = $this->lines($node);

        // Align the first HTML tag without rewriting lines inside controls or nested traces.
        $html = (string)preg_replace('/\\A((?:[ \t]*\r?\n)*)[ \t]*(?=<[a-zA-Z\/])/', '$1' . $indent, $html);
        $separator = str_ends_with($html, "\n") ? '' : "\n";

        return (
            "\n"
                . ThemeDebugComment::format($lines, $indent)
                . $html
                . $separator
                . ThemeDebugComment::format(['FormCompose END part=' . $this->part], $indent)
        );
    }

    /**
     * Build the metadata lines for a complete element trace.
     *
     * @return list<string>
     */
    private function lines(RenderNode $node): array
    {
        $lines = ['FormCompose BEGIN', 'scope: ' . $node->scope, 'part: ' . $this->part, 'type: ' . $node->type];
        /** @var mixed $field */
        $field = $node->variables['field'] ?? null;
        if ($field instanceof BoundField) {
            $lines[] = 'field: ' . $field->state->path;
        }

        ThemeDebugComment::appendHiddenFieldMetadata($lines, $node, $this->part);

        /** @var mixed $variant */
        $variant = $node->options['variant'] ?? null;
        if (is_string($variant)) {
            $lines[] = 'variant: ' . $variant;
        }

        $lines[] = 'source: ' . $this->source;
        $lines[] = 'element: ' . $this->element;
        if ($this->lookup !== []) {
            $lines[] = 'lookup:';
            foreach ($this->lookup as $attempt) {
                $lines[] = '  ' . $attempt;
            }
        }

        return $lines;
    }
}
