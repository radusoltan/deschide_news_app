<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260416074925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_sensitive and keywords fields to topics table for editorial taxonomy v2';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE topics ADD is_sensitive BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE topics ADD keywords JSON DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_topic_is_sensitive ON topics (is_sensitive) WHERE is_sensitive = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_topic_is_sensitive');
        $this->addSql('ALTER TABLE topics DROP is_sensitive');
        $this->addSql('ALTER TABLE topics DROP keywords');
    }
}
