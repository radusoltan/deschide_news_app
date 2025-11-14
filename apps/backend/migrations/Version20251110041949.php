<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110041949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add archive functionality: archived_at, archive_reason fields and indexes to articles table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD archive_reason VARCHAR(30) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN articles.archived_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_article_archived_at ON articles (archived_at)');
        $this->addSql('CREATE INDEX idx_article_status_archived ON articles (status, archived_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX idx_article_archived_at');
        $this->addSql('DROP INDEX idx_article_status_archived');
        $this->addSql('ALTER TABLE articles DROP archived_at');
        $this->addSql('ALTER TABLE articles DROP archive_reason');
    }
}
