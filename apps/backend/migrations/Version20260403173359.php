<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403173359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ingested_at timestamp to articles for AI ingestion tracking';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles ADD ingested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_article_ingested_at ON articles (ingested_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_article_ingested_at');
        $this->addSql('ALTER TABLE articles DROP ingested_at');
    }
}
