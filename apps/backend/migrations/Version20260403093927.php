<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403093927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unified Editorial Queue: add sourceType, contentHash, sourceName, originalLanguage, rejectionReason to PressRelease; make email fields nullable';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE press_releases ADD content_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD source_type VARCHAR(255) DEFAULT \'email\' NOT NULL');
        $this->addSql('ALTER TABLE press_releases ADD original_language VARCHAR(5) DEFAULT \'ro\'');
        $this->addSql('ALTER TABLE press_releases ADD source_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD rejection_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER source_email_id DROP NOT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER sender_address DROP NOT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER sender_name DROP NOT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER email_subject DROP NOT NULL');
        $this->addSql('CREATE INDEX idx_press_release_content_hash ON press_releases (content_hash)');
        $this->addSql('CREATE INDEX idx_press_release_source_type ON press_releases (source_type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_hash_source_type ON press_releases (content_hash, source_type)');

        // Backfill existing rows
        $this->addSql("UPDATE press_releases SET source_type = 'email', source_name = 'email:zoho' WHERE source_name IS NULL");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_press_release_content_hash');
        $this->addSql('DROP INDEX idx_press_release_source_type');
        $this->addSql('DROP INDEX uniq_content_hash_source_type');
        $this->addSql('ALTER TABLE press_releases DROP content_hash');
        $this->addSql('ALTER TABLE press_releases DROP source_type');
        $this->addSql('ALTER TABLE press_releases DROP original_language');
        $this->addSql('ALTER TABLE press_releases DROP source_name');
        $this->addSql('ALTER TABLE press_releases DROP rejection_reason');
        $this->addSql('ALTER TABLE press_releases ALTER source_email_id SET NOT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER sender_address SET NOT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER sender_name SET NOT NULL');
        $this->addSql('ALTER TABLE press_releases ALTER email_subject SET NOT NULL');
    }
}
