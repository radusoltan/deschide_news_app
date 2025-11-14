<?php

declare(strict_types=1);

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
class ImportantArticlesCount extends Constraint
{
    public string $minMessage = 'The important articles list must have at least {{ min }} articles.';

    public string $maxMessage = 'The important articles list cannot have more than {{ max }} articles.';

    public int $min = 5;

    public int $max = 25;

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
