<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416160814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add NotebookLM sync fields to topics table (Sprint 51a T51a.1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE topics ADD notebook_lm_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE topics ADD notebook_last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE topics ADD notebook_source_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE topics DROP notebook_lm_id');
        $this->addSql('ALTER TABLE topics DROP notebook_last_synced_at');
        $this->addSql('ALTER TABLE topics DROP notebook_source_count');
    }
}
