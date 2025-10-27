<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027164440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_author (article_id INT NOT NULL, author_id INT NOT NULL, PRIMARY KEY(article_id, author_id))');
        $this->addSql('CREATE INDEX IDX_D7684F487294869C ON article_author (article_id)');
        $this->addSql('CREATE INDEX IDX_D7684F48F675F31B ON article_author (author_id)');
        $this->addSql('CREATE TABLE authors (id SERIAL NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, slug VARCHAR(255) NOT NULL, bio TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, twitter VARCHAR(100) DEFAULT NULL, facebook VARCHAR(255) DEFAULT NULL, linkedin VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E0C2A51E7927C74 ON authors (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E0C2A51989D9B62 ON authors (slug)');
        $this->addSql('CREATE INDEX idx_author_slug ON authors (slug)');
        $this->addSql('CREATE INDEX idx_author_email ON authors (email)');
        $this->addSql('CREATE INDEX idx_author_status ON authors (status)');
        $this->addSql('CREATE INDEX idx_author_is_active ON authors (is_active)');
        $this->addSql('COMMENT ON COLUMN authors.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN authors.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE article_author ADD CONSTRAINT FK_D7684F487294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_author ADD CONSTRAINT FK_D7684F48F675F31B FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE article_author DROP CONSTRAINT FK_D7684F487294869C');
        $this->addSql('ALTER TABLE article_author DROP CONSTRAINT FK_D7684F48F675F31B');
        $this->addSql('DROP TABLE article_author');
        $this->addSql('DROP TABLE authors');
    }
}
