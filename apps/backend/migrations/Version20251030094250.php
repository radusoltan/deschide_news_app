<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030094250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_image (id SERIAL NOT NULL, article_id INT NOT NULL, image_id INT NOT NULL, position INT DEFAULT 0 NOT NULL, is_featured BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_article_image_article ON article_image (article_id)');
        $this->addSql('CREATE INDEX idx_article_image_image ON article_image (image_id)');
        $this->addSql('CREATE INDEX idx_article_image_position ON article_image (article_id, position)');
        $this->addSql('CREATE INDEX idx_article_image_featured ON article_image (article_id, is_featured)');
        $this->addSql('CREATE UNIQUE INDEX idx_article_image_unique ON article_image (article_id, image_id)');
        $this->addSql('COMMENT ON COLUMN article_image.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE article_locks (id SERIAL NOT NULL, article_id INT NOT NULL, locked_by_id INT NOT NULL, locked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8B861F867A88E00 ON article_locks (locked_by_id)');
        $this->addSql('CREATE INDEX idx_article_lock_article ON article_locks (article_id)');
        $this->addSql('CREATE INDEX idx_article_lock_expires ON article_locks (expires_at)');
        $this->addSql('COMMENT ON COLUMN article_locks.locked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN article_locks.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE articles (id SERIAL NOT NULL, category_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, lead TEXT DEFAULT NULL, content TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, badge VARCHAR(20) DEFAULT NULL, is_featured BOOLEAN DEFAULT false NOT NULL, view_count INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, publish_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BFDD316812469DE2 ON articles (category_id)');
        $this->addSql('CREATE INDEX idx_article_status ON articles (status)');
        $this->addSql('CREATE INDEX idx_article_published_at ON articles (published_at)');
        $this->addSql('CREATE INDEX idx_article_publish_at ON articles (publish_at)');
        $this->addSql('CREATE INDEX idx_article_featured ON articles (is_featured)');
        $this->addSql('COMMENT ON COLUMN articles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN articles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN articles.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN articles.publish_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE article_author (article_id INT NOT NULL, author_id INT NOT NULL, PRIMARY KEY(article_id, author_id))');
        $this->addSql('CREATE INDEX IDX_D7684F487294869C ON article_author (article_id)');
        $this->addSql('CREATE INDEX IDX_D7684F48F675F31B ON article_author (author_id)');
        $this->addSql('CREATE TABLE related_articles (article_id INT NOT NULL, related_article_id INT NOT NULL, PRIMARY KEY(article_id, related_article_id))');
        $this->addSql('CREATE INDEX IDX_195E7FC57294869C ON related_articles (article_id)');
        $this->addSql('CREATE INDEX IDX_195E7FC5F8598E2C ON related_articles (related_article_id)');
        $this->addSql('CREATE TABLE authors (id SERIAL NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, slug VARCHAR(255) NOT NULL, bio TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, twitter VARCHAR(100) DEFAULT NULL, facebook VARCHAR(255) DEFAULT NULL, linkedin VARCHAR(255) DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E0C2A51E7927C74 ON authors (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E0C2A51989D9B62 ON authors (slug)');
        $this->addSql('CREATE INDEX idx_author_slug ON authors (slug)');
        $this->addSql('CREATE INDEX idx_author_email ON authors (email)');
        $this->addSql('CREATE INDEX idx_author_status ON authors (status)');
        $this->addSql('CREATE INDEX idx_author_is_active ON authors (is_active)');
        $this->addSql('COMMENT ON COLUMN authors.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN authors.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE categories (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, on_front_page BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_category_status ON categories (status)');
        $this->addSql('CREATE INDEX idx_category_on_front_page ON categories (on_front_page)');
        $this->addSql('COMMENT ON COLUMN categories.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN categories.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE ext_translations (id SERIAL NOT NULL, locale VARCHAR(8) NOT NULL, object_class VARCHAR(191) NOT NULL, field VARCHAR(32) NOT NULL, foreign_key VARCHAR(64) NOT NULL, content TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX lookup_unique_idx ON ext_translations (foreign_key, locale, object_class, field)');
        $this->addSql('CREATE TABLE images (id SERIAL NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, path VARCHAR(500) DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, size INT DEFAULT NULL, width INT NOT NULL, height INT NOT NULL, alt VARCHAR(255) DEFAULT NULL, caption TEXT DEFAULT NULL, description TEXT DEFAULT NULL, image_author VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E01FBE6A3C0BE965 ON images (filename)');
        $this->addSql('CREATE INDEX idx_image_filename ON images (filename)');
        $this->addSql('COMMENT ON COLUMN images.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN images.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE important_articles_list (id SERIAL NOT NULL, article_id INT NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EACDB8B27294869C ON important_articles_list (article_id)');
        $this->addSql('CREATE INDEX idx_important_articles_position ON important_articles_list (position)');
        $this->addSql('COMMENT ON COLUMN important_articles_list.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE refresh_tokens (id SERIAL NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens (refresh_token)');
        $this->addSql('CREATE TABLE test_article_translations (id SERIAL NOT NULL, locale VARCHAR(8) NOT NULL, object_class VARCHAR(191) NOT NULL, field VARCHAR(32) NOT NULL, foreign_key VARCHAR(64) NOT NULL, content TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX test_article_translation_idx ON test_article_translations (locale, object_class, field, foreign_key)');
        $this->addSql('CREATE TABLE test_articles (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN test_articles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN test_articles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE thumbnail_profiles (id SERIAL NOT NULL, name VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, width INT NOT NULL, height INT NOT NULL, aspect_ratio VARCHAR(10) DEFAULT NULL, mode VARCHAR(20) NOT NULL, quality INT NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, category VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A2CBE8C35E237E06 ON thumbnail_profiles (name)');
        $this->addSql('CREATE INDEX idx_profile_name ON thumbnail_profiles (name)');
        $this->addSql('CREATE INDEX idx_profile_is_active ON thumbnail_profiles (is_active)');
        $this->addSql('CREATE INDEX idx_profile_category ON thumbnail_profiles (category)');
        $this->addSql('CREATE UNIQUE INDEX idx_profile_dimensions ON thumbnail_profiles (width, height, mode)');
        $this->addSql('COMMENT ON COLUMN thumbnail_profiles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN thumbnail_profiles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE thumbnails (id SERIAL NOT NULL, image_id INT NOT NULL, profile_id INT NOT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, width INT NOT NULL, height INT NOT NULL, size INT NOT NULL, crop_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_thumbnail_image ON thumbnails (image_id)');
        $this->addSql('CREATE INDEX idx_thumbnail_profile ON thumbnails (profile_id)');
        $this->addSql('CREATE UNIQUE INDEX idx_image_profile_unique ON thumbnails (image_id, profile_id)');
        $this->addSql('COMMENT ON COLUMN thumbnails.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "user" (username)');
        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT FK_B28A764E7294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_image ADD CONSTRAINT FK_B28A764E3DA5256D FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_locks ADD CONSTRAINT FK_8B861F867294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_locks ADD CONSTRAINT FK_8B861F867A88E00 FOREIGN KEY (locked_by_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD316812469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_author ADD CONSTRAINT FK_D7684F487294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE article_author ADD CONSTRAINT FK_D7684F48F675F31B FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT FK_195E7FC57294869C FOREIGN KEY (article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE related_articles ADD CONSTRAINT FK_195E7FC5F8598E2C FOREIGN KEY (related_article_id) REFERENCES articles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE important_articles_list ADD CONSTRAINT FK_EACDB8B27294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE thumbnails ADD CONSTRAINT FK_52A4DF603DA5256D FOREIGN KEY (image_id) REFERENCES images (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE thumbnails ADD CONSTRAINT FK_52A4DF60CCFA12B8 FOREIGN KEY (profile_id) REFERENCES thumbnail_profiles (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE article_image DROP CONSTRAINT FK_B28A764E7294869C');
        $this->addSql('ALTER TABLE article_image DROP CONSTRAINT FK_B28A764E3DA5256D');
        $this->addSql('ALTER TABLE article_locks DROP CONSTRAINT FK_8B861F867294869C');
        $this->addSql('ALTER TABLE article_locks DROP CONSTRAINT FK_8B861F867A88E00');
        $this->addSql('ALTER TABLE articles DROP CONSTRAINT FK_BFDD316812469DE2');
        $this->addSql('ALTER TABLE article_author DROP CONSTRAINT FK_D7684F487294869C');
        $this->addSql('ALTER TABLE article_author DROP CONSTRAINT FK_D7684F48F675F31B');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT FK_195E7FC57294869C');
        $this->addSql('ALTER TABLE related_articles DROP CONSTRAINT FK_195E7FC5F8598E2C');
        $this->addSql('ALTER TABLE important_articles_list DROP CONSTRAINT FK_EACDB8B27294869C');
        $this->addSql('ALTER TABLE thumbnails DROP CONSTRAINT FK_52A4DF603DA5256D');
        $this->addSql('ALTER TABLE thumbnails DROP CONSTRAINT FK_52A4DF60CCFA12B8');
        $this->addSql('DROP TABLE article_image');
        $this->addSql('DROP TABLE article_locks');
        $this->addSql('DROP TABLE articles');
        $this->addSql('DROP TABLE article_author');
        $this->addSql('DROP TABLE related_articles');
        $this->addSql('DROP TABLE authors');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE ext_translations');
        $this->addSql('DROP TABLE images');
        $this->addSql('DROP TABLE important_articles_list');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE test_article_translations');
        $this->addSql('DROP TABLE test_articles');
        $this->addSql('DROP TABLE thumbnail_profiles');
        $this->addSql('DROP TABLE thumbnails');
        $this->addSql('DROP TABLE "user"');
    }
}
