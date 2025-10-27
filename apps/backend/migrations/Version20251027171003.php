<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027171003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_image (id SERIAL NOT NULL, article_id INT NOT NULL, image_id INT NOT NULL, position INT DEFAULT 0 NOT NULL, is_featured BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_article_image_article ON article_image (article_id)');
        $this->addSql('CREATE INDEX idx_article_image_image ON article_image (image_id)');
        $this->addSql('CREATE INDEX idx_article_image_position ON article_image (article_id, position)');
        $this->addSql('CREATE INDEX idx_article_image_featured ON article_image (article_id, is_featured)');
        $this->addSql('CREATE UNIQUE INDEX idx_article_image_unique ON article_image (article_id, image_id)');
        $this->addSql('COMMENT ON COLUMN article_image.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT FK_B28A764E7294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT FK_B28A764E3DA5256D FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE article_image DROP CONSTRAINT FK_B28A764E7294869C');
        $this->addSql('ALTER TABLE article_image DROP CONSTRAINT FK_B28A764E3DA5256D');
        $this->addSql('DROP TABLE article_image');
    }
}
