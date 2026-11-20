<?php
declare(strict_types=1);

namespace FormCompose\Form;

use Closure;
use FormCompose\Form\Field\BoundField;
use FormCompose\Form\Field\FieldTypeInterface;
use FormCompose\Rendering\Attributes;
use FormCompose\Security\FormProtection;
use LogicException;
use Throwable;

/**
 * Tracks rendered input identities and atomic rendering registrations.
 *
 * FormLifecycle enforces form phases and multipart requirements. Definitions and
 * control batches stage successful inputs and buttons until rendering succeeds;
 * failed renders can be retried without retaining IDs or partial protection data.
 * Inject through FormLifecycle.
 */
class InputRegistry
{
    /** @var array<string, true> Rendered identities, including staged inputs. */
    protected array $rendered = [];

    /** @var list<\FormCompose\Form\Field\Submission> Inputs staged until the outermost render succeeds. */
    protected array $pending = [];

    /** @var list<string> Button names staged until the outermost render succeeds. */
    protected array $pendingButtons = [];

    /** @var array<string, ?string> Successful control targets, included in render rollback. */
    protected array $targets = [];

    protected int $depth = 0;

    /**
     * Share the owning lifecycle's protection service.
     */
    public function __construct(
        protected readonly FormProtection $protection,
        public readonly FeedbackRegistry $feedback = new FeedbackRegistry(),
        public readonly FeedbackReferences $references = new FeedbackReferences(),
    ) {
    }

    /**
     * Reject duplicate input identities, including within nested layouts.
     */
    public function assertAvailable(string $id): void
    {
        if (isset($this->rendered[$id])) {
            throw new LogicException('An input with this ID has already been rendered: ' . $id);
        }
    }

    /**
     * Retain successful focus targets even when the field has no errors.
     */
    public function recordTarget(BoundField $field, FieldTypeInterface $type, Attributes $attributes): void
    {
        $this->targets[$field->state->id] = FeedbackEntry::target($field, $type, $attributes);
    }

    /**
     * Read a successful target without rendering or registering anything.
     */
    public function target(string $id): ?string
    {
        return $this->targets[$id] ?? null;
    }

    /**
     * Record actual submissions after a field renders successfully.
     *
     * @param list<\FormCompose\Form\Field\Submission> $submissions Actual submitted inputs.
     * @param list<string> $ids Additional emitted IDs beyond the field's main ID.
     */
    public function register(string $id, array $submissions, array $ids = []): void
    {
        $this->registerIds([$id, ...$ids]);
        if ($this->depth > 0) {
            array_push($this->pending, ...$submissions);
        } else {
            $this->protection->register($submissions);
        }
    }

    /**
     * Stage optional button submissions in the same transaction as rendered inputs.
     */
    public function button(string $name): void
    {
        if ($this->depth > 0) {
            $this->pendingButtons[] = $name;
        } else {
            $this->protection->button($name);
        }
    }

    /**
     * Reserve emitted DOM IDs, including feedback, options, and custom composite inputs.
     *
     * @param list<string> $ids Emitted IDs.
     */
    public function registerIds(array $ids): void
    {
        $reserved = [];
        foreach ($ids as $id) {
            if ($id === '') {
                throw new LogicException('Rendered element IDs must be nonempty.');
            }

            $this->assertAvailable($id);
            if (isset($reserved[$id])) {
                throw new LogicException('An input with this ID has already been rendered: ' . $id);
            }

            $reserved[$id] = true;
        }

        foreach ($ids as $id) {
            $this->rendered[$id] = true;
        }
    }

    /**
     * Validate generated feedback associations against committed presentation IDs.
     */
    public function assertFeedback(): void
    {
        $this->references->assertResolved($this->rendered);
    }

    /**
     * Commit inputs and buttons after the outermost layout succeeds, or restore the snapshot.
     *
     * @param \Closure(): string $render Definition or control-batch rendering operation.
     */
    public function render(Closure $render, ?Attributes $attributes = null): string
    {
        $rendered = $this->rendered;
        $pending = $this->pending;
        $pendingButtons = $this->pendingButtons;
        $feedback = $this->feedback->entries();
        $references = $this->references->expectations();
        $targets = $this->targets;
        $this->depth++;
        try {
            $html = $render();
            if ($attributes?->has('id')) {
                $this->registerIds([(string)$attributes->values['id']]);
            }
        } catch (Throwable $throwable) {
            $this->rendered = $rendered;
            $this->pending = $pending;
            $this->pendingButtons = $pendingButtons;
            $this->feedback->restore($feedback);
            $this->references->restore($references);
            $this->targets = $targets;
            throw $throwable;
        } finally {
            $this->depth--;
        }

        if ($this->depth === 0) {
            $this->protection->register($this->pending);
            $this->pending = [];
            foreach ($this->pendingButtons as $name) {
                $this->protection->button($name);
            }

            $this->pendingButtons = [];
        }

        return $html;
    }
}
