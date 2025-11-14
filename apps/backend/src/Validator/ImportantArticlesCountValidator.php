<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ImportantArticlesList;
use App\Repository\ImportantArticlesListRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ImportantArticlesCountValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ImportantArticlesListRepository $repository
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ImportantArticlesCount) {
            throw new UnexpectedTypeException($constraint, ImportantArticlesCount::class);
        }

        if (!$value instanceof ImportantArticlesList) {
            throw new UnexpectedTypeException($value, ImportantArticlesList::class);
        }

        // Count current articles in the list
        $currentCount = $this->repository->count([]);

        // If this is an existing entity (has ID), we're not adding a new one
        if ($value->getId() !== null) {
            return;
        }

        // Adding a new article
        $newCount = $currentCount + 1;

        if ($newCount > $constraint->max) {
            $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ max }}', (string) $constraint->max)
                ->addViolation();
        }
    }
}
