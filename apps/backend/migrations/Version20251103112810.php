<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103112810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE live_text_reactions (id SERIAL NOT NULL, live_text_post_id INT NOT NULL, user_id INT DEFAULT NULL, reaction_type VARCHAR(20) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_reaction_post ON live_text_reactions (live_text_post_id)');
        $this->addSql('CREATE INDEX idx_reaction_user ON live_text_reactions (user_id)');
        $this->addSql('CREATE INDEX idx_reaction_ip ON live_text_reactions (ip_address)');
        $this->addSql('CREATE INDEX idx_reaction_type ON live_text_reactions (reaction_type)');
        $this->addSql('CREATE UNIQUE INDEX unique_user_post_reaction ON live_text_reactions (live_text_post_id, user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_ip_post_reaction ON live_text_reactions (live_text_post_id, ip_address)');
        $this->addSql('ALTER TABLE live_text_reactions ADD CONSTRAINT FK_ECBA87CDDF7AA99 FOREIGN KEY (live_text_post_id) REFERENCES live_text_posts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_reactions ADD CONSTRAINT FK_ECBA87CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE live_text_reactions DROP CONSTRAINT FK_ECBA87CDDF7AA99');
        $this->addSql('ALTER TABLE live_text_reactions DROP CONSTRAINT FK_ECBA87CA76ED395');
        $this->addSql('DROP TABLE live_text_reactions');
    }
}
