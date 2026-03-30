<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330092431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE authors ALTER type DROP DEFAULT');
        $this->addSql('CREATE INDEX idx_author_type ON authors (type)');
        $this->addSql('ALTER TABLE menu_items ADD parent_id INT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN menu_items.created_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN menu_items.updated_at IS \'\'');
        $this->addSql('ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2A727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_menu_item_parent ON menu_items (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_author_type');
        $this->addSql('ALTER TABLE authors ALTER type SET DEFAULT \'journalist\'');
        $this->addSql('ALTER TABLE menu_items DROP CONSTRAINT FK_70B2CA2A727ACA70');
        $this->addSql('DROP INDEX idx_menu_item_parent');
        $this->addSql('ALTER TABLE menu_items DROP parent_id');
        $this->addSql('COMMENT ON COLUMN menu_items.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN menu_items.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
