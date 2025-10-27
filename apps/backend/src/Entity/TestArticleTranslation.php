<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TestArticleTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Entity\MappedSuperclass\AbstractTranslation;

#[ORM\Entity(repositoryClass: TestArticleTranslationRepository::class)]
#[ORM\Table(name: 'test_article_translations')]
#[ORM\Index(columns: ['locale', 'object_class', 'field', 'foreign_key'], name: 'test_article_translation_idx')]
class TestArticleTranslation extends AbstractTranslation
{
    // AbstractTranslation already contains id, locale, field, object_class, foreign_key, and content properties
}
