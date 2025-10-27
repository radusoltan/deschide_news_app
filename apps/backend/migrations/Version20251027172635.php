<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027172635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE thumbnail_profiles (id SERIAL NOT NULL, name VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, width INT NOT NULL, height INT NOT NULL, aspect_ratio VARCHAR(10) DEFAULT NULL, mode VARCHAR(20) NOT NULL, quality INT NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, category VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A2CBE8C35E237E06 ON thumbnail_profiles (name)');
        $this->addSql('CREATE INDEX idx_profile_name ON thumbnail_profiles (name)');
        $this->addSql('CREATE INDEX idx_profile_is_active ON thumbnail_profiles (is_active)');
        $this->addSql('CREATE INDEX idx_profile_category ON thumbnail_profiles (category)');
        $this->addSql('CREATE UNIQUE INDEX idx_profile_dimensions ON thumbnail_profiles (width, height, mode)');
        $this->addSql('COMMENT ON COLUMN thumbnail_profiles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN thumbnail_profiles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE thumbnails ALTER profile_id SET NOT NULL');
        $this->addSql('ALTER TABLE thumbnails ADD CONSTRAINT FK_52A4DF60CCFA12B8 FOREIGN KEY (profile_id) REFERENCES thumbnail_profiles (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE thumbnails DROP CONSTRAINT FK_52A4DF60CCFA12B8');
        $this->addSql('DROP TABLE thumbnail_profiles');
        $this->addSql('ALTER TABLE thumbnails ALTER profile_id DROP NOT NULL');
    }
}
