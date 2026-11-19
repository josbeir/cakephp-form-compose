<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Form;

use Cake\Form\FormProtector;
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
