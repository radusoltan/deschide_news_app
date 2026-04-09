<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260409045746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase source_image_url column to varchar(2048) to prevent truncation errors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ALTER source_image_url TYPE VARCHAR(2048)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ALTER source_image_url TYPE VARCHAR(500)');
    }
}
