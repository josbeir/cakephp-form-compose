<?php
declare(strict_types=1);

namespace FormCompose\Test\Fixture\Rendering;

use Cake\View\HtmlStringable;

/**
 * Represents application-owned trusted content independently of CakePHP's wrapper.
 */
final readonly class CustomHtml implements HtmlStringable
{
    /** Capture developer-controlled markup. */
    public function __construct(protected string $markup)
    {
    }

    /** Return the trusted content. */
    public function __toString(): string
    {
        return $this->markup;
    }
}
