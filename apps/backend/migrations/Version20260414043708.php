<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260414043708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AI metadata fields (confidence, source count, cluster ID) to press_releases';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE press_releases ADD ai_confidence_score DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD ai_source_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD source_cluster_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE press_releases DROP ai_confidence_score');
        $this->addSql('ALTER TABLE press_releases DROP ai_source_count');
        $this->addSql('ALTER TABLE press_releases DROP source_cluster_id');
    }
}
