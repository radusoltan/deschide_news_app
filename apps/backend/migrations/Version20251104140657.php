<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104140657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE live_texts ADD template_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE live_texts ADD CONSTRAINT FK_4EEF1EA25DA0FB8 FOREIGN KEY (template_id) REFERENCES live_text_templates (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_4EEF1EA25DA0FB8 ON live_texts (template_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE live_texts DROP CONSTRAINT FK_4EEF1EA25DA0FB8');
        $this->addSql('DROP INDEX IDX_4EEF1EA25DA0FB8');
        $this->addSql('ALTER TABLE live_texts DROP template_id');
    }
}
