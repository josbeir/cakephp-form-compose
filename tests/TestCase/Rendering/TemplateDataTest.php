<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Rendering;

use Cake\View\Form\ArrayContext;
use FormCompose\Blueprint\FormDefinition;
use FormCompose\Configuration\FieldConfig;
use FormCompose\Form\Field\FieldDefinition;
use FormCompose\Rendering\PhpRenderer;
use FormCompose\Rendering\Theme;
use FormCompose\Test\Fixture\RecordingRenderer;
use FormCompose\Test\FormTestCase;
use stdClass;
use function Cake\View\html;

/**
 * Verifies template metadata through real elements and the custom renderer contract.
 */
class TemplateDataTest extends FormTestCase
{
    public function testExplicitAndParentThemeTemplatesReceiveDataAndEscapeAtOutput(): void
    {
        $form = $this->helper()
            ->form()
            ->theme(new Theme(['themes/data']))
            ->bind();
        $form->open();

        $html = $form->control('email')
            ->partTemplate('control', 'themes/data/control')
            ->templateData(['marker' => 'contact', 'content' => '<script>alert(1)</script>'])
            ->render();
        $trusted = $form->control('name')
            ->templateData(['content' => html('<strong>Trusted</strong>')])
            ->render();
        $form->close();

        static::assertSame(2, substr_count($html, 'data-template-marker="contact"'));
        static::assertSame(2, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        static::assertStringNotContainsString('<script>', $html);
        static::assertStringContainsString('name="email"', $html);
        static::assertStringContainsString('<strong>Trusted</strong>', $trusted);
    }

    public function testPartsNativeChoicesAndCustomRenderersReceiveUnmodifiedValues(): void
    {
        $helper = $this->helper();
        $renderer = new RecordingRenderer(new PhpRenderer($helper->getView()));
        $form = $helper->form(new ArrayContext([
            'errors' => ['status' => ['invalid' => 'Invalid']],
        ]))
            ->renderer($renderer)
            ->bind();
        $account = new stdClass();
        $data = ['marker' => 'shared', 'account' => $account, 'off' => false, 'zero' => 0, 'nil' => null];
        $parts = [
            'label',
            'help',
            'errors',
            'input',
            'native',
            'fallback',
            'choice',
            'choice-group',
            'option',
            'option-group',
        ];
        $templates = array_fill_keys($parts, 'themes/data/part');
        $form->open();
        $html = $form->control('status')
            ->type('radio')
            ->choices(['Group' => ['a' => 'A']])
            ->help('Help')
            ->templateData($data)
            ->replaceTemplates($templates)
            ->render();
        $html .= $form->control('category')
            ->choices(['Group' => ['a' => 'A']])
            ->multiple(true)
            ->templateData($data)
            ->replaceTemplates($templates)
            ->render();
        $form->close();

        $rendered = [];
        foreach ($renderer->calls as $call) {
            if ($call['node']->scope !== 'field') {
                continue;
            }

            static::assertSame($data, $call['node']->variables['templateData']);
            $rendered[] = $call['part'];
        }

        foreach (['control', ...$parts] as $part) {
            static::assertContains($part, $rendered);
        }

        static::assertStringContainsString('template-data: shared', $html);
        static::assertStringNotContainsString('templateData=', $html);
        static::assertStringNotContainsString('account=', $html);
    }

    public function testDefinitionsAndInlineOverridesReplaceTheBagWithoutMutatingSharedData(): void
    {
        $contact = new FormDefinition([])->add(
            new FieldDefinition(
                'email',
                FieldConfig::builder()
                    ->partTemplate('control', 'themes/data/control')
                    ->templateData(['marker' => 'original', 'content' => 'Original']),
            ),
        );
        $customized = $contact->with(
            'email',
            FieldConfig::builder()->replaceTemplateData(['marker' => 'customized']),
        );
        $form = $this->helper()->form()->bind();
        $form->open();

        $html = $form->renderDefinition($customized);
        $form->close();
        static::assertStringContainsString('data-template-marker="customized"', $html);
        static::assertStringNotContainsString('Original', $html);
        static::assertSame(
            ['marker' => 'original', 'content' => 'Original'],
            $contact->field('email')->config->presentation->templateData,
        );
        $inline = $form->field($contact->field('email')->path)
            ->apply($contact->field('email')->config->merge(FieldConfig::builder()->replaceTemplateData([])))
            ->resolve();
        static::assertSame([], $inline->state->config->presentation->templateData);
    }

    public function testFormDataIsLocalToFormPartsAndButtonDataIsIndependent(): void
    {
        $helper = $this->helper(attributes: ['csrfToken' => bin2hex(random_bytes(16)), 'formTokenData' => []]);
        $renderer = new RecordingRenderer(new PhpRenderer($helper->getView()));
        $form = $helper->form()
            ->method('patch')
            ->templateData(['marker' => 'binding', 'extra' => 'replaced'])
            ->replaceTemplates(array_fill_keys(['open', 'close', 'hidden'], 'themes/data/part'))
            ->renderer($renderer)
            ->replaceTemplateData(['marker' => 'form'])
            ->bind();
        $html = $form->open();
        $html .= $form->control('email')->render();
        $html .= $form->submit('Save')
            ->partTemplate('control', 'themes/data/part')
            ->templateData(['marker' => 'button'])
            ->render();
        $html .= $form->submit('Cancel')->render();
        $html .= $form->close();
        $buttonData = [];
        $formParts = [];
        foreach ($renderer->calls as $call) {
            /** @var array<string, mixed> $data */
            $data = $call['node']->variables['templateData'];
            if ($call['node']->scope === 'form') {
                static::assertSame(['marker' => 'form'], $data);
                $formParts[] = $call['part'];
            } elseif ($call['node']->scope === 'button') {
                $buttonData[] = $data;
            } else {
                static::assertSame([], $data);
            }
        }

        static::assertContains('open', $formParts);
        static::assertContains('close', $formParts);
        static::assertContains('hidden', $formParts);
        static::assertSame([['marker' => 'button'], []], $buttonData);
        static::assertStringContainsString('template-data: form', $html);
        static::assertStringContainsString('template-data: button', $html);
        static::assertStringNotContainsString('templateData=', $html);
        static::assertStringNotContainsString('marker=', $html);
        static::assertStringNotContainsString('replaced', $html);
    }

    public function testBindingDataAndEmptyOpeningOverridesFollowNormalPrecedence(): void
    {
        $helper = $this->helper();
        $first = $helper->form()
            ->templateData(['marker' => 'bound'])
            ->partTemplate('open', 'themes/data/part')
            ->bind();
        static::assertStringContainsString('template-data: bound', $first->open());
        $first->close();
        $second = $helper->form()
            ->templateData(['marker' => 'bound'])
            ->partTemplate('open', 'themes/data/part')
            ->replaceTemplateData([])
            ->bind();
        static::assertStringContainsString('template-data: empty', $second->open());
        $second->close();
    }
}
