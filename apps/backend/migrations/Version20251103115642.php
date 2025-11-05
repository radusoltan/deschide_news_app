<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103115642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE live_text_views (id SERIAL NOT NULL, live_text_id INT NOT NULL, user_id INT DEFAULT NULL, session_id VARCHAR(255) NOT NULL, time_spent INT DEFAULT 0 NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, viewed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_activity_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_17FB4086A76ED395 ON live_text_views (user_id)');
        $this->addSql('CREATE INDEX idx_live_text_view_live_text ON live_text_views (live_text_id)');
        $this->addSql('CREATE INDEX idx_live_text_view_session ON live_text_views (session_id)');
        $this->addSql('CREATE INDEX idx_live_text_view_ip ON live_text_views (ip_address)');
        $this->addSql('CREATE INDEX idx_live_text_view_viewed_at ON live_text_views (viewed_at)');
        $this->addSql('ALTER TABLE live_text_views ADD CONSTRAINT FK_17FB4086E8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_views ADD CONSTRAINT FK_17FB4086A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE live_text_views DROP CONSTRAINT FK_17FB4086E8507533');
        $this->addSql('ALTER TABLE live_text_views DROP CONSTRAINT FK_17FB4086A76ED395');
        $this->addSql('DROP TABLE live_text_views');
    }
}
