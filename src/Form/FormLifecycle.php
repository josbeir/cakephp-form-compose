<?php
declare(strict_types=1);

namespace FormCompose\Form;

use FormCompose\Binding\FormBinding;
use FormCompose\Configuration\FormConfig;
use FormCompose\Form\Field\BoundField;
use FormCompose\Rendering\Attributes;
use FormCompose\Rendering\Theme;
use FormCompose\Security\FormProtection;
use LogicException;

/**
 * Tracks when a form can render inputs and collects its submission protection data.
 *
 * FormSession opens and closes this lifecycle; BoundField registers successfully
 * rendered inputs with it. It rejects duplicate input IDs, file inputs in ordinary
 * forms, and registrations outside the open phase. Closing prepares final tokens;
 * a failed closing template restores the open phase so rendering can be retried.
 * The constructor accepts custom construction and protection services; supply a
 * lifecycle instance through FormSession::createLifecycle().
 */
class FormLifecycle
{
    protected ?FormState $state = null;

    protected FormPhase $phase = FormPhase::Pending;

    protected readonly FormProtection $protection;

    public readonly InputRegistry $inputs;

    /**
     * Initialize submission protection for the bound form.
     */
    public function __construct(
        protected readonly FormBinding $binding,
        protected readonly FormConstruction $construction = new FormConstruction(),
        ?FormProtection $protection = null,
        ?InputRegistry $inputs = null,
    ) {
        $this->protection = $protection ?? new FormProtection($binding->request);
        $this->inputs = $inputs ?? new InputRegistry($this->protection);
    }

    /**
     * Prepare an opening without committing the lifecycle before rendering succeeds.
     *
     */
    public function prepare(Theme $theme, FormConfig $config): FormState
    {
        if ($this->state instanceof FormState) {
            throw new LogicException('A form session can only be opened once.');
        }

        return $this->construction->prepare($this->binding, $theme, $config);
    }

    /**
     * Commit the opening and start requested tamper protection after successful rendering.
     */
    public function opened(FormState $state): void
    {
        $this->protection->start($state);
        $this->state = $state;
        $this->phase = FormPhase::Open;
    }

    /**
     * Require an active lifecycle and return its resolved metadata.
     */
    public function assertOpen(): FormState
    {
        if (!$this->state instanceof FormState || $this->phase !== FormPhase::Open) {
            throw new LogicException('Expected an open form; current phase is ' . $this->phase->name . '.');
        }

        return $this->state;
    }

    /**
     * Explicitly unlock a field or collection while this form is open.
     */
    public function unlock(string $path): void
    {
        $this->assertOpen();
        $this->protection->optional($path);
    }

    /**
     * Reject duplicate input identities and file controls outside a multipart form.
     */
    public function assertInput(BoundField $field): void
    {
        $state = $this->assertOpen();
        $this->inputs->assertAvailable($field->state->id);

        if ($field->state->type === 'file' && !$state->multipart()) {
            throw new LogicException('File inputs require a multipart form.');
        }
    }

    /**
     * Commit the actual submitted names only after an input renderer has succeeded.
     *
     * @param list<\FormCompose\Form\Field\Submission> $submissions Actual submitted inputs.
     * @param list<string> $ids Additional IDs emitted by the field type.
     */
    public function register(BoundField $field, array $submissions, array $ids = []): void
    {
        $this->assertInput($field);
        $this->inputs->register($field->state->id, $submissions, $ids);
    }

    /**
     * Register a successfully rendered named button as an optional submitted control.
     */
    public function button(Attributes $attributes): void
    {
        $this->assertOpen();
        if (isset($attributes->values['name'])) {
            $this->protection->optional((string)$attributes->values['name']);
        }
    }

    /**
     * Prepare final tokens without closing the lifecycle before templates succeed.
     *
     * @return list<\FormCompose\Rendering\Attributes>
     */
    public function tokens(): array
    {
        $state = $this->assertOpen();
        $this->inputs->assertFeedback();
        $fields = $this->protection->fields($state);
        $this->phase = FormPhase::Finalizing;

        return $fields;
    }

    /**
     * Commit the successfully rendered closing.
     */
    public function close(): void
    {
        $this->phase = FormPhase::Closed;
    }

    /**
     * Restore input collection after a closing template fails.
     */
    public function cancelClose(): void
    {
        $this->phase = FormPhase::Open;
    }
}
