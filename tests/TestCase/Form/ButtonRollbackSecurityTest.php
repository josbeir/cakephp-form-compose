<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Form;

use Cake\Form\FormProtector;
use FormCompose\Form\Field\FieldSpec;
use FormCompose\Test\Fixture\ButtonThenFailRenderer;
use FormCompose\Test\FormTestCase;
use RuntimeException;

/** Verifies button registrations commit and roll back with their enclosing renders. */
class ButtonRollbackSecurityTest extends FormTestCase
{
    /** Failed custom rendering must not leave optional names in a recovered session. */
    public function testFailedOuterRendererDiscardsItsButtonAndCanBeRetried(): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $renderer = new ButtonThenFailRenderer($helper->getView());
        $form = $helper->form()->renderer($renderer)->bind();
        $renderer->form = $form;
        $html = $form->open() . $form->control('record.id')->type('hidden')->value('42')->render();
        $html .= $form->submit('Existing')->name('existing')->render();
        try {
            $form->control('title')->render();
            static::fail('Expected the outer control renderer to fail.');
        } catch (RuntimeException $runtimeException) {
            static::assertSame('Outer control failed.', $runtimeException->getMessage());
        }

        $renderer->name = 'retained';
        $renderer->fail = false;
        $html .= $form->control('title')->render() . $form->close();
        $payload = ['record' => ['id' => '42'], 'title' => 'Edited', '_Token' => $this->tokens($html)];
        $session = $helper->getView()->getRequest()->getSession()->id();
        static::assertTrue(new FormProtector()->validate($payload, '/articles/save', $session));
        static::assertTrue(new FormProtector()->validate(
            $payload + ['existing' => 'yes', 'retained' => 'yes'],
            '/articles/save',
            $session,
        ));
        static::assertFalse(new FormProtector()->validate($payload + ['discarded' => '1'], '/articles/save', $session));
        $payload['record']['id'] = '999';
        static::assertFalse(new FormProtector()->validate($payload, '/articles/save', $session));
    }

    /** A caught nested failure preserves earlier pending buttons in the successful outer batch. */
    public function testNestedRollbackPreservesTheOuterPendingButton(): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->bind();
        $html = $form->open();
        $html .= $form->controlBatch(['title'], static function (FieldSpec $field) use ($form): string {
            $html = $form->submit('Outer')->name('outer_intent')->render();
            try {
                $form->controlBatch(['inner'], static function (FieldSpec $inner) use ($form): string {
                    $form->control($inner->path)->render();
                    $form->submit('Inner')->name('inner_intent')->render();
                    throw new RuntimeException('Inner batch failed.');
                })->render();
                static::fail('Expected the inner batch to fail.');
            } catch (RuntimeException $runtimeException) {
                static::assertSame('Inner batch failed.', $runtimeException->getMessage());
            }

            return $html . $form->control($field->path)->render();
        })->render();
        $html .= $form->close();
        $payload = ['title' => 'Edited', '_Token' => $this->tokens($html)];
        $session = $helper->getView()->getRequest()->getSession()->id();
        static::assertTrue(new FormProtector()->validate($payload, '/articles/save', $session));
        static::assertTrue(new FormProtector()->validate(
            $payload + ['outer_intent' => 'yes'],
            '/articles/save',
            $session,
        ));
        static::assertFalse(new FormProtector()->validate(
            $payload + ['inner_intent' => '1'],
            '/articles/save',
            $session,
        ));
    }

    /** @return array<string, string> CakePHP protection fields from the recovered form. */
    protected function tokens(string $html): array
    {
        $xpath = $this->xpath($html);
        $tokens = [];
        foreach (['fields', 'unlocked', 'debug'] as $name) {
            $value = $xpath->evaluate('string(//input[@name="_Token[' . $name . ']"]/@value)');
            static::assertIsString($value);
            $tokens[$name] = $value;
        }

        return $tokens;
    }
}
