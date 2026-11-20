<?php
declare(strict_types=1);

namespace FormCompose\Security;

use Cake\Core\Configure;
use Cake\Form\FormProtector;
use Cake\Http\ServerRequest;
use FormCompose\Form\FormState;
use FormCompose\Rendering\Attributes;

/**
 * Collects submitted field names and builds CakePHP form-protection token values.
 *
 * FormLifecycle starts protection when FormProtectionComponent has supplied
 * formTokenData on the request. Field types describe submitted names and locked
 * hidden values; optional buttons are unlocked. During closing, token values are
 * returned as attribute bags for the form's hidden-input templates.
 * Successful submissions are queued until closing so explicit collection unlocks
 * apply regardless of when their inputs were rendered.
 *
 * Uses CakePHP's internal FormProtector API; supported CakePHP versions are tested
 * for compatibility with this integration.
 */
class FormProtection
{
    protected ?FormProtector $protector = null;

    /** @var list<\FormCompose\Form\Field\Submission> Successfully rendered inputs awaiting token generation. */
    protected array $submissions = [];

    /** @var list<string> Optional submit names checked against the completed form before unlocking. */
    protected array $buttons = [];

    /**
     * Capture the current request without starting a session or registering inputs.
     */
    public function __construct(protected readonly ServerRequest $request)
    {
    }

    /**
     * Enable tamper protection only when the host application's component requested it.
     */
    public function start(FormState $state): void
    {
        /** @var array<string, mixed>|null $data */
        $data = $this->request->getAttribute('formTokenData');
        if ($state->method !== 'get' && $data !== null) {
            $this->request->getSession()->start();
            $this->protector = new FormProtector($data);
        }
    }

    /**
     * Register only successful rendered inputs and explicit locked hidden values.
     *
     * @param list<\FormCompose\Form\Field\Submission> $submissions Actual submitted inputs.
     */
    public function register(array $submissions): void
    {
        array_push($this->submissions, ...$submissions);
    }

    /**
     * Explicitly unlocked paths may vary or be omitted.
     */
    public function optional(string $name): void
    {
        $this->protector?->unlockField($name);
    }

    /**
     * Stage automatic button unlocks separately from explicitly unlocked fields.
     */
    public function button(string $name): void
    {
        $this->buttons[] = str_replace(['[', ']'], ['.', ''], $name);
    }

    /**
     * Return token values for hidden-field templates without embedding any markup.
     *
     * @return list<\FormCompose\Rendering\Attributes>
     * @throws \InvalidArgumentException If an automatic button unlock would weaken field protection.
     */
    public function fields(FormState $state): array
    {
        if (!$this->protector instanceof FormProtector) {
            return [];
        }

        $protector = clone $this->protector;
        foreach ($this->buttons as $name) {
            ButtonName::assertIndependent($name, $this->submissions);
            $protector->unlockField($name);
        }

        foreach ($this->submissions as $submission) {
            $protector->addField($submission->name, $submission->locked, $submission->value);
        }

        $data = $protector->buildTokenData($state->action, $this->request->getSession()->id());
        if (!Configure::read('debug')) {
            unset($data['debug']);
        }

        $fields = [];
        /** @var mixed $value */
        foreach ($data as $name => $value) {
            $fields[] = new Attributes(['type' => 'hidden', 'name' => '_Token[' . $name . ']', 'value' => $value]);
        }

        return $fields;
    }
}
