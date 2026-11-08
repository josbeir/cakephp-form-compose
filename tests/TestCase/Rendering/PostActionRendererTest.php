<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Rendering;

use Cake\View\HtmlStringable;
use FormCompose\Binding\FormBinding;
use FormCompose\Blueprint\BlueprintFactory;
use FormCompose\Configuration\FormConfig;
use FormCompose\Form\FormSession;
use FormCompose\Form\PostAction;
use FormCompose\Rendering\PhpRenderer;
use FormCompose\Rendering\PostActionRenderer;
use FormCompose\Rendering\RenderContext;
use FormCompose\Rendering\RendererInterface;
use FormCompose\Rendering\RenderNode;
use FormCompose\Test\FormTestCase;
use FormCompose\View\Helper\FormHelper;
use RuntimeException;

/**
 * Verifies action failure isolation and normal helper/renderer extension boundaries.
 */
class PostActionRendererTest extends FormTestCase
{
    public function testFailedTriggersDoNotCommitBlocksOrSuppressARetry(): void
    {
        $helper = $this->helper();
        $renderer = new class(new PhpRenderer($helper->getView())) implements RendererInterface {
            protected bool $fail = true;

            public function __construct(protected readonly RendererInterface $delegate)
            {
            }

            public function render(string $part, RenderNode $node): string
            {
                if ($part === 'control' && $node->scope === 'action' && $this->fail) {
                    $this->fail = false;
                    throw new RuntimeException('Trigger failed.');
                }

                return $this->delegate->render($part, $node);
            }
        };
        try {
            $helper->postLink(label: 'Delete', url: '/delete', renderer: $renderer)->block('actions')->render();
            static::fail('Expected trigger rendering failure.');
        } catch (RuntimeException $runtimeException) {
            static::assertSame('Trigger failed.', $runtimeException->getMessage());
            static::assertSame('', $helper->getView()->fetch('actions'));
        }

        $html = $helper->postLink(label: 'Retry', url: '/retry', renderer: $renderer)->block('actions')->render();
        static::assertStringContainsString('Retry', $html);
        static::assertStringContainsString('action="/retry"', $helper->getView()->fetch('actions'));
        static::assertStringNotContainsString('action="/delete"', $helper->getView()->fetch('actions'));
    }

    public function testCustomActionRenderingAndSessionOverridesAreUsedByPublicShortcuts(): void
    {
        $helper = new class($this->helper()->getView()) extends FormHelper {
            protected function createPostActionRenderer(): PostActionRenderer
            {
                return new class(
                    $this->getView(),
                    fn(FormConfig $options, RenderContext $rendering): FormSession => $this->form()
                        ->apply($options)
                        ->theme($rendering->theme)
                        ->renderer($rendering->renderer)
                        ->bind(),
                ) extends PostActionRenderer {
                    protected function trigger(
                        string|HtmlStringable $label,
                        PostAction $action,
                        FormSession $form,
                        RenderContext $rendering,
                    ): string {
                        return (
                            '<span data-custom-action>'
                                . parent::trigger($label, $action, $form, $rendering)
                                . '</span>'
                        );
                    }
                };
            }

            protected function createSession(
                FormBinding $binding,
                RenderContext $rendering,
                BlueprintFactory $blueprints,
            ): FormSession {
                return new class($binding, $rendering, $blueprints) extends FormSession {
                    /** Specialize action form markup through the public session boundary. */
                    public function open(): string
                    {
                        return str_replace('<form ', '<form data-custom-session ', parent::open());
                    }
                };
            }
        };
        $html = $helper->postButton(label: 'Delete', url: '/delete')->render();
        static::assertStringContainsString('data-custom-session', $html);
        static::assertStringContainsString('data-custom-action', $html);
    }
}
