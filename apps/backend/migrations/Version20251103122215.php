<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103122215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE live_text_match_events (id SERIAL NOT NULL, sport_match_id INT NOT NULL, event_type VARCHAR(50) NOT NULL, team VARCHAR(10) NOT NULL, player_name VARCHAR(255) DEFAULT NULL, second_player_name VARCHAR(255) DEFAULT NULL, event_minute INT NOT NULL, extra_time_minute INT DEFAULT NULL, score_after_event VARCHAR(20) DEFAULT NULL, description TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_63D817661C1C536C ON live_text_match_events (sport_match_id)');
        $this->addSql('CREATE TABLE live_text_sport_matches (id SERIAL NOT NULL, live_text_id INT NOT NULL, sport_type VARCHAR(50) NOT NULL, home_team VARCHAR(255) NOT NULL, away_team VARCHAR(255) NOT NULL, home_team_logo VARCHAR(500) DEFAULT NULL, away_team_logo VARCHAR(500) DEFAULT NULL, home_score INT NOT NULL, away_score INT NOT NULL, status VARCHAR(30) NOT NULL, current_minute INT DEFAULT NULL, current_period VARCHAR(50) DEFAULT NULL, venue VARCHAR(255) DEFAULT NULL, competition VARCHAR(255) DEFAULT NULL, scheduled_start_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, actual_start_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statistics JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E2DD35DCE8507533 ON live_text_sport_matches (live_text_id)');
        $this->addSql('ALTER TABLE live_text_match_events ADD CONSTRAINT FK_63D817661C1C536C FOREIGN KEY (sport_match_id) REFERENCES live_text_sport_matches (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_sport_matches ADD CONSTRAINT FK_E2DD35DCE8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE live_text_match_events DROP CONSTRAINT FK_63D817661C1C536C');
        $this->addSql('ALTER TABLE live_text_sport_matches DROP CONSTRAINT FK_E2DD35DCE8507533');
        $this->addSql('DROP TABLE live_text_match_events');
        $this->addSql('DROP TABLE live_text_sport_matches');
    }
}
