<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create newscoop_migration_log table for existing import commands
 */
final class Version20251101115000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create newscoop_migration_log table for existing import infrastructure';
    }

    public function up(Schema $schema): void
    {
        // Create newscoop_migration_log table
        $this->addSql('
            CREATE TABLE IF NOT EXISTS newscoop_migration_log (
                id SERIAL PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                newscoop_id VARCHAR(100) NOT NULL,
                deschide_id INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'success\',
                error_message TEXT,
                additional_data JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->addSql('CREATE INDEX idx_migration_entity_type ON newscoop_migration_log (entity_type)');
        $this->addSql('CREATE INDEX idx_migration_newscoop_id ON newscoop_migration_log (newscoop_id)');
        $this->addSql('CREATE INDEX idx_migration_deschide_id ON newscoop_migration_log (deschide_id)');
        $this->addSql('CREATE INDEX idx_migration_status ON newscoop_migration_log (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_migration_entity_newscoop ON newscoop_migration_log (entity_type, newscoop_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS newscoop_migration_log CASCADE');
    }
}
