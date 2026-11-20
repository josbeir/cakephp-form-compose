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

/** Keeps default submissions on the current document and validates explicit action targets. */
class ActionUrlTest extends FormTestCase
{
    /** Default actions remain local even when an incoming path resembles a host. */
    #[DataProvider('requestPaths')]
    public function testDefaultActionsStayOnTheCurrentOrigin(string $path, string $expected, string $mode): void
    {
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => $path . '?next=%2Fhome',
            'QUERY_STRING' => 'next=%2Fhome',
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.test',
        ])->withAttribute('formTokenData', [])->withAttribute('csrfToken', 'csrf-secret');
        $helper = new FormHelper(new View($request));
        $builder = $helper->form();
        if ($mode === 'omitted') {
            $builder = $builder->url(false);
        }

        $form = $builder->bind();
        $html = $form->open() . $form->control('record.id')->type('hidden')->value('42')->render() . $form->close();
        $xpath = $this->xpath($html);
        static::assertSame(0.0, $xpath->evaluate('count(//form/@action)'));
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
        static::assertTrue(new FormProtector()->validate($payload, $expected . '?next=%2Fhome', $session));
        static::assertFalse(new FormProtector()->validate($payload, '/different', $session));
        $payload['record']['id'] = '999';
        static::assertFalse(new FormProtector()->validate($payload, $expected . '?next=%2Fhome', $session));
    }

    /** @return array<string, array{string, string, string}> Request paths retain their browser submission targets. */
    public static function requestPaths(): array
    {
        $cases = [];
        foreach ([
            'ordinary' => ['/articles/save', '/articles/save'],
            'network path' => ['//evil.example/login', '//evil.example/login'],
            'extra slashes' => ['///evil.example/login', '///evil.example/login'],
            'backslash' => ['/\\evil.example/login', '/%5Cevil.example/login'],
        ] as $label => [$path, $expected]) {
            foreach (['default', 'omitted'] as $mode) {
                $cases[$label . ' ' . $mode] = [$path, $expected, $mode];
            }
        }

        return $cases;
    }

    public function testAnInvalidExternalPortCannotBecomeASignedFormAction(): void
    {
        $form = $this->helper()->form()->url('http://example.test:99999')->bind();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid form action URL.');
        $form->open();
    }
}
