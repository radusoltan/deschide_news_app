<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408055306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_settings table for configurable parameters (auto-promote threshold)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_settings (key VARCHAR(100) NOT NULL, value TEXT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (key))');
        $this->addSql("COMMENT ON COLUMN app_settings.updated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_settings');
    }
}
