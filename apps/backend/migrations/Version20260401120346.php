<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401120346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translation tracking fields (translationStatus, translatedAt, translatedBy) to categories and authors';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE authors ADD translation_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE authors ADD translated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE authors ADD translated_by VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD translation_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD translated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD translated_by VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE authors DROP translation_status');
        $this->addSql('ALTER TABLE authors DROP translated_at');
        $this->addSql('ALTER TABLE authors DROP translated_by');
        $this->addSql('ALTER TABLE categories DROP translation_status');
        $this->addSql('ALTER TABLE categories DROP translated_at');
        $this->addSql('ALTER TABLE categories DROP translated_by');
    }
}
