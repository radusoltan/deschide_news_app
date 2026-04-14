<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Author;
use App\Entity\Image;
use App\Entity\PressRelease;
use App\Enum\ArticleStatus;
use App\Enum\SourceType;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Service\RemoteImageDownloader;
use App\Service\SourceAuthorResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates an Article entity from a PressRelease, mapping all fields
 * and handling image attachment / remote image download.
 */
class ArticleFactoryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly SourceAuthorResolver $sourceAuthorResolver,
        private readonly RemoteImageDownloader $imageDownloader,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /**
     * Create and persist an Article from a PressRelease.
     *
     * The Article is persisted but NOT flushed — the caller controls flush timing.
     */
    public function createFromPressRelease(PressRelease $pressRelease): Article
    {
        $locale = $pressRelease->getOriginalLanguage() ?? 'ro';

        $article = new Article();
        $article->setTitle($pressRelease->getTitle());
        $article->setLead($pressRelease->getLead());
        $article->setContent($pressRelease->getContent());
        $article->setStatus(ArticleStatus::NEW);
        $article->setTranslatableLocale($locale);
        $article->setContentHash($pressRelease->getContentHash());

        // Only set sourceEmail if present and no existing article has it (unique constraint)
        $sourceEmailId = $pressRelease->getSourceEmailId();
        if ($sourceEmailId !== null) {
            $existingArticle = $this->articleRepository->findOneBy(['sourceEmail' => $sourceEmailId]);
            if ($existingArticle === null) {
                $article->setSourceEmail($sourceEmailId);
            }
        }

        // Populate AI metadata if this is an AI-generated PressRelease
        if ($pressRelease->isAiGenerated()) {
            $article->setAiGenerated(true);
            $article->setAiConfidenceScore($pressRelease->getAiConfidenceScore());
            $article->setAiSourceCount($pressRelease->getAiSourceCount());
            $article->setSourceClusterId($pressRelease->getSourceClusterId());
        }

        // Map category
        $category = $this->categoryRepository->findOneBy(['slug' => $pressRelease->getCategorySlug()]);
        if ($category === null) {
            $category = $this->categoryRepository->findOneBy(['slug' => 'societate']);
        }
        if ($category !== null) {
            $article->setCategory($category);
        }

        // Resolve author based on source type
        $this->assignAuthor($article, $pressRelease);

        $this->em->persist($article);

        // Attach image if press release has one (email attachment)
        if ($pressRelease->hasAttachment()) {
            $this->attachImage($article, $pressRelease);
        }

        // Download remote image from scraping/aggregator source
        if ($pressRelease->getSourceImageUrl() && $article->getArticleImages()->isEmpty()) {
            $this->downloadRemoteImage($article, $pressRelease);
        }

        return $article;
    }

    private function assignAuthor(Article $article, PressRelease $data): void
    {
        if ($data->getSourceType() === SourceType::SCRAPE && $data->getSourceName() !== null) {
            // For scrape sources, resolve author by source name
            $author = $this->sourceAuthorResolver->resolve($data->getSourceName());
            $article->addAuthor($author);
        } elseif ($data->getSenderAddress() !== null) {
            // For email sources, resolve author by sender email domain
            $author = $this->resolveAuthorFromSenderEmail($data->getSenderAddress());
            if ($author !== null) {
                $article->addAuthor($author);
            }
        }
    }

    private function downloadRemoteImage(Article $article, PressRelease $pressRelease): void
    {
        try {
            $image = $this->imageDownloader->download($pressRelease->getSourceImageUrl());
            if ($image !== null) {
                $image->setAlt($article->getTitle());
                $this->em->persist($image);

                $articleImage = new ArticleImage();
                $articleImage->setArticle($article);
                $articleImage->setImage($image);
                $articleImage->setPosition(0);
                $articleImage->setIsFeatured(true);
                $this->em->persist($articleImage);

                $this->logger->info('Remote image attached to article', [
                    'articleId' => $article->getId(),
                    'sourceUrl' => mb_substr($pressRelease->getSourceImageUrl(), 0, 100),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to download remote image', [
                'url' => $pressRelease->getSourceImageUrl(),
                'error' => $e->getMessage(),
            ]);
        }
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

            // Create Image entity (without VichUploader -- set fields directly)
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

    /**
     * Extract domain from sender email and look up matching author.
     */
    public function resolveAuthorFromSenderEmail(string $senderEmail): ?Author
    {
        $domain = $this->extractDomain($senderEmail);
        if ($domain === null) {
            return null;
        }

        return $this->authorRepository->findByEmailDomain($domain);
    }

    /**
     * Extract the domain part from an email address.
     */
    public function extractDomain(string $email): ?string
    {
        // Handle "Name <email@domain>" format
        if (preg_match('/<([^>]+)>/', $email, $matches)) {
            $email = $matches[1];
        }

        $email = trim($email);

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return null;
        }

        $domain = substr($email, $atPos + 1);
        $domain = strtolower(trim($domain));

        return $domain !== '' ? $domain : null;
    }
}
