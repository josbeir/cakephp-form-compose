<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Form;

use Cake\Form\FormProtector;
use Cake\I18n\I18n;
use Cake\I18n\Package;
use Cake\Utility\Hash;
use Cake\View\Form\ArrayContext;
use Dom\Element;
use Dom\NodeList;
use FormCompose\Configuration\ButtonConfig;
use FormCompose\Form\FormSession;
use FormCompose\Rendering\Html;
use FormCompose\Rendering\PhpRenderer;
use FormCompose\Rendering\Theme;
use FormCompose\Test\CompositionTestCase;
use FormCompose\Test\Fixture\Authoring\EmailForm;
use FormCompose\Test\Fixture\Authoring\FailingPostForm;
use FormCompose\Test\Fixture\Authoring\LocalEmailForm;
use FormCompose\Test\Fixture\Authoring\SecureForm;
use FormCompose\Test\Fixture\Composition\PostForm;
use FormCompose\Test\Fixture\FailOnceRenderer;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Verifies convenient rendering preserves session state, extensions, and real protection tokens.
 */
class FormSessionTest extends CompositionTestCase
{
    public function testBoundDefaultsRenderOnceThroughAnExplicitThemeAndRenderer(): void
    {
        $helper = $this->helper();
        $theme = new Theme(layers: ['themes/unified']);
        $form = $helper->form(new ArrayContext(['data' => ['title' => 'Original']]))
            ->theme($theme)
            ->renderer(new PhpRenderer($helper->getView(), $theme))
            ->blueprint(PostForm::class)
            ->attributes(['class' => 'complete'])
            ->submit('Save')
            ->submitConfig(ButtonConfig::builder()->variant('primary'))
            ->bind();
        static::assertCount(2, $this->probe->calls);
        $html = $form->render();
        static::assertCount(2, $this->probe->calls);
        static::assertStringContainsString('value="Original"', $html);
        static::assertStringContainsString('class="complete"', $html);
        static::assertStringContainsString('data-form-shell', $html);
        static::assertStringContainsString('data-primary', $html);
    }

    public function testAnExplicitDefinitionRendersLocallyWithoutReplacingBindingDefaults(): void
    {
        $form = $this->helper()->form()->blueprint(EmailForm::class)->bind();
        $defaults = $form->binding->definition;
        $html = $form->open() . $form->blueprint(LocalEmailForm::class)->render() . $form->close();
        static::assertStringContainsString('Local email', $html);
        static::assertStringContainsString('type="email"', $html);
        static::assertSame($defaults, $form->binding->definition);
        static::assertSame('Default email', $form->field('email')->resolve()->state->config->label);
    }

    public function testSessionCanResolveAnExplicitBlueprint(): void
    {
        $html = $this->helper()
            ->form()
            ->blueprint('composed-post')
            ->submit(false)
            ->bind()
            ->render();
        static::assertStringContainsString('name="author[first_name]"', $html);
        static::assertSame(['post', 'author'], array_column($this->probe->calls, 'name'));
    }

    public function testDefaultSubmitIsTranslatedWhenRenderingRatherThanBinding(): void
    {
        $locale = I18n::getLocale();
        $form = $this->helper()->form()->bind();
        try {
            I18n::setTranslator(
                'default',
                static function (): Package {
                    $package = new Package();
                    $package->setMessages(['Submit' => 'Versturen']);

                    return $package;
                },
                'nl_BE',
            );
            I18n::setLocale('nl_BE');
            static::assertStringContainsString('>Versturen</button>', $form->render());
        } finally {
            I18n::clear();
            I18n::setLocale($locale);
        }
    }

    public function testEmptyFormsAndExplicitButtonLabelsRemainSupported(): void
    {
        $helper = $this->helper();
        $html = $helper->form()->submit(false)->bind()->render();
        static::assertStringContainsString('<form ', $html);
        static::assertStringContainsString('</form>', $html);
        static::assertStringNotContainsString('<button', $html);
        static::assertStringContainsString(
            '>Save &lt;all&gt;</button>',
            $helper->form()->submit('Save <all>')->bind()->render(),
        );
        static::assertStringContainsString(
            '<strong>Save</strong>',
            $helper->form()->submit(new Html('<strong>Save</strong>'))->bind()->render(),
        );
        static::assertStringContainsString('></button>', $helper->form()->submit('')->bind()->render());
    }

    public function testCompleteFormsPreserveMultipartCsrfMethodAndSubmissionProtection(): void
    {
        $csrf = bin2hex(random_bytes(16));
        $helper = $this->helper(attributes: ['csrfToken' => $csrf, 'formTokenData' => []]);
        $html = $helper->form(new ArrayContext(['data' => ['inputs' => ['tags' => ['b'], 'status' => 'a']]]))
            ->blueprint(SecureForm::class)
            ->multipart()
            ->method('patch')
            ->url('/articles/save')
            ->submitConfig(
                ButtonConfig::builder()->name('save')->value('yes'),
            )
            ->render();
        $xpath = $this->xpath($html);
        static::assertTrue($xpath->evaluate('boolean(//form[@enctype="multipart/form-data"])'));
        static::assertTrue($xpath->evaluate('boolean(//input[@name="_method"][@value="PATCH"])'));
        static::assertTrue($xpath->evaluate('boolean(//input[@name="_csrfToken"][@value="' . $csrf . '"])'));
        static::assertTrue($xpath->evaluate('boolean(//input[@type="file"][@name="inputs[attachment]"])'));
        static::assertTrue($xpath->evaluate('boolean(//select[@name="inputs[tags][]"]/option[@value="b"][@selected])'));
        $data = [
            'author' => ['id' => '7'],
            'inputs' => ['tags' => ['b'], 'status' => 'a', 'attachment' => ''],
            '_Token' => $this->tokens($html),
        ];
        $session = $helper->getView()->getRequest()->getSession()->id();
        static::assertTrue(new FormProtector()->validate($data, '/articles/save', $session));
        static::assertTrue(new FormProtector()->validate($data + ['save' => 'yes'], '/articles/save', $session));
        $data['author']['id'] = '8';
        static::assertFalse(new FormProtector()->validate($data, '/articles/save', $session));
        $data['author']['id'] = '7';
        $data['inputs']['disabled'] = 'injected';
        static::assertFalse(new FormProtector()->validate($data, '/articles/save', $session));
        static::assertSame(1, substr_count($html, 'name="_Token[fields]"'));
    }

    public function testCompleteRenderingRejectsOpenAndPreviouslyRenderedSessions(): void
    {
        $form = $this->helper()->form()->bind();
        $form->open();
        $this->assertCannotRenderAgain($form);
        $form->close();
        $this->assertCannotRenderAgain($form);
        $complete = $this->helper()->form()->bind();
        $complete->render();
        $this->assertCannotRenderAgain($complete);
    }

    public function testUnlockedCollectionsAllowRowChangesWithoutUnlockingOtherFieldsOrForms(): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->url('/team')->bind();
        $html = $form->open();
        $form->unlock('members');
        $html .= $form->control('members.0.id')->type('hidden')->value('1')->render();
        $html .= $form->control('team_id')->type('hidden')->value('7')->render();
        $html .= $form->close();
        $session = $helper->getView()->getRequest()->getSession()->id();
        $data = ['team_id' => '7', '_Token' => $this->tokens($html)];
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        $data['members'] = [['id' => '2'], ['id' => '3']];
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        static::assertFalse(new FormProtector()->validate($data + ['injected' => 'extra'], '/team', $session));
        $data['team_id'] = '8';
        static::assertFalse(new FormProtector()->validate($data, '/team', $session));

        $other = $helper->form()->url('/team')->bind();
        $otherHtml =
            $other->open() . $other->control('members.0.id')->type('hidden')->value('1')->render() . $other->close();
        $otherData = ['members' => [['id' => '2']], '_Token' => $this->tokens($otherHtml)];
        static::assertFalse(new FormProtector()->validate($otherData, '/team', $session));
    }

    #[DataProvider('collectionUnlockOrders')]
    public function testCollectionUnlocksApplyRegardlessOfRenderingOrder(string $path, string $unlockAt): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->url('/team')->bind();
        $html = $form->open();
        if ($unlockAt === 'open') {
            $form->unlock($path);
        }

        $html .= $form->control($path . '.0.name')->render();
        $html .= $form->control($path . '.0.id')
            ->type('hidden')
            ->value('1')
            ->render();
        if ($unlockAt === 'inputs') {
            $form->unlock($path);
        }

        $html .= $form->control($path . '.0.email')->render();
        $html .= $form->control('team_id')->type('hidden')->value('7')->render();
        $html .= $form->control('memberships')->type('hidden')->value('protected')->render();
        $html .= $form->close();
        $session = $helper->getView()->getRequest()->getSession()->id();
        $data = ['team_id' => '7', 'memberships' => 'protected', '_Token' => $this->tokens($html)];
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        $unchanged = Hash::insert($data, $path, [['id' => '1', 'name' => 'Alice', 'email' => 'a@example.test']]);
        static::assertTrue(new FormProtector()->validate($unchanged, '/team', $session));
        $changed = Hash::insert($data, $path, [['id' => '2', 'name' => 'Bob'], ['id' => '3', 'name' => 'Carol']]);
        static::assertTrue(new FormProtector()->validate($changed, '/team', $session));
        static::assertFalse(new FormProtector()->validate($changed + ['injected' => 'extra'], '/team', $session));
        static::assertFalse(new FormProtector()->validate(
            array_replace($changed, ['team_id' => '8']),
            '/team',
            $session,
        ));
        static::assertFalse(new FormProtector()->validate(
            array_replace($changed, ['memberships' => 'tampered']),
            '/team',
            $session,
        ));
    }

    /**
     * Cover root and nested collections on either side of successful input rendering.
     *
     * @return array<string, array{string, string}>
     */
    public static function collectionUnlockOrders(): array
    {
        return [
            'root before rendering' => ['members', 'open'],
            'root after rendering' => ['members', 'inputs'],
            'nested before rendering' => ['team.members', 'open'],
            'nested after rendering' => ['team.members', 'inputs'],
        ];
    }

    public function testUnlockingARenderedHiddenFieldPreservesItsSiblings(): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->url('/team')->bind();
        $html = $form->open();
        $html .= $form->control('members.0.id')->type('hidden')->value('1')->render();
        $html .= $form->control('members.0.name')->render();
        $form->unlock('members.0.id');
        $html .= $form->close();
        $session = $helper->getView()->getRequest()->getSession()->id();
        $data = ['members' => [['id' => '2', 'name' => 'Alice']], '_Token' => $this->tokens($html)];
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        unset($data['members'][0]['id']);
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        unset($data['members'][0]['name']);
        static::assertFalse(new FormProtector()->validate($data, '/team', $session));
    }

    public function testCollectionCanBeUnlockedAfterAFailedClosing(): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $renderer = new FailOnceRenderer(new PhpRenderer($helper->getView()), 'form', 'close');
        $form = $helper->form()->url('/team')->renderer($renderer)->bind();
        $html = $form->open();
        $html .= $form->control('members.0.name')->render();
        $html .= $form->control('members.0.id')->type('hidden')->value('1')->render();
        $html .= $form->control('team_id')->type('hidden')->value('7')->render();
        try {
            $form->close();
            static::fail('Expected the closing template to fail.');
        } catch (RuntimeException $runtimeException) {
            static::assertSame('Template failed', $runtimeException->getMessage());
        }

        $form->unlock('members');
        $html .= $form->close();
        $session = $helper->getView()->getRequest()->getSession()->id();
        $data = ['team_id' => '7', '_Token' => $this->tokens($html)];
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        $data['members'] = [['id' => '2', 'name' => 'Bob']];
        static::assertTrue(new FormProtector()->validate($data, '/team', $session));
        $data['team_id'] = '8';
        static::assertFalse(new FormProtector()->validate($data, '/team', $session));
    }

    public function testUnlockRequiresAnOpenSession(): void
    {
        $form = $this->helper()->bind();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected an open form; current phase is Pending.');
        $form->unlock('members');
    }

    public function testUnlockRejectsAClosedSession(): void
    {
        $form = $this->helper()->bind();
        $form->open();
        $form->close();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected an open form; current phase is Closed.');
        $form->unlock('members');
    }

    public function testUnlockDoesNotEnableProtectionOnAnUnprotectedForm(): void
    {
        $form = $this->helper()->bind();
        $html = $form->open();
        $form->unlock('members');
        $html .= $form->control('members.0.name')->render();
        $html .= $form->close();
        static::assertStringNotContainsString('_Token[', $html);
        static::assertStringContainsString('name="members[0][name]"', $html);
    }

    public function testFailedCompleteRenderingDoesNotEchoOrAutomaticallyClose(): void
    {
        $helper = $this->helper();
        $form = $helper->form()->blueprint(FailingPostForm::class)->bind();
        ob_start();
        try {
            $form->render();
            static::fail('Expected the layout to fail.');
        } catch (RuntimeException $runtimeException) {
            static::assertSame('Definition layout failed.', $runtimeException->getMessage());
            static::assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
        }

        static::assertStringContainsString('name="title"', $form->control('title')->render());
        $form->close();
        static::assertStringContainsString('</form>', $helper->form()->blueprint(PostForm::class)->render());
    }

    /**
     * Verify the fresh-session requirement without changing the original lifecycle.
     */
    protected function assertCannotRenderAgain(FormSession $form): void
    {
        try {
            $form->render();
            static::fail('Expected opening a session twice to fail.');
        } catch (LogicException $logicException) {
            static::assertSame('A form session can only be opened once.', $logicException->getMessage());
        }
    }

    /**
     * Extract real Cake tokens for validating successful and tampered submissions.
     *
     * @return array<string, string>
     */
    protected function tokens(string $html): array
    {
        /** @var NodeList<Element>|false $nodes */
        $nodes = $this->xpath($html)->query('//input[starts-with(@name, "_Token[")]');
        static::assertInstanceOf(NodeList::class, $nodes);
        $tokens = [];
        foreach ($nodes as $node) {
            $name = $node->getAttribute('name');
            $value = $node->getAttribute('value');
            static::assertNotNull($name);
            static::assertNotNull($value);
            $tokens[substr($name, 7, -1)] = $value;
        }

        return $tokens;
    }
}
