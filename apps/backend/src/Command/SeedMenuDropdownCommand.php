<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MenuItem;
use App\Enum\MenuItemType;
use App\Enum\MenuType;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:menu-dropdown',
    description: 'Seed a "Știri" dropdown with sub-items in the main menu',
)]
final class SeedMenuDropdownCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seed "Știri" Dropdown in Main Menu');

        $menuItemRepo = $this->em->getRepository(MenuItem::class);

        // Check if a dropdown already exists in main menu
        $existingDropdown = $menuItemRepo->findOneBy([
            'menu' => MenuType::MAIN,
            'type' => MenuItemType::DROPDOWN,
        ]);

        if ($existingDropdown) {
            $io->warning(\sprintf(
                'A dropdown already exists in the main menu (ID: %d, label: "%s"). Skipping.',
                $existingDropdown->getId(),
                $existingDropdown->getLabel(),
            ));

            return Command::SUCCESS;
        }

        // 1. Create the "Știri" dropdown at position 0
        $dropdown = new MenuItem();
        $dropdown->setMenu(MenuType::MAIN);
        $dropdown->setType(MenuItemType::DROPDOWN);
        $dropdown->setLabel('Știri');
        $dropdown->setPosition(0);
        $dropdown->setIsActive(true);
        $dropdown->setTranslatableLocale('ro');

        $this->em->persist($dropdown);
        $this->em->flush();

        $io->success(\sprintf('Created dropdown "Știri" (ID: %d)', $dropdown->getId()));

        // 2. Add EN/RU translations for the dropdown label
        $translationRepo = $this->em->getRepository(Translation::class);
        $translationRepo->translate($dropdown, 'label', 'en', 'News');
        $translationRepo->translate($dropdown, 'label', 'ru', 'Новости');
        $this->em->flush();

        $io->info('Added translations: EN="News", RU="Новости"');

        // 3. Move core news category items under the dropdown
        //    Politică, Societate, Economie, Externe are the primary news sections
        $newsLabels = ['Politică', 'Societate', 'Economie', 'Externe'];
        $movedCount = 0;
        $childPosition = 0;

        foreach ($newsLabels as $label) {
            $item = $menuItemRepo->findOneBy([
                'menu' => MenuType::MAIN,
                'type' => MenuItemType::CATEGORY,
                'label' => $label,
                'parent' => null,
            ]);

            if ($item === null) {
                $io->warning(\sprintf('Category item "%s" not found in main menu — skipping', $label));

                continue;
            }

            $item->setParent($dropdown);
            $item->setPosition($childPosition);
            $movedCount++;

            $io->writeln(\sprintf(
                '  Moved "%s" (ID: %d) → child position %d',
                $item->getLabel(),
                $item->getId(),
                $childPosition,
            ));

            $childPosition++;
        }

        // Flush parent changes so the next findBy sees correct parent_id values
        $this->em->flush();

        // 4. Reposition remaining top-level items (dropdown=0, rest start from 1)
        $remainingItems = $menuItemRepo->findBy(
            ['menu' => MenuType::MAIN, 'parent' => null],
            ['position' => 'ASC'],
        );

        $position = 0;
        foreach ($remainingItems as $item) {
            $item->setPosition($position);
            $position++;
        }

        $this->em->flush();

        $io->success(\sprintf(
            'Moved %d items under dropdown. Repositioned %d top-level items.',
            $movedCount,
            \count($remainingItems),
        ));

        // 5. Print final menu structure
        $io->section('Final Main Menu Structure');

        $topLevel = $menuItemRepo->findBy(
            ['menu' => MenuType::MAIN, 'parent' => null],
            ['position' => 'ASC'],
        );

        foreach ($topLevel as $item) {
            $suffix = $item->getType() === MenuItemType::DROPDOWN ? ' [DROPDOWN]' : '';
            $io->writeln(\sprintf(
                '#%d  pos:%d  %s  (%s)%s',
                $item->getId(),
                $item->getPosition(),
                $item->getLabel(),
                $item->getType()->value,
                $suffix,
            ));

            // Print children
            $children = $menuItemRepo->findBy(
                ['parent' => $item],
                ['position' => 'ASC'],
            );
            foreach ($children as $child) {
                $io->writeln(\sprintf(
                    '  └─ #%d  pos:%d  %s  (%s)',
                    $child->getId(),
                    $child->getPosition(),
                    $child->getLabel(),
                    $child->getType()->value,
                ));
            }
        }

        return Command::SUCCESS;
    }
}
