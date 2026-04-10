<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LiveTextTemplate;
use App\Enum\TemplateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-livetext-templates',
    description: 'Seeds predefined LiveText templates with default configurations'
)]
class SeedLiveTextTemplatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force seeding even if templates already exist'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        try {
        // Check if templates already exist
        $existingCount = $this->entityManager->getRepository(LiveTextTemplate::class)
            ->count(['isSystem' => true]);

        if ($existingCount > 0 && !$force) {
            $io->warning('System templates already exist. Use --force to reseed.');

            return Command::FAILURE;
        }

        if ($force && $existingCount > 0) {
            // Remove existing system templates
            $io->note('Removing existing system templates...');
            $existingTemplates = $this->entityManager->getRepository(LiveTextTemplate::class)
                ->findBy(['isSystem' => true]);
            foreach ($existingTemplates as $template) {
                $this->entityManager->remove($template);
            }
            $this->entityManager->flush();
        }

        $io->title('Seeding LiveText Templates');

        $templates = $this->getTemplateDefinitions();
        $count = 0;

        foreach ($templates as $templateData) {
            $template = new LiveTextTemplate();
            $template->setName($templateData['name']);
            $template->setDescription($templateData['description']);
            $template->setType($templateData['type']);
            $template->setConfig($templateData['config']);
            $template->setIsSystem(true);

            $this->entityManager->persist($template);
            ++$count;

            $io->success(\sprintf(
                'Created template: %s (%s)',
                $templateData['name'],
                $templateData['type']->value
            ));
        }

        $this->entityManager->flush();

        $io->success(\sprintf('Successfully seeded %d LiveText templates!', $count));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<int, array{name: string, description: string, type: TemplateType, config: array}>
     */
    private function getTemplateDefinitions(): array
    {
        return [
            // 1. Breaking News Template
            [
                'name' => 'Breaking News',
                'description' => 'For urgent breaking news coverage with red theme and prominent alerts',
                'type' => TemplateType::BREAKING_NEWS,
                'config' => [
                    'colors' => [
                        'primary' => '#ef4444',      // red-500
                        'secondary' => '#dc2626',    // red-600
                        'accent' => '#b91c1c',       // red-700
                        'background' => '#fef2f2',   // red-50
                        'text' => '#7f1d1d',         // red-900
                    ],
                    'layout' => [
                        'headerStyle' => 'banner',
                        'postStyle' => 'full',
                        'showTimeline' => true,
                        'sidebarPosition' => 'right',
                    ],
                    'features' => [
                        'enableReactions' => true,
                        'enableKeyPoints' => true,
                        'enableTimeline' => true,
                        'autoRefresh' => true,
                        'refreshInterval' => 30,
                    ],
                ],
            ],

            // 2. Sport Event Template
            [
                'name' => 'Sport Event',
                'description' => 'For live sports events with score tracking and dynamic updates',
                'type' => TemplateType::SPORT,
                'config' => [
                    'colors' => [
                        'primary' => '#10b981',      // green-500
                        'secondary' => '#059669',    // green-600
                        'accent' => '#047857',       // green-700
                        'background' => '#ecfdf5',   // green-50
                        'text' => '#064e3b',         // green-900
                    ],
                    'layout' => [
                        'headerStyle' => 'bold',
                        'postStyle' => 'compact',
                        'showTimeline' => true,
                        'sidebarPosition' => 'right',
                    ],
                    'features' => [
                        'enableReactions' => true,
                        'enableKeyPoints' => true,
                        'enableTimeline' => true,
                        'autoRefresh' => true,
                        'refreshInterval' => 15,
                    ],
                ],
            ],

            // 3. Conference Template
            [
                'name' => 'Conference',
                'description' => 'For conferences and speeches with speaker tracking',
                'type' => TemplateType::CONFERENCE,
                'config' => [
                    'colors' => [
                        'primary' => '#3b82f6',      // blue-500
                        'secondary' => '#2563eb',    // blue-600
                        'accent' => '#1d4ed8',       // blue-700
                        'background' => '#eff6ff',   // blue-50
                        'text' => '#1e3a8a',         // blue-900
                    ],
                    'layout' => [
                        'headerStyle' => 'minimal',
                        'postStyle' => 'card',
                        'showTimeline' => true,
                        'sidebarPosition' => 'left',
                    ],
                    'features' => [
                        'enableReactions' => true,
                        'enableKeyPoints' => true,
                        'enableTimeline' => true,
                        'autoRefresh' => true,
                        'refreshInterval' => 60,
                    ],
                ],
            ],

            // 4. Election Template
            [
                'name' => 'Election',
                'description' => 'For election coverage with results tracking and analysis',
                'type' => TemplateType::ELECTION,
                'config' => [
                    'colors' => [
                        'primary' => '#8b5cf6',      // purple-500
                        'secondary' => '#7c3aed',    // purple-600
                        'accent' => '#6d28d9',       // purple-700
                        'background' => '#faf5ff',   // purple-50
                        'text' => '#4c1d95',         // purple-900
                    ],
                    'layout' => [
                        'headerStyle' => 'bold',
                        'postStyle' => 'full',
                        'showTimeline' => true,
                        'sidebarPosition' => 'right',
                    ],
                    'features' => [
                        'enableReactions' => true,
                        'enableKeyPoints' => true,
                        'enableTimeline' => true,
                        'autoRefresh' => true,
                        'refreshInterval' => 45,
                    ],
                ],
            ],
        ];
    }
}
