<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Author;
use App\Entity\Image;
use App\Entity\PressRelease;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Message\Editorial\IngestArticleMessage;
use App\Message\Editorial\SyncArticleToVaultMessage;
use App\Message\TranslateArticleMessage;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Service\SourceAuthorResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class PressReleaseApproveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly SourceAuthorResolver $sourceAuthorResolver,
        private readonly Security $security,
        private readonly MessageBusInterface $messageBus,
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
        $locale = $data->getOriginalLanguage() ?? 'ro';
        $article = new Article();
        $article->setTitle($data->getTitle());
        $article->setLead($data->getLead());
        $article->setContent($data->getContent());
        $article->setStatus(ArticleStatus::NEW);
        $article->setTranslatableLocale($locale);
        $article->setContentHash($data->getContentHash());

        // Only set sourceEmail if present and no existing article has it (unique constraint)
        $sourceEmailId = $data->getSourceEmailId();
        if ($sourceEmailId !== null) {
            $existingArticle = $this->articleRepository->findOneBy(['sourceEmail' => $sourceEmailId]);
            if ($existingArticle === null) {
                $article->setSourceEmail($sourceEmailId);
            }
        }

        // Map category
        $category = $this->categoryRepository->findOneBy(['slug' => $data->getCategorySlug()]);
        if ($category === null) {
            $category = $this->categoryRepository->findOneBy(['slug' => 'societate']);
        }
        if ($category !== null) {
            $article->setCategory($category);
        }

        // Resolve author based on source type
        $this->assignAuthor($article, $data);

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

        $this->logger->info('Article created from PressRelease', [
            'articleId' => $article->getId(),
            'pressReleaseId' => $data->getId(),
            'sourceType' => $data->getSourceType()->value,
        ]);

        // Dispatch post-approval messages
        $this->dispatchPostApprovalMessages($article, $locale);

        return $data;
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

    private function dispatchPostApprovalMessages(Article $article, string $locale): void
    {
        $articleId = $article->getId();

        // Translate to non-original locales
        $targetLocales = array_values(array_diff(['ro', 'en', 'ru'], [$locale]));
        if ($targetLocales !== []) {
            $this->messageBus->dispatch(new TranslateArticleMessage(
                articleId: $articleId,
                locales: $targetLocales,
            ));
        }

        // AI ingestion
        $this->messageBus->dispatch(new IngestArticleMessage(
            articleId: $articleId,
        ));

        // Vault sync
        $this->messageBus->dispatch(new SyncArticleToVaultMessage(
            articleId: $articleId,
        ));

        $this->logger->info('Post-approval messages dispatched', [
            'articleId' => $articleId,
            'translateLocales' => $targetLocales,
        ]);
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

    /**
     * Extract domain from sender email and look up matching author.
     */
    private function resolveAuthorFromSenderEmail(string $senderEmail): ?Author
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
    private function extractDomain(string $email): ?string
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
