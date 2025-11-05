<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101183606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE newscoop_id_mapping_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE migration_log_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE newscoop_migration_log_id_seq CASCADE');
        $this->addSql('CREATE TABLE external_article_mappings (id SERIAL NOT NULL, article_id INT NOT NULL, source VARCHAR(50) NOT NULL, external_id VARCHAR(255) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_588A6A277294869C ON external_article_mappings (article_id)');
        $this->addSql('CREATE INDEX idx_external_source_id ON external_article_mappings (source, external_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_source_external_id ON external_article_mappings (source, external_id)');
        $this->addSql('COMMENT ON COLUMN external_article_mappings.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN external_article_mappings.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE external_article_mappings ADD CONSTRAINT FK_588A6A277294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE newscoop_migration_log');
        $this->addSql('DROP TABLE migration_log');
        $this->addSql('DROP TABLE newscoop_id_mapping');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE newscoop_id_mapping_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE migration_log_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE newscoop_migration_log_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE newscoop_migration_log (id SERIAL NOT NULL, entity_type VARCHAR(50) NOT NULL, newscoop_id VARCHAR(100) NOT NULL, deschide_id INT NOT NULL, status VARCHAR(20) DEFAULT \'success\' NOT NULL, error_message TEXT DEFAULT NULL, additional_data JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_migration_deschide_id ON newscoop_migration_log (deschide_id)');
        $this->addSql('CREATE INDEX idx_migration_entity_type ON newscoop_migration_log (entity_type)');
        $this->addSql('CREATE INDEX idx_migration_newscoop_id ON newscoop_migration_log (newscoop_id)');
        $this->addSql('CREATE INDEX idx_migration_status ON newscoop_migration_log (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_migration_entity_newscoop ON newscoop_migration_log (entity_type, newscoop_id)');
        $this->addSql('CREATE TABLE migration_log (id SERIAL NOT NULL, batch_id VARCHAR(50) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT DEFAULT NULL, newscoop_id VARCHAR(100) DEFAULT NULL, status VARCHAR(20) NOT NULL, error_message TEXT DEFAULT NULL, metadata JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_log_batch_id ON migration_log (batch_id)');
        $this->addSql('CREATE INDEX idx_log_created_at ON migration_log (created_at)');
        $this->addSql('CREATE INDEX idx_log_entity_type ON migration_log (entity_type)');
        $this->addSql('CREATE INDEX idx_log_status ON migration_log (status)');
        $this->addSql('CREATE TABLE newscoop_id_mapping (id SERIAL NOT NULL, entity_type VARCHAR(50) NOT NULL, newscoop_id INT NOT NULL, news_app_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_mapping_entity_type ON newscoop_id_mapping (entity_type)');
        $this->addSql('CREATE INDEX idx_mapping_news_app_id ON newscoop_id_mapping (news_app_id)');
        $this->addSql('CREATE INDEX idx_mapping_newscoop_id ON newscoop_id_mapping (newscoop_id)');
        $this->addSql('CREATE UNIQUE INDEX newscoop_id_mapping_entity_type_newscoop_id_key ON newscoop_id_mapping (entity_type, newscoop_id)');
        $this->addSql('ALTER TABLE external_article_mappings DROP CONSTRAINT FK_588A6A277294869C');
        $this->addSql('DROP TABLE external_article_mappings');
    }
}
