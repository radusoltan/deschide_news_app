<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407084423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sourceImageUrl to press_releases for scraping image pipeline';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ADD source_image_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases DROP source_image_url');
    }
}
