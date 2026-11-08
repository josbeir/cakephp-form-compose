<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Rendering;

use Cake\View\Form\ArrayContext;
use FormCompose\Configuration\SummaryConfig;
use FormCompose\Test\Fixture\Authoring\CommentsForm;
use FormCompose\Test\Fixture\Authoring\EmptyForm;
use FormCompose\Test\Fixture\Authoring\TitleForm;
use FormCompose\Test\Fixture\Summary\CustomHelper;
use FormCompose\Test\FormTestCase;
use function Cake\View\html;

/**
 * Verifies the rendered-field scope, template API, and complete-form placement.
 */
class ErrorSummaryTest extends FormTestCase
{
    public function testOnlyRenderedEligibleFieldsAppearInRenderOrder(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext([
                'errors' => [
                    'first' => ['invalid' => 'First error'],
                    'second' => ['one' => 'Second one', 'two' => 'Second two'],
                    'omitted' => ['invalid' => 'Omitted error'],
                    'suppressed' => ['invalid' => 'Suppressed error'],
                ],
            ]))
            ->bind();
        $form->field('omitted')->resolve()->attributes();
        $form->open();
        static::assertSame('', $form->errorSummary()->render());
        $body = $form->control('second')->label('Second label')->render();
        $body .= $form->control('first')->id('custom:first')->label(false)->render();
        $body .= $form->control('suppressed')->errors(false)->render();
        $summary = $form->errorSummary()->render();
        static::assertStringContainsString('Second label: Second one', $summary);
        static::assertStringContainsString('Second label: Second two', $summary);
        static::assertStringContainsString('First: First error', $summary);
        static::assertStringContainsString('href="#custom%3Afirst"', $summary);
        static::assertStringNotContainsString('Omitted error', $summary);
        static::assertStringNotContainsString('Suppressed error', $summary);
        static::assertLessThan(strpos($summary, 'First error'), strpos($summary, 'Second one'));
        static::assertStringContainsString('id="custom:first"', $body);
        $form->close();
    }

    public function testLabelsTitlesAndMessagesEscapeUnlessExplicitlyTrusted(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext([
                'errors' => ['title' => ['invalid' => '<script>bad</script>'], 'trusted' => ['invalid' => 'Invalid']],
            ]))
            ->bind();
        $form->open();
        $form->control('title')->label('<Title>')->render();
        $form->control('trusted')
            ->label(html('<em>Trusted</em>'))
            ->render();

        $summary = $form->errorSummary()->title('<Errors>')->render();
        static::assertStringContainsString('&lt;Errors&gt;', $summary);
        static::assertStringContainsString('&lt;Title&gt;: &lt;script&gt;bad&lt;/script&gt;', $summary);
        static::assertStringContainsString('<em>Trusted</em>: Invalid', $summary);
        static::assertStringNotContainsString('&amp;lt;', $summary);
        $form->close();
    }

    public function testHiddenAndDisabledFieldsRemainVisibleAsUnlinkedMessages(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext([
                'errors' => ['id' => ['invalid' => 'Invalid identifier'], 'locked' => ['invalid' => 'Locked value']],
            ]))
            ->bind();
        $form->open();
        $form->control('id')->type('hidden')->render();
        $form->control('locked')->disabled(true)->render();

        $summary = $form->errorSummary()->render();
        static::assertStringContainsString('Invalid identifier', $summary);
        static::assertStringContainsString('Locked value', $summary);
        static::assertStringNotContainsString('<a ', $summary);
        $form->close();
    }

    public function testRepeatedRowsAndCollectionErrorsAreIncludedWithoutDuplicatingChildErrors(): void
    {
        $context = new ArrayContext([
            'defaults' => ['comments' => [2 => ['body' => 'Value']]],
            'errors' => [
                'comments' => ['count' => 'Add another', 2 => ['body' => ['invalid' => 'Fix row two']]],
            ],
        ]);
        $form = $this->helper()->form($context)->blueprint(CommentsForm::class)->bind();
        $form->open();
        $form->blueprint()->render();

        $summary = $form->errorSummary()->render();
        static::assertSame(1, substr_count($summary, 'Fix row two'));
        static::assertSame(1, substr_count($summary, 'Add another'));
        static::assertStringContainsString('href="#form-compose-1-comments.2.body"', $summary);
        static::assertStringContainsString('Comments: Add another', $summary);
        $form->close();
    }

    public function testEmptyCollectionsCanContributeTheirOwnError(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext(['errors' => ['comments' => ['count' => 'At least one']]]))
            ->bind();
        $form->open();
        $form->embedMany(path: 'comments', blueprint: EmptyForm::class)
            ->label(html('<strong>Comments</strong>'))
            ->render();
        static::assertStringContainsString('<strong>Comments</strong>: At least one', $form->errorSummary()->render());
        $form->close();
    }

    public function testCompleteFormSummaryIsOptInAndAppearsBeforeControls(): void
    {
        $context = new ArrayContext(['errors' => ['title' => ['invalid' => 'Fix title']]]);
        $helper = $this->helper();
        $ordinary = $helper->form($context)->blueprint(TitleForm::class)->render();
        static::assertStringNotContainsString('<section', $ordinary);
        $html = $helper->form($context)
            ->blueprint(TitleForm::class)
            ->errorSummary(true)
            ->render();
        static::assertLessThan(strpos($html, 'name="title"'), strpos($html, '<section'));
        static::assertStringNotContainsString('errorSummary=', $html);
        static::assertStringContainsString('aria-labelledby="form-compose-2-error-summary-title"', $html);
    }

    public function testSummaryOptionsSelectCustomTemplatesAndAttributeDefaults(): void
    {
        $html = $this->helper()
            ->form(new ArrayContext(['errors' => ['title' => ['invalid' => 'Fix title']]]))
            ->blueprint(TitleForm::class)
            ->errorSummary(
                SummaryConfig::builder()
                    ->partTemplate('control', 'summary/custom')
                    ->title(html('<em>Errors</em>'))
                    ->attributes(['id' => 'validation', 'class' => 'notice'])
                    ->partAttributes('title', ['id' => 'validation-heading'])
                    ->templateData(['intro' => 'Check <these>']),
            )
            ->render();
        static::assertStringContainsString(
            '<aside id="validation" class="notice" aria-labelledby="validation-heading">',
            $html,
        );
        static::assertStringContainsString('<em>Errors</em>', $html);
        static::assertStringContainsString('Check &lt;these&gt;', $html);
    }

    public function testAnInjectedSummaryRendererWorksThroughTheHelperShortcut(): void
    {
        $helper = new CustomHelper($this->helper()->getView());
        $html = $helper->form(new ArrayContext(['errors' => ['title' => ['invalid' => 'Fix title']]]))
            ->blueprint(TitleForm::class)
            ->errorSummary(true)
            ->render();
        static::assertStringContainsString('class="custom-summary"', $html);
    }

    public function testHandwrittenInputRegistrationAddsFeedbackWithoutRenderingAgain(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext([
                'errors' => ['title' => ['invalid' => 'Fix handwritten title']],
            ]))
            ->bind();
        $field = $form->field('title')->resolve();
        $form->open();
        $input = '<input' . (string)$field->attributes() . '>';
        $field->register();
        $input .= $field->errors()->render();
        $summary = $form->errorSummary()->render();
        static::assertStringContainsString('id="form-compose-1-title"', $input);
        static::assertSame(1, substr_count($summary, 'Fix handwritten title'));
        static::assertStringNotContainsString('<a ', $summary);
        $form->close();
    }

    public function testSuppressedCollectionErrorsNeverEnterTheSummary(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext(['errors' => ['comments' => ['count' => 'Suppressed']]]))
            ->bind();
        $form->open();
        $form->embedMany(path: 'comments', blueprint: EmptyForm::class)->errors(false)->render();
        static::assertSame('', $form->errorSummary()->render());
        $form->close();
    }
}
