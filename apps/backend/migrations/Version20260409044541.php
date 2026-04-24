<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409044541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CASCADE/SET NULL to FK constraints for article deletion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT fk_195e7fc5f8598e2c');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT fk_195e7fc57294869c');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT FK_195E7FC5F8598E2C FOREIGN KEY (related_article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT FK_195E7FC57294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT fk_9b6ca7237294869c');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT FK_9B6CA7237294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT FK_9B6CA7237294869C');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT fk_9b6ca7237294869c FOREIGN KEY (article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT FK_195E7FC57294869C');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT FK_195E7FC5F8598E2C');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT fk_195e7fc57294869c FOREIGN KEY (article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT fk_195e7fc5f8598e2c FOREIGN KEY (related_article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
