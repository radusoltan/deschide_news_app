<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027171725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE thumbnails (id SERIAL NOT NULL, image_id INT NOT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, width INT NOT NULL, height INT NOT NULL, size INT NOT NULL, profile_id INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_thumbnail_image ON thumbnails (image_id)');
        $this->addSql('CREATE INDEX idx_thumbnail_profile ON thumbnails (profile_id)');
        $this->addSql('CREATE UNIQUE INDEX idx_image_profile_unique ON thumbnails (image_id, profile_id)');
        $this->addSql('COMMENT ON COLUMN thumbnails.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE thumbnails ADD CONSTRAINT FK_52A4DF603DA5256D FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE thumbnails DROP CONSTRAINT FK_52A4DF603DA5256D');
        $this->addSql('DROP TABLE thumbnails');
    }
}
