<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Newscoop import infrastructure tables
 */
final class Version20251101114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create newscoop_id_mapping and migration_log tables for data import';
    }

    public function up(Schema $schema): void
    {
        // newscoop_id_mapping: Maps Newscoop IDs to news_app IDs
        $this->addSql('
            CREATE TABLE IF NOT EXISTS newscoop_id_mapping (
                id SERIAL PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                newscoop_id INT NOT NULL,
                news_app_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (entity_type, newscoop_id)
            )
        ');

        $this->addSql('CREATE INDEX idx_mapping_entity_type ON newscoop_id_mapping (entity_type)');
        $this->addSql('CREATE INDEX idx_mapping_newscoop_id ON newscoop_id_mapping (newscoop_id)');
        $this->addSql('CREATE INDEX idx_mapping_news_app_id ON newscoop_id_mapping (news_app_id)');

        // migration_log: Logs import operations
        $this->addSql('
            CREATE TABLE IF NOT EXISTS migration_log (
                id SERIAL PRIMARY KEY,
                batch_id VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT,
                newscoop_id VARCHAR(100),
                status VARCHAR(20) NOT NULL,
                error_message TEXT,
                metadata JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->addSql('CREATE INDEX idx_log_batch_id ON migration_log (batch_id)');
        $this->addSql('CREATE INDEX idx_log_entity_type ON migration_log (entity_type)');
        $this->addSql('CREATE INDEX idx_log_status ON migration_log (status)');
        $this->addSql('CREATE INDEX idx_log_created_at ON migration_log (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS newscoop_id_mapping CASCADE');
        $this->addSql('DROP TABLE IF EXISTS migration_log CASCADE');
    }
}
