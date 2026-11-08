<?php
declare(strict_types=1);

namespace FormCompose\Rendering;

use Cake\View\HtmlStringable;
use Cake\View\View;
use Closure;
use FormCompose\Configuration\ActionConfig;
use FormCompose\Configuration\FieldConfig;
use FormCompose\Form\FormSession;
use FormCompose\Form\PostAction;
use FormCompose\Form\PostActionSubmission;

/**
 * Builds independent protected action forms through the helper's binding factory.
 *
 * Form sessions own hidden inputs, tokens, and button submissions. This service
 * places complete forms inline or in view blocks and renders link parts.
 * Override trigger() via FormHelper::createPostActionRenderer().
 */
class PostActionRenderer
{
    /**
     * Retain the view and ordinary binding entry point, preserving session overrides.
     *
     * @param \Closure(\FormCompose\Configuration\FormConfig, \FormCompose\Rendering\RenderContext): \FormCompose\Form\FormSession $bind Session factory.
     */
    public function __construct(protected readonly View $view, protected readonly Closure $bind)
    {
    }

    /**
     * Return a complete action without changing an existing form session.
     *
     */
    public function render(
        string|HtmlStringable $label,
        array|string|null $url,
        ActionConfig $config,
        string $kind,
        RenderContext $rendering,
    ): string {
        $action = new PostAction($kind, $url, $config);
        $form = ($this->bind)($action->form, $rendering);
        $html = $form->open();
        /** @var mixed $value */
        foreach ($action->data as $path => $value) {
            $html .= $form->control(
                $path,
                new FieldConfig(type: 'hidden')->value($value)
                    ->secure(true),
            )->render();
        }

        $trigger = $this->trigger($label, $action, $form, $rendering);
        $inside = $kind === 'button' && $action->block === null;
        $html .= $inside ? $trigger : '';
        $html .= $form->close();
        if ($action->block !== null) {
            $this->view->append($action->block, $html);
            $html = '';
        }

        return $html . ($inside ? '' : $trigger);
    }

    /**
     * Reuse normal button rendering or render a link associated with the action form.
     */
    protected function trigger(
        string|HtmlStringable $label,
        PostAction $action,
        FormSession $form,
        RenderContext $rendering,
    ): string {
        $presentation = $action->presentation;
        $attributes = $presentation->attributes;
        if ($action->kind === 'button') {
            PostActionSubmission::button($action->data, $action->button->name);
            $config = $action->button->presentation($presentation->attributes(['form' => $form->binding->id]));

            return $form->submit($label, $config)->render();
        }

        $attributes = $rendering
            ->theme
            ->attributes('action.control', 'link')
            ->merge($attributes)
            ->replace([
                'href' => '#' . rawurlencode($form->binding->id),
                'data-form-compose-action' => $form->binding->id,
            ]);

        return $rendering->renderer->render('control', new RenderNode(
            'action',
            'link',
            ['form' => $form, 'label' => $label, 'attributes' => $attributes],
            $presentation,
        ));
    }
}
