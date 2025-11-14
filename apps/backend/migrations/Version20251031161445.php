<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031161445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE url_redirects (id SERIAL NOT NULL, old_url VARCHAR(500) NOT NULL, new_url VARCHAR(500) NOT NULL, locale VARCHAR(10) NOT NULL, http_status_code INT NOT NULL, type VARCHAR(50) NOT NULL, entity_id INT DEFAULT NULL, hit_count INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_accessed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_old_url ON url_redirects (old_url)');
        $this->addSql('CREATE INDEX idx_created_at ON url_redirects (created_at)');
        $this->addSql('CREATE INDEX idx_entity_type ON url_redirects (type, entity_id)');
        $this->addSql('COMMENT ON COLUMN url_redirects.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN url_redirects.last_accessed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE url_redirects');
    }
}
