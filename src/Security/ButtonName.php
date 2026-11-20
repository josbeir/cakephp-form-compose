<?php
declare(strict_types=1);

namespace FormCompose\Security;

use FormCompose\Form\Field\FieldDefinition;
use InvalidArgumentException;

/** Validates automatic submit-button unlocks before FormProtection issues tokens. */
final class ButtonName
{
    /**
     * Keep optional buttons independent of protected input paths and infrastructure.
     *
     * CakePHP matches unlocked names against path segments, including nested inputs.
     * Check the completed submission list so rendering order cannot affect safety.
     *
     * @param string $name Normalized dot-separated button name.
     * @param list<\FormCompose\Form\Field\Submission> $submissions Successfully rendered inputs.
     * @throws \InvalidArgumentException If the name is invalid or overlaps protected paths.
     */
    public static function assertIndependent(string $name, array $submissions): void
    {
        new FieldDefinition($name);
        $parts = explode('.', $name);
        if (array_intersect($parts, ['_method', '_csrfToken', '_Token']) !== []) {
            throw new InvalidArgumentException('Submit button names cannot overlap form infrastructure fields.');
        }

        foreach ($submissions as $submission) {
            if (!$submission->locked) {
                continue;
            }

            $field = str_replace(['[', ']'], ['.', ''], $submission->name);
            if (array_intersect($parts, explode('.', $field)) !== []) {
                throw new InvalidArgumentException('Submit button names cannot overlap protected field paths.');
            }
        }
    }
}
