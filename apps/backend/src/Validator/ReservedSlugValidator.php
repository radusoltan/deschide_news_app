<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Reserved Slug Validator.
 *
 * Validates that a slug is not in the list of reserved slugs.
 */
class ReservedSlugValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ReservedSlug) {
            throw new UnexpectedTypeException($constraint, ReservedSlug::class);
        }

        // Null and empty values are allowed (handled by NotBlank if needed)
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Normalize slug to lowercase for comparison
        $normalizedSlug = strtolower(trim($value));

        // Check if slug is in reserved list
        if (\in_array($normalizedSlug, ReservedSlug::RESERVED_SLUGS, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ slug }}', $value)
                ->addViolation();
        }
    }
}
