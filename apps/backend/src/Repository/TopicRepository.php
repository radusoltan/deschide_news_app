<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

/**
 * @extends NestedTreeRepository<Topic>
 */
class TopicRepository extends NestedTreeRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(Topic::class));
    }
}
