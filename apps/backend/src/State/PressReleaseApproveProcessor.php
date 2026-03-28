<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\PressRelease;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PressReleaseApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PressRelease
    {
        if (!$data instanceof PressRelease) {
            throw new BadRequestHttpException('Invalid data');
        }

        if ($data->getStatus() !== PressReleaseStatus::PENDING) {
            throw new BadRequestHttpException('Only pending press releases can be approved');
        }

        // Create article from press release
        $article = new Article();
        $article->setTitle($data->getTitle());
        $article->setLead($data->getLead());
        $article->setContent($data->getContent());
        $article->setStatus(ArticleStatus::NEW);
        $article->setSourceEmail($data->getSourceEmailId());
        $article->setTranslatableLocale('ro');

        // Map category
        $category = $this->categoryRepository->findOneBy(['slug' => $data->getCategorySlug()]);
        if ($category === null) {
            $category = $this->categoryRepository->findOneBy(['slug' => 'societate']);
        }
        if ($category !== null) {
            $article->setCategory($category);
        }

        $this->em->persist($article);

        // Update press release status
        $data->setStatus(PressReleaseStatus::APPROVED);
        $data->setProcessedAt(new \DateTimeImmutable());
        $data->setArticle($article);

        $user = $this->security->getUser();
        if ($user !== null) {
            $data->setProcessedBy($user);
        }

        $this->em->flush();

        return $data;
    }
}
