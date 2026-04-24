<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use App\Enum\AuthorType;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup:author-sources',
    description: 'Creates Author records for news agencies and institutional press offices'
)]
class SetupAuthorSourcesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorRepository $authorRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Setting up Author Sources (Agencies & Press Offices)');

        try {
        $sources = $this->getSourceDefinitions();
        $created = 0;
        $skipped = 0;

        foreach ($sources as $source) {
            $existing = $this->authorRepository->findOneBySlug($source['slug']);

            if ($existing !== null) {
                $io->note(\sprintf('Skipped (already exists): %s [%s]', $source['name'], $source['slug']));
                ++$skipped;

                continue;
            }

            $author = new Author();
            $author->setFirstName($source['firstName']);
            $author->setLastName($source['lastName']);
            $author->setEmail($source['email']);
            $author->setType($source['type']);
            $author->setStatus(AuthorStatus::ACTIVE);
            $author->setIsActive(true);
            $author->setBio($source['bio']);
            $author->setEmailDomain($source['emailDomain']);

            $this->entityManager->persist($author);
            $this->entityManager->flush();

            // Override the Gedmo-generated slug with our desired slug
            $author->setSlug($source['slug']);
            $this->entityManager->flush();

            $io->success(\sprintf(
                'Created: %s [slug: %s, type: %s, domain: %s]',
                $source['name'],
                $source['slug'],
                $source['type']->value,
                $source['emailDomain']
            ));
            ++$created;
        }

        $io->newLine();
        $io->success(\sprintf(
            'Done! Created: %d, Skipped: %d, Total sources: %d',
            $created,
            $skipped,
            \count($sources)
        ));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array<int, array{name: string, firstName: string, lastName: string, slug: string, email: string, emailDomain: string, type: AuthorType, bio: string}>
     */
    private function getSourceDefinitions(): array
    {
        return [
            // === Agencies ===
            [
                'name' => 'IPN (Info-Prim Neo)',
                'firstName' => 'IPN',
                'lastName' => '(Info-Prim Neo)',
                'slug' => 'ipn',
                'email' => 'redactie@ipn.md',
                'emailDomain' => 'ipn.md',
                'type' => AuthorType::AGENCY,
                'bio' => 'Agenția de presă independentă Info-Prim Neo (IPN) — sursă de știri din Republica Moldova',
            ],
            [
                'name' => 'Moldpres',
                'firstName' => 'Moldpres',
                'lastName' => 'Agency',
                'slug' => 'moldpres',
                'email' => 'redactie@moldpres.md',
                'emailDomain' => 'moldpres.md',
                'type' => AuthorType::AGENCY,
                'bio' => 'Agenția Națională de Presă MOLDPRES — agenție de stat de știri a Republicii Moldova',
            ],

            // === Press Offices ===
            [
                'name' => 'Guvernul Republicii Moldova',
                'firstName' => 'Guvernul',
                'lastName' => 'Republicii Moldova',
                'slug' => 'guvernul-rm',
                'email' => 'presa@gov.md',
                'emailDomain' => 'gov.md',
                'type' => AuthorType::PRESS_OFFICE,
                'bio' => 'Oficiul de presă al Guvernului Republicii Moldova',
            ],
            [
                'name' => 'Președinția Republicii Moldova',
                'firstName' => 'Președinția',
                'lastName' => 'Republicii Moldova',
                'slug' => 'presedintia-rm',
                'email' => 'presa@presidency.md',
                'emailDomain' => 'presidency.md',
                'type' => AuthorType::PRESS_OFFICE,
                'bio' => 'Oficiul de presă al Președinției Republicii Moldova',
            ],
            [
                'name' => 'Parlamentul Republicii Moldova',
                'firstName' => 'Parlamentul',
                'lastName' => 'Republicii Moldova',
                'slug' => 'parlamentul-rm',
                'email' => 'presa@parlament.md',
                'emailDomain' => 'parlament.md',
                'type' => AuthorType::PRESS_OFFICE,
                'bio' => 'Oficiul de presă al Parlamentului Republicii Moldova',
            ],
            [
                'name' => 'Banca Națională a Moldovei',
                'firstName' => 'Banca Națională',
                'lastName' => 'a Moldovei',
                'slug' => 'bnm',
                'email' => 'presa@bnm.md',
                'emailDomain' => 'bnm.md',
                'type' => AuthorType::PRESS_OFFICE,
                'bio' => 'Oficiul de presă al Băncii Naționale a Moldovei',
            ],
            [
                'name' => 'Partidul Nostru',
                'firstName' => 'Partidul',
                'lastName' => 'Nostru',
                'slug' => 'partidul-nostru',
                'email' => 'presa@pnru.md',
                'emailDomain' => 'pnru.md',
                'type' => AuthorType::PRESS_OFFICE,
                'bio' => 'Oficiul de presă al formațiunii politice Partidul Nostru',
            ],
        ];
    }
}
