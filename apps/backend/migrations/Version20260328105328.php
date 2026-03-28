<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328105328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles ADD source_email VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD request_translation BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE articles ADD translation_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD translated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE articles ADD translated_by VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BFDD31687E9AA74B ON articles (source_email)');
        $this->addSql('ALTER TABLE live_text_sport_matches ALTER live_text_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_BFDD31687E9AA74B');
        $this->addSql('ALTER TABLE articles DROP source_email');
        $this->addSql('ALTER TABLE articles DROP request_translation');
        $this->addSql('ALTER TABLE articles DROP translation_status');
        $this->addSql('ALTER TABLE articles DROP translated_at');
        $this->addSql('ALTER TABLE articles DROP translated_by');
        $this->addSql('ALTER TABLE live_text_sport_matches ALTER live_text_id SET NOT NULL');
    }
}
