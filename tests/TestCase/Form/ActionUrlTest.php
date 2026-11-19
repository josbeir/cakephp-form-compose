<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Form;

use Cake\Form\FormProtector;
use Cake\Http\ServerRequestFactory;
use Cake\View\View;
use FormCompose\Test\FormTestCase;
use FormCompose\View\Helper\FormHelper;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/** Rejects unroutable action targets before opening or signing a form. */
class ActionUrlTest extends FormTestCase
{
    /** Default actions remain local even when an incoming path resembles a host. */
    #[DataProvider('requestPaths')]
    public function testDefaultActionsStayOnTheCurrentOrigin(string $path, ?string $expected): void
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => $path . '?next=%2Fhome',
            'QUERY_STRING' => 'next=%2Fhome',
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.test',
        ])->withAttribute('formTokenData', [])->withAttribute('csrfToken', 'csrf-secret');
        $helper = new FormHelper(new View($request));
        $builder = $helper->form();
        if ($expected === null) {
            $builder = $builder->url(false);
        }

        $form = $builder->bind();
        $html = $form->open() . $form->control('record.id')->type('hidden')->value('42')->render() . $form->close();
        $xpath = $this->xpath($html);
        static::assertSame(
            $expected === null ? '' : $expected . '?next=%2Fhome',
            $xpath->evaluate('string(//form/@action)'),
        );
        static::assertSame('csrf-secret', $xpath->evaluate('string(//input[@name="_csrfToken"]/@value)'));
        $payload = [
            'record' => ['id' => '42'],
            '_Token' => [
                'fields' => $xpath->evaluate('string(//input[@name="_Token[fields]"]/@value)'),
                'unlocked' => $xpath->evaluate('string(//input[@name="_Token[unlocked]"]/@value)'),
                'debug' => $xpath->evaluate('string(//input[@name="_Token[debug]"]/@value)'),
            ],
        ];
        $session = $request->getSession()->id();
        static::assertTrue(new FormProtector()->validate($payload, ($expected ?? $path) . '?next=%2Fhome', $session));
        $payload['record']['id'] = '999';
        static::assertFalse(new FormProtector()->validate($payload, ($expected ?? $path) . '?next=%2Fhome', $session));
    }

    /** @return array<string, array{string, ?string}> Incoming paths and browser submission targets. */
    public static function requestPaths(): array
    {
        return [
            'ordinary' => ['/articles/save', '/articles/save'],
            'network path' => ['//evil.example/login', '/evil.example/login'],
            'extra slashes' => ['///evil.example/login', '/evil.example/login'],
            'backslash' => ['/\\evil.example/login', '/%5Cevil.example/login'],
            'omitted action' => ['//evil.example/login', null],
        ];
    }

    public function testAnInvalidExternalPortCannotBecomeASignedFormAction(): void
    {
        $form = $this->helper()->form()->url('http://example.test:99999')->bind();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid form action URL.');
        $form->open();
    }
}
