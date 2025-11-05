<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103101020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE live_text_collaborators (id SERIAL NOT NULL, live_text_id INT NOT NULL, user_id INT NOT NULL, role VARCHAR(50) DEFAULT \'contributor\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_793F398E8507533 ON live_text_collaborators (live_text_id)');
        $this->addSql('CREATE INDEX IDX_793F398A76ED395 ON live_text_collaborators (user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_live_text_user ON live_text_collaborators (live_text_id, user_id)');
        $this->addSql('CREATE TABLE live_text_posts (id SERIAL NOT NULL, live_text_id INT NOT NULL, author_id INT NOT NULL, content TEXT NOT NULL, content_html TEXT DEFAULT NULL, is_key_point BOOLEAN DEFAULT false NOT NULL, position INT DEFAULT 0 NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8E5666FBF675F31B ON live_text_posts (author_id)');
        $this->addSql('CREATE INDEX idx_live_text_post_live_text ON live_text_posts (live_text_id)');
        $this->addSql('CREATE INDEX idx_live_text_post_published_at ON live_text_posts (published_at)');
        $this->addSql('CREATE INDEX idx_live_text_post_is_key_point ON live_text_posts (is_key_point)');
        $this->addSql('CREATE INDEX idx_live_text_post_position ON live_text_posts (position)');
        $this->addSql('CREATE TABLE live_texts (id SERIAL NOT NULL, author_id INT NOT NULL, category_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, start_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4EEF1EA2989D9B62 ON live_texts (slug)');
        $this->addSql('CREATE INDEX IDX_4EEF1EA2F675F31B ON live_texts (author_id)');
        $this->addSql('CREATE INDEX IDX_4EEF1EA212469DE2 ON live_texts (category_id)');
        $this->addSql('CREATE INDEX idx_live_text_status ON live_texts (status)');
        $this->addSql('CREATE INDEX idx_live_text_start_time ON live_texts (start_time)');
        $this->addSql('CREATE INDEX idx_live_text_end_time ON live_texts (end_time)');
        $this->addSql('ALTER TABLE live_text_collaborators ADD CONSTRAINT FK_793F398E8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_collaborators ADD CONSTRAINT FK_793F398A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_posts ADD CONSTRAINT FK_8E5666FBE8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_posts ADD CONSTRAINT FK_8E5666FBF675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_texts ADD CONSTRAINT FK_4EEF1EA2F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_texts ADD CONSTRAINT FK_4EEF1EA212469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_external_source_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE live_text_collaborators DROP CONSTRAINT FK_793F398E8507533');
        $this->addSql('ALTER TABLE live_text_collaborators DROP CONSTRAINT FK_793F398A76ED395');
        $this->addSql('ALTER TABLE live_text_posts DROP CONSTRAINT FK_8E5666FBE8507533');
        $this->addSql('ALTER TABLE live_text_posts DROP CONSTRAINT FK_8E5666FBF675F31B');
        $this->addSql('ALTER TABLE live_texts DROP CONSTRAINT FK_4EEF1EA2F675F31B');
        $this->addSql('ALTER TABLE live_texts DROP CONSTRAINT FK_4EEF1EA212469DE2');
        $this->addSql('DROP TABLE live_text_collaborators');
        $this->addSql('DROP TABLE live_text_posts');
        $this->addSql('DROP TABLE live_texts');
        $this->addSql('CREATE INDEX idx_external_source_id ON external_article_mappings (source, external_id)');
    }
}
