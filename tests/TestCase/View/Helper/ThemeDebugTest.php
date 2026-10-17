<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\View\Helper;

use Cake\Core\Configure;
use Cake\Form\FormProtector;
use Cake\View\Form\ArrayContext;
use FormCompose\Rendering\PhpRenderer;
use FormCompose\Test\Fixture\RecordingRenderer;
use FormCompose\Test\Fixture\Repeat\PostForm;
use FormCompose\Test\Fixture\Widget\DateRangeType;
use FormCompose\Test\FormTestCase;
use RuntimeException;

/**
 * Verifies helper debug configuration without changing markup or form bookkeeping.
 */
class ThemeDebugTest extends FormTestCase
{
    public function testDebugCommentsAreOptInAndPreserveMarkupApartFromInterElementWhitespace(): void
    {
        $outputs = [];
        foreach ([false, true] as $enabled) {
            $helper = $this->helper();
            $helper->setConfig('themeDebug', $enabled);
            $form = $helper->bind(null, ['id' => 'comparison', 'types' => ['range' => new DateRangeType()]]);
            $outputs[] =
                $form->open()
                . $form->control('title', ['value' => '<Secret>', 'help' => 'Private help'])
                . $form->control('period', ['type' => 'range'])
                . $form->control('notes', ['type' => 'textarea', 'value' => 'Text <content>'])
                . $form->submit('Save')
                . $form->close();
        }

        static::assertStringNotContainsString('FormCompose BEGIN', $outputs[0]);
        static::assertStringContainsString('part: native', $outputs[1]);
        static::assertStringContainsString('part: composite', $outputs[1]);
        $withoutComments = preg_replace('/<!-- FormCompose .*?-->/s', '', $outputs[1]);
        static::assertIsString($withoutComments);
        static::assertSame(
            preg_replace('/>\s+</', '><', trim($outputs[0])),
            preg_replace('/>\s+</', '><', trim($withoutComments)),
        );
        $comments = [];
        preg_match_all('/<!-- FormCompose .*?-->/s', $outputs[1], $comments);
        $trace = implode('', $comments[0]);
        static::assertStringNotContainsString('Secret', $trace);
        static::assertStringNotContainsString('Private help', $trace);
        static::assertStringNotContainsString('content', $trace);
    }

    public function testDebugSettingIsCapturedByBindingsAndLocalToEachHelper(): void
    {
        $helper = $this->helper();
        $plain = $helper->bind();
        $helper->setConfig('themeDebug', true);
        $traced = $helper->bind();
        $helper->setConfig('themeDebug', false);
        static::assertStringNotContainsString('FormCompose BEGIN', $plain->render());
        static::assertStringContainsString('FormCompose BEGIN', $traced->render());
        static::assertStringNotContainsString('FormCompose BEGIN', $helper->bind()->render());
        static::assertStringNotContainsString('FormCompose BEGIN', $this->helper()->bind()->render());
    }

    public function testSuppliedRenderersKeepTheirOwnDebugConfiguration(): void
    {
        $helper = $this->helper();
        $helper->setConfig('themeDebug', true);

        $plain = $helper->bind(renderer: new RecordingRenderer(new PhpRenderer($helper->getView())));
        static::assertStringNotContainsString('FormCompose BEGIN', $plain->render());
        $helper->setConfig('themeDebug', false);
        $traced = $helper->bind(renderer: new RecordingRenderer(new PhpRenderer($helper->getView(), themeDebug: true)));
        static::assertStringContainsString('FormCompose BEGIN', $traced->render());
    }

    public function testCompleteBlueprintsAndRepeatedRowsShareTheDebugRenderer(): void
    {
        $helper = $this->helper();
        $helper->setConfig('themeDebug', true);

        $html = $helper->render(PostForm::class, new ArrayContext([
            'defaults' => ['comments' => [2 => ['id' => 12, 'body' => 'Comment value']]],
        ]));
        static::assertStringContainsString('scope: blueprint', $html);
        static::assertStringContainsString('scope: embed-many', $html);
        static::assertStringContainsString('field: comments.2.body', $html);
        static::assertStringContainsString('part: native', $html);
        static::assertSame(
            'Comment value',
            $this->xpath($html)->evaluate('string(//textarea[@name="comments[2][body]"])'),
        );
    }

    public function testStandaloneActionsUseTheCurrentHelperDebugSetting(): void
    {
        $helper = $this->helper();
        $helper->setConfig('themeDebug', true);

        $button = $helper->postButton('Delete', '/delete');
        static::assertStringContainsString("scope: form\npart: open", $button);
        static::assertStringContainsString('scope: button', $button);
        $link = $helper->postLink('Delete', '/delete');
        static::assertStringContainsString('scope: action', $link);
        static::assertStringContainsString('scope: form', $link);
        $helper->setConfig('themeDebug', false);
        static::assertStringNotContainsString('FormCompose BEGIN', $helper->postButton('Delete', '/delete'));
        static::assertStringNotContainsString('FormCompose BEGIN', $helper->postLink('Delete', '/delete'));
    }

    public function testProtectedInputsStillValidateAndSecretsAreAbsentFromComments(): void
    {
        $csrf = bin2hex(random_bytes(16));
        $helper = $this->helper(attributes: ['csrfToken' => $csrf, 'formTokenData' => []]);
        $helper->setConfig('themeDebug', true);

        $form = $helper->bind(null, ['url' => '/articles/save']);
        $html =
            $form->open(['method' => 'patch'])
            . $form->control('id', ['type' => 'hidden', 'value' => 'locked-private-value'])
            . $form->control('title', ['value' => 'Submitted title'])
            . $form->control('ignored', ['disabled' => true])
            . $form->close();
        $xpath = $this->xpath($html);
        $fields = $xpath->evaluate('string(//input[@name="_Token[fields]"]/@value)');
        $unlocked = $xpath->evaluate('string(//input[@name="_Token[unlocked]"]/@value)');
        $debug = $xpath->evaluate('string(//input[@name="_Token[debug]"]/@value)');
        static::assertIsString($fields);
        static::assertIsString($unlocked);
        static::assertIsString($debug);
        $payload = [
            'id' => 'locked-private-value',
            'title' => 'Changed',
            '_Token' => [
                'fields' => $fields,
                'unlocked' => $unlocked,
                'debug' => $debug,
            ],
        ];
        $sessionId = $helper->getView()->getRequest()->getSession()->id();
        static::assertTrue(new FormProtector()->validate($payload, '/articles/save', $sessionId));
        static::assertFalse(new FormProtector()->validate(
            array_replace($payload, ['id' => 'tampered']),
            '/articles/save',
            $sessionId,
        ));
        static::assertSame('PATCH', $xpath->evaluate('string(//input[@name="_method"]/@value)'));
        static::assertSame($csrf, $xpath->evaluate('string(//input[@name="_csrfToken"]/@value)'));
        $comments = [];
        preg_match_all('/<!-- FormCompose .*?-->/s', $html, $comments);
        $trace = implode('', $comments[0]);
        foreach ([
            ['_csrfToken',       'CSRF protection'],
            ['_method',          'HTTP method override'],
            ['_Token[fields]',   'Form protection signature'],
            ['_Token[unlocked]', 'Form protection unlocked fields'],
            ['_Token[debug]',    'Form protection diagnostics'],
        ] as [$name, $purpose]) {
            static::assertStringContainsString(
                '<!-- FormCompose internal hidden field: ' . $name . ' (' . $purpose . ') -->',
                $trace,
            );
        }

        $internalComments = [];
        preg_match_all('/<!-- FormCompose internal hidden field:.*?-->/', $trace, $internalComments);
        static::assertCount(5, $internalComments[0]);
        foreach ($internalComments[0] as $comment) {
            static::assertStringNotContainsString('lookup:', $comment);
            static::assertStringNotContainsString('element:', $comment);
        }

        static::assertStringNotContainsString($csrf, $trace);
        static::assertStringNotContainsString('locked-private-value', $trace);
        static::assertStringNotContainsString('Submitted title', $trace);
        static::assertStringNotContainsString($fields, $trace);
    }

    public function testThemeDebugCanBeExplicitlyEnabledIndependentlyOfCakeDebug(): void
    {
        /** @var mixed $cakeDebug */
        $cakeDebug = Configure::read('debug');
        Configure::write('debug', false);
        try {
            $helper = $this->helper();
            static::assertStringNotContainsString('FormCompose BEGIN', $helper->bind()->render());
            $helper->setConfig('themeDebug', true);
            static::assertStringContainsString('FormCompose BEGIN', $helper->bind()->render());
        } finally {
            Configure::write('debug', $cakeDebug);
        }
    }

    public function testFailedCompositeRenderingRollsBackInputsAndDoesNotPolluteTheRetryTrace(): void
    {
        $helper = $this->helper();
        $helper->setConfig('themeDebug', true);

        $form = $helper->bind(null, ['types' => ['range' => new DateRangeType()]]);
        $opening = $form->open();
        $this->expectOutputString('');
        try {
            $form->control('period', ['type' => 'range', 'templates' => ['composite' => 'widgets/failure']]);
            static::fail('Expected the composite template to throw.');
        } catch (RuntimeException $runtimeException) {
            static::assertSame('Composite layout failed', $runtimeException->getMessage());
        }

        $html = $opening . $form->control('period', ['type' => 'range']) . $form->close();
        static::assertStringNotContainsString('widgets/failure', $html);
        static::assertMatchesRegularExpression('/^<!-- FormCompose BEGIN\nscope: field\npart: control/m', $html);
        static::assertSame(1.0, $this->xpath($html)->evaluate('count(//input[@name="period[start]"])'));
        static::assertSame(1.0, $this->xpath($html)->evaluate('count(//input[@name="period[end]"])'));
    }
}
