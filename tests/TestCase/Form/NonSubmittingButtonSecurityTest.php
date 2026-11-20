<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Form;

use Cake\Form\FormProtector;
use FormCompose\Configuration\ButtonConfig;
use FormCompose\Test\FormTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Verifies browser-inert buttons cannot weaken the form's accepted submission names. */
class NonSubmittingButtonSecurityTest extends FormTestCase
{
    /** Only buttons the browser can submit may introduce optional names. */
    #[DataProvider('buttonConfigurations')]
    public function testOnlySubmittingButtonsUnlockTheirNames(ButtonConfig $config, string $acceptance): void
    {
        $helper = $this->helper(attributes: ['formTokenData' => []]);
        $form = $helper->form()->bind();
        $html = $form->open() . $form->control('record.id')->type('hidden')->value('42')->render();
        $html .= $form->submit('Save')->apply($config)->name('is_admin')->render() . $form->close();
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
        static::assertSame(
            $acceptance === 'optional',
            new FormProtector()->validate($payload + ['is_admin' => '1'], '/articles/save', $session),
        );
        $payload['record']['id'] = '999';
        static::assertFalse(new FormProtector()->validate($payload, '/articles/save', $session));
    }

    /** @return array<string, array{\FormCompose\Configuration\ButtonConfig, string}> Native button states. */
    public static function buttonConfigurations(): array
    {
        return [
            'disabled submit' => [ButtonConfig::builder()->disabled(true), 'rejected'],
            'ordinary button' => [ButtonConfig::builder()->type('button'), 'rejected'],
            'reset' => [ButtonConfig::builder()->type('reset'), 'rejected'],
            'enabled submit' => [ButtonConfig::builder()->disabled(false), 'optional'],
            'invalid type defaults to submit' => [ButtonConfig::builder()->type('unknown'), 'optional'],
        ];
    }
}
