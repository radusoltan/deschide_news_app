<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027153413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE author_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE category_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE ext_translations_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE refresh_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE image_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE thumbnail_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE messenger_messages_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE article_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE article_image_id_seq CASCADE');
        $this->addSql('CREATE TABLE test_article_translations (id SERIAL NOT NULL, locale VARCHAR(8) NOT NULL, object_class VARCHAR(191) NOT NULL, field VARCHAR(32) NOT NULL, foreign_key VARCHAR(64) NOT NULL, content TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX test_article_translation_idx ON test_article_translations (locale, object_class, field, foreign_key)');
        $this->addSql('CREATE TABLE test_articles (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN test_articles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN test_articles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE article_image DROP CONSTRAINT fk_b28a764e3da5256d');
        $this->addSql('ALTER TABLE article_image DROP CONSTRAINT fk_b28a764e7294869c');
        $this->addSql('ALTER TABLE article_author DROP CONSTRAINT fk_d7684f487294869c');
        $this->addSql('ALTER TABLE article_author DROP CONSTRAINT fk_d7684f48f675f31b');
        $this->addSql('ALTER TABLE thumbnail DROP CONSTRAINT fk_c35726e63da5256d');
        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e6612469de2');
        $this->addSql('DROP TABLE article_image');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE article_author');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE ext_translations');
        $this->addSql('DROP TABLE image');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE thumbnail');
        $this->addSql('DROP TABLE article');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE author_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE category_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE ext_translations_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE refresh_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE image_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE thumbnail_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE messenger_messages_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE article_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE article_image_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE article_image (id SERIAL NOT NULL, article_id INT NOT NULL, image_id INT NOT NULL, "position" INT NOT NULL, is_featured BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_article_image_featured ON article_image (article_id, is_featured)');
        $this->addSql('CREATE INDEX idx_article_image_position ON article_image (article_id, "position")');
        $this->addSql('CREATE INDEX idx_b28a764e3da5256d ON article_image (image_id)');
        $this->addSql('CREATE INDEX idx_b28a764e7294869c ON article_image (article_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_article_image ON article_image (article_id, image_id)');
        $this->addSql('COMMENT ON COLUMN article_image.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, username VARCHAR(180) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_8d93d649e7927c74 ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_8d93d649f85e0677 ON "user" (username)');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "user".updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE article_author (article_id INT NOT NULL, author_id INT NOT NULL, PRIMARY KEY(article_id, author_id))');
        $this->addSql('CREATE INDEX idx_d7684f487294869c ON article_author (article_id)');
        $this->addSql('CREATE INDEX idx_d7684f48f675f31b ON article_author (author_id)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE author (id SERIAL NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, slug VARCHAR(255) NOT NULL, bio TEXT DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, twitter VARCHAR(255) DEFAULT NULL, avatar_path VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_bdafd8c8989d9b62 ON author (slug)');
        $this->addSql('COMMENT ON COLUMN author.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN author.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE ext_translations (id SERIAL NOT NULL, locale VARCHAR(8) NOT NULL, object_class VARCHAR(191) NOT NULL, field VARCHAR(32) NOT NULL, foreign_key VARCHAR(64) NOT NULL, content TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX lookup_unique_idx ON ext_translations (foreign_key, locale, object_class, field)');
        $this->addSql('CREATE TABLE image (id SERIAL NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, width INT NOT NULL, height INT NOT NULL, hash VARCHAR(64) NOT NULL, dominant_color VARCHAR(7) DEFAULT NULL, blur_placeholder TEXT DEFAULT NULL, alt_text VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, source TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_image_hash ON image (hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_c53d045fd1b862b8 ON image (hash)');
        $this->addSql('COMMENT ON COLUMN image.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN image.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE category (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_64c19c1989d9b62 ON category (slug)');
        $this->addSql('COMMENT ON COLUMN category.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN category.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE refresh_tokens (id SERIAL NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_9bace7e1c74f2195 ON refresh_tokens (refresh_token)');
        $this->addSql('CREATE TABLE thumbnail (id SERIAL NOT NULL, image_id INT NOT NULL, profile VARCHAR(50) NOT NULL, format VARCHAR(10) NOT NULL, path VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, file_size INT NOT NULL, status VARCHAR(20) NOT NULL, crop_json TEXT DEFAULT NULL, has_custom_crop BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_c35726e63da5256d ON thumbnail (image_id)');
        $this->addSql('CREATE INDEX idx_thumbnail_custom_crop ON thumbnail (has_custom_crop)');
        $this->addSql('CREATE INDEX idx_thumbnail_status ON thumbnail (status)');
        $this->addSql('CREATE UNIQUE INDEX unique_image_profile_format ON thumbnail (image_id, profile, format)');
        $this->addSql('COMMENT ON COLUMN thumbnail.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN thumbnail.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE article (id SERIAL NOT NULL, category_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, lead TEXT DEFAULT NULL, content TEXT NOT NULL, status VARCHAR(20) NOT NULL, badge VARCHAR(20) DEFAULT NULL, is_featured BOOLEAN NOT NULL, view_count INT NOT NULL, reading_time INT DEFAULT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_23a0e6612469de2 ON article (category_id)');
        $this->addSql('CREATE INDEX idx_article_featured ON article (is_featured)');
        $this->addSql('CREATE INDEX idx_article_published_at ON article (published_at)');
        $this->addSql('CREATE INDEX idx_article_status ON article (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_23a0e66989d9b62 ON article (slug)');
        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT fk_b28a764e3da5256d FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT fk_b28a764e7294869c FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_author ADD CONSTRAINT fk_d7684f487294869c FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_author ADD CONSTRAINT fk_d7684f48f675f31b FOREIGN KEY (author_id) REFERENCES author (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE thumbnail ADD CONSTRAINT fk_c35726e63da5256d FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT fk_23a0e6612469de2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE test_article_translations');
        $this->addSql('DROP TABLE test_articles');
    }
}
