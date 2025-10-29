<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251029050110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add related_articles pivot table for article relationships';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE related_articles (article_id INT NOT NULL, related_article_id INT NOT NULL, PRIMARY KEY(article_id, related_article_id))');
        $this->addSql('CREATE INDEX IDX_195E7FC57294869C ON related_articles (article_id)');
        $this->addSql('CREATE INDEX IDX_195E7FC5F8598E2C ON related_articles (related_article_id)');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT FK_195E7FC57294869C FOREIGN KEY (article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT FK_195E7FC5F8598E2C FOREIGN KEY (related_article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT FK_195E7FC57294869C');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT FK_195E7FC5F8598E2C');
        $this->addSql('DROP TABLE related_articles');
    }
}
