<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251109085818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isArchived field to categories table for archived categories (Complete Import - Option A)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories ADD is_archived BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_category_is_archived ON categories (is_archived)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX idx_category_is_archived');
        $this->addSql('ALTER TABLE categories DROP is_archived');
    }
}
