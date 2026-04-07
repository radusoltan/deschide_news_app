<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407072448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add originalTitle and originalContent to press_releases for source preservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ADD original_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD original_content TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases DROP original_title');
        $this->addSql('ALTER TABLE press_releases DROP original_content');
    }
}
