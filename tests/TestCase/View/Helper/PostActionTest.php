<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\View\Helper;

use FormCompose\Configuration\FormConfig;
use FormCompose\Rendering\Theme;
use FormCompose\Test\FormTestCase;
use InvalidArgumentException;
use function Cake\View\html;

/**
 * Exercises standalone action output through the real helper and PHP templates.
 */
class PostActionTest extends FormTestCase
{
    public function testPostButtonUsesNativeMarkupAndAnIndependentPostSession(): void
    {
        $csrf = bin2hex(random_bytes(16));
        $helper = $this->helper(['version' => 99], ['csrfToken' => $csrf]);
        $html = $helper->postButton(label: 'Delete <record>', url: '/records/delete/5')
            ->data(['version' => 0, 'flags' => ['active' => false], 'empty' => '', 'missing' => null])
            ->form(FormConfig::builder()->attributes(['class' => 'action-form']))
            ->attributes(['class' => 'danger'])
            ->render();
        $xpath = $this->xpath($html);
        static::assertSame(
            1,
            $xpath->query('//form[@method="post"][@action="/records/delete/5"][@class="action-form"]')->length,
        );
        static::assertSame(
            1,
            $xpath->query('//form/button[@type="submit"][@class="danger"][@form="form-compose-1"]')->length,
        );
        static::assertSame(0, $xpath->query('//script | //input[@name="_method"]')->length);
        static::assertSame($csrf, $xpath->evaluate('string(//input[@name="_csrfToken"]/@value)'));
        static::assertSame('0', $xpath->evaluate('string(//input[@name="flags[active]"]/@value)'));
        static::assertSame('0', $xpath->evaluate('string(//input[@name="version"]/@value)'));
        static::assertSame(1, $xpath->query('//input[@name="missing"]')->length);
        static::assertStringContainsString('Delete &lt;record&gt;', $html);
        static::assertStringContainsString(
            'id="form-compose-2"',
            $helper->postButton(label: 'Again', url: '/records/delete/6')->render(),
        );
    }

    public function testPostLinksUseHiddenFormsAndLoadAnExternalScriptOnlyOnce(): void
    {
        $helper = $this->helper(attributes: ['cspScriptNonce' => 'nonce<safe>']);
        $html = $helper->postLink(label: 'Delete', url: '/records/delete/5')
            ->method('DELETE')
            ->attributes(['target' => '_blank'])
            ->render();
        $html .= $helper->postLink(label: html('<strong>Archive</strong>'), url: '/records/archive/5')
            ->render();
        $xpath = $this->xpath($html);
        static::assertSame(2, $xpath->query('//form[@hidden]')->length);
        static::assertSame('DELETE', $xpath->evaluate('string(//form[1]/input[@name="_method"]/@value)'));
        static::assertSame('_blank', $xpath->evaluate('string(//form[1]/@target)'));
        static::assertSame('form-compose-1', $xpath->evaluate('string(//a[1]/@data-form-compose-action)'));
        static::assertSame('#form-compose-1', $xpath->evaluate('string(//a[1]/@href)'));
        static::assertSame(0, $xpath->query('//script')->length);
        $scripts = $this->xpath($helper->getView()->fetch('script'));
        static::assertSame(1, $scripts->query('//script[@src][@defer]')->length);
        static::assertSame('nonce<safe>', $scripts->evaluate('string(//script/@nonce)'));
        static::assertStringContainsString('/form_compose/js/actions.js', $helper->getView()->fetch('script'));
        static::assertStringNotContainsString('onclick=', $html);
        static::assertStringContainsString('<strong>Archive</strong>', $html);
    }

    public function testButtonsAndLinksShareConfirmationAndAssetLoading(): void
    {
        $helper = $this->helper();
        $html = $helper->postButton(label: 'Delete', url: '/delete')->confirm('Delete "this" <record>?')->render();
        $html .= $helper->postLink(label: 'Archive', url: '/archive')->confirm('Archive?')->render();
        $xpath = $this->xpath($html);
        static::assertSame('Delete "this" <record>?', $xpath->evaluate('string(//form[1]/@data-form-compose-confirm)'));
        static::assertSame('Archive?', $xpath->evaluate('string(//form[2]/@data-form-compose-confirm)'));
        static::assertSame(0, $xpath->query('//script')->length);
        static::assertSame(1, $this->xpath($helper->getView()->fetch('script'))->query('//script')->length);
        static::assertSame(0, $xpath->query('//*[@confirm or @onclick or @onsubmit]')->length);
    }

    public function testViewBlocksPlaceFormsOutsideTheirVisibleTriggers(): void
    {
        $helper = $this->helper();
        $button = $helper->postButton(label: 'Delete', url: '/delete')
            ->block('postActions')
            ->form(FormConfig::builder()->id('delete-action'))
            ->render();
        $link = $helper->postLink(label: 'Archive', url: '/archive')->block('actions')->render();
        static::assertStringNotContainsString('<form', $button . $link);
        static::assertStringContainsString('form="delete-action"', $button);
        static::assertStringContainsString('id="delete-action"', $helper->getView()->fetch('postActions'));
        static::assertStringContainsString('hidden', $helper->getView()->fetch('postActions'));
        static::assertStringContainsString('action="/archive"', $helper->getView()->fetch('actions'));
    }

    public function testThemesAndOccurrenceTemplatesCustomizeBothTriggers(): void
    {
        $theme = new Theme(defaults: [
            'button' => ['class' => 'button'],
            'action.control' => ['class' => 'action-link'],
            'form.open' => ['class' => 'action-form'],
        ]);
        $helper = $this->helper();
        $html = $helper->postButton(label: html('<em>Go</em>'), url: '/go', theme: $theme)
            ->partTemplate('control', 'actions/button')
            ->templateData(['marker' => '<custom>'])
            ->render();
        $html .= $helper->postLink(label: 'Next', url: '/next', theme: $theme)
            ->partTemplate('control', 'actions/link')
            ->templateData(['marker' => '<link>'])
            ->render();
        static::assertStringContainsString('class="button"', $html);
        static::assertStringContainsString('class="action-link"', $html);
        static::assertStringContainsString('class="action-form"', $html);
        static::assertStringContainsString('data-marker="&lt;custom&gt;"', $html);
        static::assertStringContainsString('data-marker="&lt;link&gt;"', $html);
        static::assertStringContainsString('<em>Go</em>', $html);
    }

    public function testOmittedLinkUrlUsesTheCurrentRequestAndFormOptionsCannotOverrideTheTarget(): void
    {
        $helper = $this->helper();
        $html = $helper->postLink(label: 'Here')->render();
        static::assertStringContainsString('action="/articles/save"', $html);
        $html = $helper->postButton(label: 'Other', url: '/explicit')
            ->method('patch')
            ->form(
                FormConfig::builder()
                    ->method('delete')
                    ->url('/ignored')
                    ->method('get'),
            )
            ->render();
        static::assertStringContainsString('action="/explicit"', $html);
        static::assertStringContainsString('name="_method" value="PATCH"', $html);
        $html = $helper->postButton(label: 'Nested method', url: '/nested')
            ->form(FormConfig::builder()->method('put'))
            ->render();
        static::assertStringContainsString('name="_method" value="PUT"', $html);
    }

    public function testInvalidThemeIsRejectedThroughBothActionEntryPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->helper()->postButton(label: 'Go', url: '/go', theme: 'Unknown')->render();
    }

    public function testRouteArraysAndHiddenDataAreEscapedThroughTheOrdinaryPipeline(): void
    {
        $html = $this->helper()
            ->postButton(label: 'Delete', url: [
                'controller' => 'articles',
                'action' => 'delete',
                5,
                '?' => ['source' => 'a&b'],
            ])
            ->data(['note' => '"><script>bad</script>'])
            ->render();
        static::assertStringContainsString('action="/articles/delete/5?source=a%26b"', $html);
        static::assertStringContainsString('&quot;&gt;&lt;script&gt;bad&lt;/script&gt;', $html);
        static::assertStringNotContainsString('<script>', $html);
    }
}
