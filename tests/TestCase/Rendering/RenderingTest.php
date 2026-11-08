<?php
declare(strict_types=1);

namespace FormCompose\Test\TestCase\Rendering;

use Cake\View\Form\ArrayContext;
use FormCompose\Rendering\Theme;
use FormCompose\Test\FormTestCase;
use InvalidArgumentException;
use TypeError;
use function Cake\View\html;

class RenderingTest extends FormTestCase
{
    public function testLabelsHelpErrorsAndValuesAreEscapedAndAssociated(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext([
                'errors' => ['email' => ['invalid' => '<script>bad</script>']],
            ]))
            ->url('/articles/save')
            ->bind();
        $field = $form->field('email')
            ->type('email')
            ->label('<b>Email</b>')
            ->help('<i>Help</i>')
            ->value('"><script>bad</script>')
            ->resolve();
        $form->open();
        $html = $field->control();
        static::assertStringContainsString('&lt;b&gt;Email&lt;/b&gt;', $html);
        static::assertStringContainsString('&lt;i&gt;Help&lt;/i&gt;', $html);
        static::assertStringNotContainsString('<script>', $html);
        static::assertStringContainsString('aria-invalid="true"', $html);
        static::assertStringContainsString(
            'aria-describedby="' . $field->state->id . '-help ' . $field->state->id . '-errors"',
            $html,
        );
        static::assertStringContainsString('id="' . $field->state->id . '-errors"', $html);
        static::assertStringContainsString('for="' . $field->state->id . '"', $html);
        $form->close();
    }

    public function testSuppressedPartsDoNotProduceAriaReferences(): void
    {
        $field = $this->helper()
            ->form(new ArrayContext(['errors' => ['name' => ['bad']]]))
            ->bind()
            ->field('name')
            ->label(false)
            ->help(false)
            ->errors(false)
            ->resolve();
        static::assertSame('', $field->label()->render());
        static::assertSame('', $field->help()->render());
        static::assertSame('', $field->errors()->render());
        static::assertArrayNotHasKey('aria-describedby', $field->attributes()->values);
    }

    public function testTrustedMarkupRequiresExplicitWrapper(): void
    {
        $field = $this->helper()
            ->form()
            ->bind()
            ->field('name')
            ->label(html('<b>Name</b>'))
            ->help(html('<em>Help</em>'))
            ->resolve();
        static::assertStringContainsString('<b>Name</b>', $field->label()->render());
        static::assertStringContainsString('<em>Help</em>', $field->help()->render());
    }

    public function testNativeControlsSupportChoicesAndSpecialValues(): void
    {
        $form = $this->helper()->form()->url('/articles/save')->bind();
        $form->open();
        static::assertStringContainsString(
            'value="0"',
            $form->control('off')->value(false)->render(),
        );
        static::assertStringContainsString(
            '&lt;body&gt;',
            $form->control('body')->type('textarea')->value('<body>')->render(),
        );
        static::assertStringContainsString(
            'selected="selected"',
            $form->control('status')
                ->choices(['a' => 'Active', 'b' => 'Blocked'])
                ->value('a')
                ->render(),
        );
        $checkbox = $form->control('active')->type('checkbox')->value(true)->render();
        static::assertStringContainsString('type="hidden"', $checkbox);
        static::assertStringContainsString('checked="checked"', $checkbox);
        static::assertStringNotContainsString(
            'secret',
            $form->control('password')->type('password')->value('secret')->render(),
        );
        static::assertStringNotContainsString(
            '<label',
            $form->control('id')->type('hidden')->value('2')->render(),
        );
        $form->close();
    }

    public function testGroupedControlsUseLegendsAndUniqueChoiceIds(): void
    {
        $form = $this->helper()->form()->url('/articles/save')->bind();
        $form->open();

        $radio = $form->control('status')
            ->type('radio')
            ->choices(['a' => 'Active', 'b' => 'Blocked'])
            ->value('b')
            ->render();
        static::assertStringContainsString('<fieldset>', $radio);
        static::assertStringContainsString('<legend>Status</legend>', $radio);
        static::assertStringContainsString('id="form-compose-1-status-a"', $radio);
        static::assertStringContainsString('id="form-compose-1-status-b"', $radio);
        $checks = $form->control('tags')
            ->type('multicheckbox')
            ->choices(['a' => 'A', 'b' => 'B'])
            ->value(['a'])
            ->render();
        static::assertStringContainsString('name="tags[]"', $checks);
        $multiple = $form->control('categories')
            ->choices(['a' => 'A'])
            ->multiple(true)
            ->value(['a'])
            ->render();
        static::assertStringContainsString('name="categories[]"', $multiple);
        $form->close();
    }

    public function testThemeDefaultsAndFieldAttributesAreMerged(): void
    {
        $theme = new Theme(defaults: [
            'input' => ['class' => 'control'],
            'email.input' => ['class' => 'email-control'],
            'label' => ['class' => 'caption'],
        ]);
        $field = $this->helper()
            ->form()
            ->theme($theme)
            ->bind()
            ->field('email')
            ->type('email')
            ->attributes(['class' => 'wide', 'x-model' => 'email'])
            ->partAttributes('help', ['class' => 'hint'])
            ->resolve();
        static::assertSame('email-control wide', $field->attributes()->values['class']);
        static::assertSame('email', $field->attributes()->values['x-model']);
        static::assertSame('caption', $field->attributes('label')->values['class']);
        static::assertSame('hint', $field->attributes('help')->values['class']);
    }

    public function testReservedInputAttributesCannotSilentlyChangeSecurityIdentity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->helper()
            ->form()
            ->bind()
            ->field('email')
            ->resolve()
            ->attributes(extra: ['name' => 'other']);
    }

    public function testUnknownThemeNameIsRejected(): void
    {
        $this->expectException(TypeError::class);
        $this->invokeBuilder($this->helper()->form(), 'theme', 'UnknownTheme');
    }

    public function testHiddenControlsDoNotReferenceUnrenderedFeedback(): void
    {
        $form = $this->helper()
            ->form(new ArrayContext(['errors' => ['id' => ['invalid']]]))
            ->url('/articles/save')
            ->bind();
        $form->open();

        $html = $form->control('id')->type('hidden')->help('Invisible help')->render();
        static::assertStringNotContainsString('aria-describedby', $html);
        static::assertStringNotContainsString('aria-invalid', $html);
        $form->close();
    }
}
