<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029065431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_locks (id SERIAL NOT NULL, article_id INT NOT NULL, locked_by_id INT NOT NULL, locked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8B861F867A88E00 ON article_locks (locked_by_id)');
        $this->addSql('CREATE INDEX idx_article_lock_article ON article_locks (article_id)');
        $this->addSql('CREATE INDEX idx_article_lock_expires ON article_locks (expires_at)');
        $this->addSql('COMMENT ON COLUMN article_locks.locked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN article_locks.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE article_locks ADD CONSTRAINT FK_8B861F867294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_locks ADD CONSTRAINT FK_8B861F867A88E00 FOREIGN KEY (locked_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE article_locks DROP CONSTRAINT FK_8B861F867294869C');
        $this->addSql('ALTER TABLE article_locks DROP CONSTRAINT FK_8B861F867A88E00');
        $this->addSql('DROP TABLE article_locks');
    }
}
