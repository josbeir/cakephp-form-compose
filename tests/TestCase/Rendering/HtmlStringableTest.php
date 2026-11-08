<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Rendering;

use Cake\View\Form\ArrayContext;
use Cake\View\HtmlStringable;
use FormCompose\Configuration\ChoiceConfig;
use FormCompose\Configuration\ChoiceGroupConfig;
use FormCompose\Test\Fixture\Authoring\EmptyForm;
use FormCompose\Test\Fixture\Authoring\TitleForm;
use FormCompose\Test\Fixture\Rendering\CustomHtml;
use FormCompose\Test\FormTestCase;

/**
 * Verifies application-defined trusted content through public form entry points.
 */
class HtmlStringableTest extends FormTestCase
{
    public function testDeferredFieldTextAndSummaryPreserveTheHtmlContract(): void
    {
        $label = new CustomHtml('<strong>Title</strong>');
        $help = new CustomHtml('<em>Help</em>');
        $form = $this->helper()
            ->form(new ArrayContext(['errors' => ['title' => ['invalid' => '<Invalid>']]]))
            ->bind();
        $field = $form->field('title')
            ->label(static fn(): HtmlStringable => $label)
            ->help(static fn(): HtmlStringable => $help)
            ->resolve();
        static::assertSame($label, $field->state->config->label);
        static::assertSame($help, $field->state->config->help);
        $form->open();
        $html = $field->control();
        static::assertStringContainsString('<strong>Title</strong></label>', $html);
        static::assertStringContainsString('<em>Help</em>', $html);
        static::assertStringContainsString('&lt;Invalid&gt;', $html);
        $summary = $form->errorSummary()->title(new CustomHtml('<em>Errors</em>'))->render();
        static::assertStringContainsString('<em>Errors</em></h2>', $summary);
        static::assertStringContainsString('<strong>Title</strong>: &lt;Invalid&gt;', $summary);
        $form->close();
    }

    public function testChoiceLabelsAndGroupsPreserveCustomTrustedContent(): void
    {
        $form = $this->helper()->form()->bind();
        $form->open();
        foreach (['select', 'radio', 'multicheckbox'] as $type) {
            $html = $form->control('choices_' . $type)
                ->type($type)
                ->choices([
                    'simple' => new CustomHtml('<b>Simple</b>'),
                    new ChoiceGroupConfig(new CustomHtml('<i>Group</i>'), [
                        new ChoiceConfig('"value"', new CustomHtml('<em>Configured</em>')),
                        'plain' => '<Plain>',
                    ]),
                ])
                ->render();
            static::assertStringContainsString('<b>Simple</b>', $html);
            static::assertStringContainsString('<em>Configured</em>', $html);
            static::assertStringContainsString('&lt;Plain&gt;', $html);
            static::assertStringContainsString('value="&quot;value&quot;"', $html);
            static::assertStringContainsString(
                $type === 'select' ? 'label="&lt;i&gt;Group&lt;/i&gt;"' : '<legend><i>Group</i></legend>',
                $html,
            );
        }

        $form->close();
    }

    public function testCustomContentWorksInCompleteFormsActionsAndRepeatedEmbeds(): void
    {
        $helper = $this->helper();
        $label = new CustomHtml('<strong>Save</strong>');
        static::assertStringContainsString(
            '<strong>Save</strong></button>',
            $helper->form()->blueprint(TitleForm::class)->submit($label)->render(),
        );
        static::assertStringContainsString(
            '<strong>Save</strong></button>',
            $helper->postButton($label, '/save')->render(),
        );
        static::assertStringContainsString('<strong>Save</strong></a>', $helper->postLink($label, '/save')->render());

        $form = $helper->form(new ArrayContext(['errors' => ['comments' => ['count' => '<Required>']]]))->bind();
        $form->open();
        $form->embedMany(path: 'comments', blueprint: EmptyForm::class)->label($label)->render();
        static::assertStringContainsString('<strong>Save</strong>: &lt;Required&gt;', $form->errorSummary()->render());
        $form->close();
    }

    public function testTrustedObjectsRemainEscapedInAttributesAndInputValues(): void
    {
        $form = $this->helper()->form()->bind();
        $form->open();
        foreach (['text', 'textarea'] as $type) {
            $html = $form->control('value_' . $type)
                ->type($type)
                ->value(new CustomHtml('</textarea><script>bad</script>'))
                ->attributes(['title' => new CustomHtml('"<Title>')])
                ->render();
            static::assertStringContainsString('title="&quot;&lt;Title&gt;"', $html);
            static::assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;bad&lt;/script&gt;', $html);
            static::assertStringNotContainsString('<script>', $html);
        }

        $form->close();
    }
}
