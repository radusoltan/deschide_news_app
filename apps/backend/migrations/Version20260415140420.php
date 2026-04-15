<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415140420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index (status, created_at) on press_releases for cursor pagination.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_pr_status_created ON press_releases (status, created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_pr_status_created');
    }
}
