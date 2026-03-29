<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Image;
use App\Entity\PressRelease;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PressReleaseApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
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
        $article->setTranslatableLocale('ro');

        // Only set sourceEmail if no existing article has it (unique constraint)
        $existingArticle = $this->articleRepository->findOneBy(['sourceEmail' => $data->getSourceEmailId()]);
        if ($existingArticle === null) {
            $article->setSourceEmail($data->getSourceEmailId());
        }

        // Map category
        $category = $this->categoryRepository->findOneBy(['slug' => $data->getCategorySlug()]);
        if ($category === null) {
            $category = $this->categoryRepository->findOneBy(['slug' => 'societate']);
        }
        if ($category !== null) {
            $article->setCategory($category);
        }

        $this->em->persist($article);

        // Attach image if press release has one
        if ($data->hasAttachment()) {
            $this->attachImage($article, $data);
        }

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

    private function attachImage(Article $article, PressRelease $pressRelease): void
    {
        try {
            $sourcePath = $this->projectDir . '/' . $pressRelease->getAttachmentPath();
            if (!file_exists($sourcePath)) {
                $this->logger->warning('Press image file not found', ['path' => $sourcePath]);
                return;
            }

            // Copy to VichUploader upload directory
            $uploadDir = $this->projectDir . '/public/uploads/images/originals';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = strtolower(pathinfo($pressRelease->getAttachmentFilename(), PATHINFO_EXTENSION));
            $newFilename = sprintf('press_%d_%s.%s', time(), bin2hex(random_bytes(6)), $ext);
            $destPath = $uploadDir . '/' . $newFilename;

            copy($sourcePath, $destPath);

            // Get image dimensions
            $imageSize = @getimagesize($destPath);
            $width = $imageSize[0] ?? 0;
            $height = $imageSize[1] ?? 0;

            // Create Image entity (without VichUploader — set fields directly)
            $image = new Image();
            $image->setFilename($newFilename);
            $image->setOriginalFilename($pressRelease->getAttachmentFilename());
            $image->setPath('images/originals/' . $newFilename);
            $image->setMimeType($pressRelease->getAttachmentMimeType());
            $image->setSize($pressRelease->getAttachmentSize());
            $image->setWidth($width);
            $image->setHeight($height);
            $image->setAlt($pressRelease->getTitle());

            $this->em->persist($image);

            // Create ArticleImage link
            $articleImage = new ArticleImage();
            $articleImage->setArticle($article);
            $articleImage->setImage($image);
            $articleImage->setPosition(0);
            $articleImage->setIsFeatured(true);

            $this->em->persist($articleImage);

            $this->logger->info('Press image attached to article', [
                'pressReleaseId' => $pressRelease->getId(),
                'filename' => $newFilename,
                'dimensions' => "{$width}x{$height}",
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to attach press image', [
                'pressReleaseId' => $pressRelease->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
