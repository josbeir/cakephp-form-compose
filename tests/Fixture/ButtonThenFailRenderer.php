<?php
declare(strict_types=1);

namespace FormCompose\Test\Fixture;

use FormCompose\Form\FormSession;
use FormCompose\Rendering\PhpRenderer;
use FormCompose\Rendering\RenderNode;
use RuntimeException;

/** Exercises nested button rendering followed by an enclosing control failure. */
class ButtonThenFailRenderer extends PhpRenderer
{
    public ?FormSession $form = null;

    public string $name = 'discarded';

    public bool $fail = true;

    /** Render the field and a named button before optionally discarding both. */
    public function render(string $part, RenderNode $node): string
    {
        $html = parent::render($part, $node);
        if ($node->scope === 'field' && $node->type === 'text' && $part === 'control') {
            $form = $this->form;
            assert($form instanceof FormSession, 'The test must attach the owning session before rendering.');
            $html .= $form->submit('Save')->name($this->name)->render();
            if ($this->fail) {
                throw new RuntimeException('Outer control failed.');
            }
        }

        return $html;
    }
}
