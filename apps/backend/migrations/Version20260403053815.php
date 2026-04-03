<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403053815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create AI entities: ai_conversations, ai_messages, ai_prompt_templates';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_conversations (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, agent_type VARCHAR(50) DEFAULT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, message_count INT DEFAULT 0 NOT NULL, total_tokens_used INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ai_conv_user ON ai_conversations (user_id)');
        $this->addSql('CREATE INDEX idx_ai_conv_created ON ai_conversations (created_at)');
        $this->addSql('CREATE INDEX idx_ai_conv_status ON ai_conversations (status)');
        $this->addSql('CREATE TABLE ai_messages (id UUID NOT NULL, role VARCHAR(20) NOT NULL, content TEXT NOT NULL, model VARCHAR(50) DEFAULT NULL, agent_type VARCHAR(50) DEFAULT NULL, tokens_used INT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, conversation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ai_msg_conversation ON ai_messages (conversation_id)');
        $this->addSql('CREATE INDEX idx_ai_msg_created ON ai_messages (created_at)');
        $this->addSql('CREATE INDEX idx_ai_msg_role ON ai_messages (role)');
        $this->addSql('CREATE TABLE ai_prompt_templates (id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, category VARCHAR(50) NOT NULL, agent_type VARCHAR(50) NOT NULL, prompt_template TEXT NOT NULL, required_fields JSON NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_ai_tpl_category ON ai_prompt_templates (category)');
        $this->addSql('CREATE INDEX idx_ai_tpl_agent_type ON ai_prompt_templates (agent_type)');
        $this->addSql('CREATE INDEX idx_ai_tpl_active ON ai_prompt_templates (is_active)');
        $this->addSql('ALTER TABLE ai_conversations ADD CONSTRAINT FK_F36727D7A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ai_messages ADD CONSTRAINT FK_C4E498F69AC0396 FOREIGN KEY (conversation_id) REFERENCES ai_conversations (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_conversations DROP CONSTRAINT FK_F36727D7A76ED395');
        $this->addSql('ALTER TABLE ai_messages DROP CONSTRAINT FK_C4E498F69AC0396');
        $this->addSql('DROP TABLE ai_conversations');
        $this->addSql('DROP TABLE ai_messages');
        $this->addSql('DROP TABLE ai_prompt_templates');
    }
}
