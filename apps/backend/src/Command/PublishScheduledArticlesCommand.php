<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:publish-scheduled-articles',
    description: 'Publishes articles that are scheduled to be published at the current time'
)]
class PublishScheduledArticlesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get current time (rounded to the minute, ignore seconds)
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Chisinau'));
        $currentMinute = $now->format('Y-m-d H:i');

        $io->note(sprintf('Checking for articles scheduled at: %s (Europe/Chisinau)', $currentMinute));

        try {
            // Find articles that:
            // 1. Have status 'submitted'
            // 2. Have publishAt set
            // 3. publishAt time matches current minute (or is in the past)
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('a')
                ->from(Article::class, 'a')
                ->where('a.status = :status')
                ->andWhere('a.publishAt IS NOT NULL')
                ->andWhere('a.publishAt <= :now')
                ->setParameter('status', ArticleStatus::SUBMITTED)
                ->setParameter('now', $now);

            $articles = $qb->getQuery()->getResult();

            if (empty($articles)) {
                $io->success('No articles to publish at this time.');
                return Command::SUCCESS;
            }

            $publishedCount = 0;

            foreach ($articles as $article) {
                try {
                    // Publish the article
                    $article->setStatus(ArticleStatus::PUBLISHED);
                    $article->setPublishedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Chisinau')));

                    $this->entityManager->persist($article);

                    $this->logger->info('Published scheduled article', [
                        'article_id' => $article->getId(),
                        'title' => $article->getTitle(),
                        'publish_at' => $article->getPublishAt()?->format('Y-m-d H:i:s'),
                        'published_at' => $article->getPublishedAt()?->format('Y-m-d H:i:s'),
                    ]);

                    $publishedCount++;

                    $io->writeln(sprintf(
                        '✓ Published: [%d] %s (scheduled for %s)',
                        $article->getId(),
                        $article->getTitle(),
                        $article->getPublishAt()?->format('Y-m-d H:i')
                    ));
                } catch (\Exception $e) {
                    $this->logger->error('Failed to publish scheduled article', [
                        'article_id' => $article->getId(),
                        'error' => $e->getMessage(),
                    ]);

                    $io->error(sprintf(
                        'Failed to publish article [%d]: %s',
                        $article->getId(),
                        $e->getMessage()
                    ));
                }
            }

            // Flush all changes at once
            $this->entityManager->flush();

            $io->success(sprintf('Successfully published %d article(s).', $publishedCount));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Error in publish scheduled articles command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $io->error(sprintf('Error: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
