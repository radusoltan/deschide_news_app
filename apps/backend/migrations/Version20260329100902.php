<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329100902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE press_releases ADD attachment_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD attachment_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD attachment_mime_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD attachment_size INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE press_releases DROP attachment_filename');
        $this->addSql('ALTER TABLE press_releases DROP attachment_path');
        $this->addSql('ALTER TABLE press_releases DROP attachment_mime_type');
        $this->addSql('ALTER TABLE press_releases DROP attachment_size');
    }
}
