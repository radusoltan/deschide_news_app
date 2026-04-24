<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409090154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('COMMENT ON COLUMN app_settings.updated_at IS \'\'');
        $this->addSql('ALTER TABLE articles DROP CONSTRAINT fk_bfdd316812469de2');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT FK_BFDD316812469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_ab_tests DROP CONSTRAINT fk_f74daa4bb03a8386');
        $this->addSql('ALTER TABLE live_text_ab_tests ALTER created_by_id DROP NOT NULL');
        $this->addSql('ALTER TABLE live_text_ab_tests ADD CONSTRAINT FK_F74DAA4BB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_collaborators DROP CONSTRAINT fk_793f398e8507533');
        $this->addSql('ALTER TABLE live_text_collaborators DROP CONSTRAINT fk_793f398a76ed395');
        $this->addSql('ALTER TABLE live_text_collaborators ADD CONSTRAINT FK_793F398E8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_collaborators ADD CONSTRAINT FK_793F398A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_match_events DROP CONSTRAINT fk_63d817661c1c536c');
        $this->addSql('ALTER TABLE live_text_match_events ADD CONSTRAINT FK_63D817661C1C536C FOREIGN KEY (sport_match_id) REFERENCES live_text_sport_matches (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_post_engagements DROP CONSTRAINT fk_36d47c7e4b89032c');
        $this->addSql('ALTER TABLE live_text_post_engagements ADD CONSTRAINT FK_36D47C7E4B89032C FOREIGN KEY (post_id) REFERENCES live_text_posts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_posts DROP CONSTRAINT fk_8e5666fbe8507533');
        $this->addSql('ALTER TABLE live_text_posts DROP CONSTRAINT fk_8e5666fbf675f31b');
        $this->addSql('ALTER TABLE live_text_posts ALTER author_id DROP NOT NULL');
        $this->addSql('ALTER TABLE live_text_posts ADD CONSTRAINT FK_8E5666FBE8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_posts ADD CONSTRAINT FK_8E5666FBF675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_text_sport_matches DROP CONSTRAINT fk_e2dd35dce8507533');
        $this->addSql('ALTER TABLE live_text_sport_matches ADD CONSTRAINT FK_E2DD35DCE8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE live_texts DROP CONSTRAINT fk_4eef1ea2f675f31b');
        $this->addSql('ALTER TABLE live_texts ALTER author_id DROP NOT NULL');
        $this->addSql('ALTER TABLE live_texts ADD CONSTRAINT FK_4EEF1EA2F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT fk_9b6ca723953c1c61');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT fk_9b6ca7232ffd4fd3');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT FK_9B6CA723953C1C61 FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT FK_9B6CA7232FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('COMMENT ON COLUMN app_settings.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE articles DROP CONSTRAINT FK_BFDD316812469DE2');
        $this->addSql('ALTER TABLE articles ADD CONSTRAINT fk_bfdd316812469de2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_ab_tests DROP CONSTRAINT FK_F74DAA4BB03A8386');
        $this->addSql('ALTER TABLE live_text_ab_tests ALTER created_by_id SET NOT NULL');
        $this->addSql('ALTER TABLE live_text_ab_tests ADD CONSTRAINT fk_f74daa4bb03a8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_collaborators DROP CONSTRAINT FK_793F398E8507533');
        $this->addSql('ALTER TABLE live_text_collaborators DROP CONSTRAINT FK_793F398A76ED395');
        $this->addSql('ALTER TABLE live_text_collaborators ADD CONSTRAINT fk_793f398e8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_collaborators ADD CONSTRAINT fk_793f398a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_match_events DROP CONSTRAINT FK_63D817661C1C536C');
        $this->addSql('ALTER TABLE live_text_match_events ADD CONSTRAINT fk_63d817661c1c536c FOREIGN KEY (sport_match_id) REFERENCES live_text_sport_matches (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_post_engagements DROP CONSTRAINT FK_36D47C7E4B89032C');
        $this->addSql('ALTER TABLE live_text_post_engagements ADD CONSTRAINT fk_36d47c7e4b89032c FOREIGN KEY (post_id) REFERENCES live_text_posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_posts DROP CONSTRAINT FK_8E5666FBE8507533');
        $this->addSql('ALTER TABLE live_text_posts DROP CONSTRAINT FK_8E5666FBF675F31B');
        $this->addSql('ALTER TABLE live_text_posts ALTER author_id SET NOT NULL');
        $this->addSql('ALTER TABLE live_text_posts ADD CONSTRAINT fk_8e5666fbe8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_posts ADD CONSTRAINT fk_8e5666fbf675f31b FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_sport_matches DROP CONSTRAINT FK_E2DD35DCE8507533');
        $this->addSql('ALTER TABLE live_text_sport_matches ADD CONSTRAINT fk_e2dd35dce8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_texts DROP CONSTRAINT FK_4EEF1EA2F675F31B');
        $this->addSql('ALTER TABLE live_texts ALTER author_id SET NOT NULL');
        $this->addSql('ALTER TABLE live_texts ADD CONSTRAINT fk_4eef1ea2f675f31b FOREIGN KEY (author_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT FK_9B6CA7232FFD4FD3');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT FK_9B6CA723953C1C61');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT fk_9b6ca7232ffd4fd3 FOREIGN KEY (processed_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT fk_9b6ca723953c1c61 FOREIGN KEY (source_id) REFERENCES sources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
