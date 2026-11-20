<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Form;

use Cake\Form\FormProtector;
use FormCompose\Configuration\ButtonConfig;
use FormCompose\Test\FormTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/** Checks automatic button unlocks against all successfully rendered protected inputs. */
class ButtonOverlapSecurityTest extends FormTestCase
{
    /** Token generation rejects overlapping names regardless of rendering order. */
    #[DataProvider('overlappingNames')]
    public function testButtonsCannotUnlockProtectedInputs(string $field, string $name): void
    {
        foreach (['button-first', 'field-first'] as $order) {
            $form = $this->helper(attributes: ['formTokenData' => []])->form()->bind();
            $form->open();
            if ($order === 'button-first') {
                $form->submit('Save')->name($name)->render();
            }

            $form->control($field)->type('hidden')->value('42')->render();
            if ($order === 'field-first') {
                $form->submit('Save')->name($name)->render();
            }

            try {
                $form->close();
                static::fail('Expected an overlapping button name to prevent token generation.');
            } catch (InvalidArgumentException $exception) {
                static::assertStringContainsString('cannot overlap protected field paths', $exception->getMessage());
            }
        }
    }

    /** @return array<string, array{string, string}> Names whose path segments overlap. */
    public static function overlappingNames(): array
    {
        return [
            'exact' => ['id', 'id'],
            'nested leaf' => ['record.id', 'id'],
            'nested parent' => ['record.id', 'record'],
            'bracket name' => ['record.id', 'record[id]'],
            'dot name' => ['record.id', 'record.id'],
            'association' => ['records.0.id', 'id'],
            'shared segment' => ['record.id', 'other[id]'],
        ];
    }

    /** Native non-submitting buttons can share field names without changing protection. */
    #[DataProvider('nonSubmittingButtons')]
    public function testNonSubmittingButtonsDoNotParticipateInOverlapValidation(
        ButtonConfig $config,
        string $name,
        string $order,
    ): void {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->bind();
        $html = $form->open();
        if ($order === 'button-first') {
            $html .= $form->submit('Action')->apply($config)->name($name)->render();
        }

        $html .= $form->control('title')->render();
        $html .= $form->control('record.id')->type('hidden')->value('42')->render();
        if ($order === 'field-first') {
            $html .= $form->submit('Action')->apply($config)->name($name)->render();
        }

        $html .= $form->close();
        $xpath = $this->xpath($html);
        $tokens = [];
        foreach (['fields', 'unlocked', 'debug'] as $key) {
            $value = $xpath->evaluate('string(//input[@name="_Token[' . $key . ']"]/@value)');
            static::assertIsString($value);
            $tokens[$key] = $value;
        }

        static::assertSame('', $tokens['unlocked']);
        $payload = ['title' => 'Edited', 'record' => ['id' => '42'], '_Token' => $tokens];
        $session = $helper->getView()->getRequest()->getSession()->id();
        static::assertTrue(new FormProtector()->validate($payload, '/articles/save', $session));
        static::assertFalse(new FormProtector()->validate($payload + ['injected' => '1'], '/articles/save', $session));
        $payload['record']['id'] = '999';
        static::assertFalse(new FormProtector()->validate($payload, '/articles/save', $session));
    }

    /** @return array<string, array{\FormCompose\Configuration\ButtonConfig, string, string}> Inert controls and overlapping names. */
    public static function nonSubmittingButtons(): array
    {
        $cases = [];
        foreach ([
            'reset' => ButtonConfig::builder()->type('reset'),
            'ordinary' => ButtonConfig::builder()->type('button'),
            'disabled submit' => ButtonConfig::builder()->disabled(true),
        ] as $kind => $config) {
            foreach (['title', 'id', '_Token[fields]'] as $name) {
                foreach (['button-first', 'field-first'] as $order) {
                    $cases[$kind . ' ' . $name . ' ' . $order] = [$config, $name, $order];
                }
            }
        }

        return $cases;
    }

    /** Automatic unlocks must also leave the form infrastructure intact. */
    #[DataProvider('infrastructureNames')]
    public function testButtonsCannotUnlockInfrastructure(string $name): void
    {
        $form = $this->helper(attributes: ['formTokenData' => []])->form()->bind();
        $form->open();
        $form->submit('Save')->name($name)->render();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot overlap form infrastructure fields');
        $form->close();
    }

    /** @return array<string, array{string}> Infrastructure names and nested variants. */
    public static function infrastructureNames(): array
    {
        return [
            'method' => ['_method'],
            'CSRF' => ['_csrfToken'],
            'token' => ['_Token[fields]'],
        ];
    }

    /** Legitimate optional buttons coexist with hidden and explicitly unlocked inputs. */
    public function testIndependentButtonsRemainOptionalAndHiddenValuesStayLocked(): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->bind();
        $html = $form->open();
        $html .= $form->control('record.id')->type('hidden')->value('42')->render();
        $html .= $form->control('dynamic')->secure(false)->render();
        $html .= $form->submit('Save')->name('actions[save]')->render();
        $html .= $form->close();
        $xpath = $this->xpath($html);
        $payload = [
            'record' => ['id' => '42'],
            '_Token' => [
                'fields' => $xpath->evaluate('string(//input[@name="_Token[fields]"]/@value)'),
                'unlocked' => $xpath->evaluate('string(//input[@name="_Token[unlocked]"]/@value)'),
                'debug' => $xpath->evaluate('string(//input[@name="_Token[debug]"]/@value)'),
            ],
        ];
        $session = $helper->getView()->getRequest()->getSession()->id();
        static::assertTrue(new FormProtector()->validate($payload, '/articles/save', $session));
        $payload['actions']['save'] = 'yes';
        static::assertTrue(new FormProtector()->validate($payload, '/articles/save', $session));
        $payload['record']['id'] = '999';
        static::assertFalse(new FormProtector()->validate($payload, '/articles/save', $session));
    }

    /** Ordinary unprotected forms do not acquire a mandatory protection policy. */
    public function testUnprotectedFormsCanReuseButtonNames(): void
    {
        $form = $this->helper()->form()->bind();
        $html = $form->open() . $form->control('record.id')->type('hidden')->value('42')->render();
        $html .= $form->submit('Save')->name('id')->render() . $form->close();
        static::assertStringNotContainsString('_Token[', $html);
    }
}
