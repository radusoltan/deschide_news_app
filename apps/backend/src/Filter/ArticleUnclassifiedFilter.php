<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Article;
use Doctrine\ORM\QueryBuilder;

/**
 * API Platform filter that narrows the Article collection to rows with NO
 * linked topics (article_topics has no matching row).
 *
 * Activated via `?unclassified=1`. Any other value (omitted, 0, empty) is
 * a no-op so the filter blends with existing query patterns.
 */
final class ArticleUnclassifiedFilter extends AbstractFilter
{
    private const PARAMETER_NAME = 'unclassified';

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($property !== self::PARAMETER_NAME) {
            return;
        }

        if (!$this->isTruthy($value)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // NOT EXISTS over the article_topics join so we don't force a
        // LEFT JOIN which would complicate other predicates / sort orders.
        $subAlias = $queryNameGenerator->generateJoinAlias('unclassified_sub');
        $joinAlias = $queryNameGenerator->generateJoinAlias('unclassified_topic');

        $subQuery = $queryBuilder->getEntityManager()->createQueryBuilder()
            ->select(sprintf('1'))
            ->from(Article::class, $subAlias)
            ->innerJoin(sprintf('%s.topics', $subAlias), $joinAlias)
            ->where(sprintf('%s.id = %s.id', $subAlias, $rootAlias));

        $queryBuilder->andWhere(sprintf('NOT EXISTS (%s)', $subQuery->getDQL()));
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER_NAME => [
                'property' => null,
                'type' => 'bool',
                'required' => false,
                'description' => 'When true, returns only articles with no linked topics (NOT EXISTS on article_topics).',
                'openapi' => [
                    'example' => '1',
                    'allowEmptyValue' => false,
                ],
            ],
        ];
    }

    private function isTruthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_int($value)) {
            return $value === 1;
        }
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return false;
    }
}
