<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029051945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publish_at field for scheduled article publishing';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles ADD publish_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN articles.publish_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_article_publish_at ON articles (publish_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX idx_article_publish_at');
        $this->addSql('ALTER TABLE articles DROP publish_at');
    }
}
