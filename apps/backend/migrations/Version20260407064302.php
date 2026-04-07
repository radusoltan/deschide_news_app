<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407064302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create aggregator_runs table for persisting aggregator run history';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE aggregator_runs (id UUID NOT NULL, source VARCHAR(100) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, articles_found INT NOT NULL, duplicates_skipped INT NOT NULL, errors_count INT NOT NULL, error_details JSON DEFAULT NULL, triggered_by VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_agg_run_started ON aggregator_runs (started_at)');
        $this->addSql('CREATE INDEX idx_agg_run_source ON aggregator_runs (source)');
        $this->addSql('CREATE INDEX idx_agg_run_status ON aggregator_runs (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE aggregator_runs');
    }
}
