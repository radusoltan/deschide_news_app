<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210082400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add inMenu and inFooterMenu columns to categories table for menu reorganization';
    }

    public function up(Schema $schema): void
    {
        // Add inMenu and inFooterMenu columns to categories table
        $this->addSql('ALTER TABLE categories ADD COLUMN in_menu BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE categories ADD COLUMN in_footer_menu BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('CREATE INDEX idx_category_in_menu ON categories (in_menu)');
        $this->addSql('CREATE INDEX idx_category_in_footer_menu ON categories (in_footer_menu)');
    }

    public function down(Schema $schema): void
    {
        // Remove inMenu and inFooterMenu columns
        $this->addSql('DROP INDEX IF EXISTS idx_category_in_menu');
        $this->addSql('DROP INDEX IF EXISTS idx_category_in_footer_menu');
        $this->addSql('ALTER TABLE categories DROP COLUMN IF EXISTS in_menu');
        $this->addSql('ALTER TABLE categories DROP COLUMN IF EXISTS in_footer_menu');
    }
}
