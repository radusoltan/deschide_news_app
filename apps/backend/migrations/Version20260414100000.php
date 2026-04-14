<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add partial unique index on press_releases.source_url (WHERE NOT NULL) to prevent scraper duplicates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_pr_source_url ON press_releases (source_url) WHERE source_url IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_pr_source_url');
    }
}
