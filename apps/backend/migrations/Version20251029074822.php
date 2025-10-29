<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029074822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE important_articles_list (id SERIAL NOT NULL, article_id INT NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EACDB8B27294869C ON important_articles_list (article_id)');
        $this->addSql('CREATE INDEX idx_important_articles_position ON important_articles_list (position)');
        $this->addSql('COMMENT ON COLUMN important_articles_list.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE important_articles_list ADD CONSTRAINT FK_EACDB8B27294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE important_articles_list DROP CONSTRAINT FK_EACDB8B27294869C');
        $this->addSql('DROP TABLE important_articles_list');
    }
}
