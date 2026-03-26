<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326070037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin_notifications table for notification system';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_notifications (id UUID NOT NULL, type VARCHAR(30) NOT NULL, importance VARCHAR(10) NOT NULL, title VARCHAR(255) NOT NULL, message TEXT DEFAULT NULL, related_entity_type VARCHAR(50) DEFAULT NULL, related_entity_id INT DEFAULT NULL, action_url VARCHAR(500) DEFAULT NULL, is_read BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, recipient_user_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BEC26FB4B15EFB97 ON admin_notifications (recipient_user_id)');
        $this->addSql('CREATE INDEX idx_notif_recipient_unread ON admin_notifications (recipient_user_id, is_read, created_at)');
        $this->addSql('CREATE INDEX idx_notif_type_created ON admin_notifications (type, created_at)');
        $this->addSql('CREATE INDEX idx_notif_cleanup ON admin_notifications (created_at, is_read)');
        $this->addSql('ALTER TABLE admin_notifications ADD CONSTRAINT FK_BEC26FB4B15EFB97 FOREIGN KEY (recipient_user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_notifications DROP CONSTRAINT FK_BEC26FB4B15EFB97');
        $this->addSql('DROP TABLE admin_notifications');
    }
}
