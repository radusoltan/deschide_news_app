#!/usr/bin/env python3
"""
Convert all agents to YAML frontmatter format with proper tools and permission modes.
Implements Anthropic best practices for agent configuration.
"""

import os
import re
from pathlib import Path
from typing import List, Dict

# Agent configuration matrix based on Anthropic best practices
AGENT_CONFIGS = {
    # CRITICAL SECURITY - STRICT READ-ONLY
    'security-auditor': {
        'tools': ['Read', 'Grep', 'WebSearch', 'bash:curl'],
        'permissionMode': 'default',
        'color': 'red',
        'model': 'claude-3-5-sonnet-20241022'
    },

    # INFRASTRUCTURE - DANGEROUS OPERATIONS
    'database-engineer': {
        'tools': ['Read', 'bash:psql', 'bash:redis-cli', 'bash:curl', 'Grep', 'Glob'],
        'permissionMode': 'default',
        'color': 'blue',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'cache-sync-specialist': {
        'tools': ['Read', 'Write', 'Edit', 'Grep', 'Glob', 'Bash'],
        'permissionMode': 'acceptEdits',
        'color': 'blue',
        'model': 'claude-3-5-sonnet-20241022'
    },

    # TESTING AGENTS - READ-ONLY WITH PLAYWRIGHT
    'backend-api-tester': {
        'tools': ['Read', 'mcp__playwright__browser_navigate', 'mcp__playwright__browser_snapshot',
                  'mcp__playwright__browser_click', 'mcp__playwright__browser_evaluate',
                  'mcp__playwright__browser_network_requests'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'frontend-e2e-tester': {
        'tools': ['Read', 'mcp__playwright__browser_navigate', 'mcp__playwright__browser_snapshot',
                  'mcp__playwright__browser_click', 'mcp__playwright__browser_type',
                  'mcp__playwright__browser_evaluate'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'fullstack-integration-tester': {
        'tools': ['Read', 'mcp__playwright__browser_navigate', 'mcp__playwright__browser_snapshot',
                  'mcp__playwright__browser_click', 'mcp__playwright__browser_type',
                  'mcp__playwright__browser_network_requests'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'multilanguage-tester': {
        'tools': ['Read', 'mcp__playwright__browser_navigate', 'mcp__playwright__browser_snapshot',
                  'mcp__playwright__browser_evaluate'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'admin-panel-tester': {
        'tools': ['Read', 'mcp__playwright__browser_navigate', 'mcp__playwright__browser_snapshot',
                  'mcp__playwright__browser_click', 'mcp__playwright__browser_type',
                  'mcp__playwright__browser_fill_form'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'performance-tester': {
        'tools': ['Read', 'Bash', 'mcp__playwright__browser_navigate',
                  'mcp__playwright__browser_network_requests'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'manual-frontend-tester': {
        'tools': ['Read', 'Write', 'mcp__playwright__browser_navigate', 'mcp__playwright__browser_snapshot',
                  'mcp__playwright__browser_click', 'mcp__playwright__browser_take_screenshot'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },

    # DEVELOPMENT AGENTS - WRITE ACCESS
    'public-frontend-developer': {
        'tools': ['Read', 'Write', 'Edit', 'Grep', 'Glob', 'Bash', 'WebSearch', 'Skill'],
        'permissionMode': 'acceptEdits',
        'color': 'purple',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'premium-ui-designer': {
        'tools': ['Read', 'Write', 'Edit', 'Grep', 'Glob', 'WebSearch', 'Skill'],
        'permissionMode': 'acceptEdits',
        'color': 'purple',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'design-review-agent': {
        'tools': ['Read', 'Grep', 'Glob', 'WebSearch'],
        'permissionMode': 'default',
        'color': 'purple',
        'model': 'claude-3-5-sonnet-20241022'
    },

    # DATA IMPORT AGENTS - BULK WRITES
    'data-import-orchestrator': {
        'tools': ['Read', 'Write', 'Task', 'Memory'],
        'permissionMode': 'default',
        'color': 'gold',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'newscoop-importer': {
        'tools': ['Read', 'Write', 'Bash', 'bash:symfony', 'bash:psql'],
        'permissionMode': 'acceptEdits',
        'color': 'gold',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'csv-articles-importer': {
        'tools': ['Read', 'Write', 'Bash', 'bash:symfony'],
        'permissionMode': 'acceptEdits',
        'color': 'gold',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'import-validator': {
        'tools': ['Read', 'Bash', 'bash:symfony', 'bash:psql'],
        'permissionMode': 'default',
        'color': 'gold',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'import-mapper': {
        'tools': ['Read', 'Write', 'Edit', 'Bash', 'bash:symfony', 'bash:psql'],
        'permissionMode': 'acceptEdits',
        'color': 'gold',
        'model': 'claude-3-5-sonnet-20241022'
    },

    # SPECIALIST AGENTS
    'seo-specialist': {
        'tools': ['Read', 'Write', 'Edit', 'Grep', 'Glob', 'WebSearch', 'Bash'],
        'permissionMode': 'acceptEdits',
        'color': 'blue',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'git-flow-manager': {
        'tools': ['Read', 'Bash', 'bash:git'],
        'permissionMode': 'default',
        'color': 'blue',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'docusaurus-expert': {
        'tools': ['Read', 'Write', 'Edit', 'Grep', 'Glob', 'Bash'],
        'permissionMode': 'acceptEdits',
        'color': 'blue',
        'model': 'claude-3-5-sonnet-20241022'
    },

    'e2e-test-scenario-designer': {
        'tools': ['Read', 'Write', 'Grep', 'Glob'],
        'permissionMode': 'default',
        'color': 'green',
        'model': 'claude-3-5-sonnet-20241022'
    },
}


def extract_agent_name(filename: str) -> str:
    """Extract agent name from filename (without .md extension)."""
    return filename.replace('.md', '')


def extract_description(content: str) -> str:
    """Extract agent description from existing content."""
    # Try to extract from first paragraph after title
    lines = content.split('\n')
    description_lines = []
    in_description = False

    for line in lines:
        # Skip title
        if line.startswith('# '):
            in_description = True
            continue

        # Stop at first section header
        if in_description and line.startswith('##'):
            break

        # Collect description lines
        if in_description and line.strip():
            # Skip bold metadata lines
            if line.startswith('**') and '**:' in line:
                continue
            description_lines.append(line.strip())
            if len(description_lines) >= 3:  # Limit description length
                break

    description = ' '.join(description_lines) if description_lines else \
                  "Specialized agent for Deschide News multilingual news portal."

    # Add examples placeholder
    description += "\n\nExamples:\n- \"@{agent_name} [task description]\""

    return description


def generate_yaml_frontmatter(agent_name: str, description: str, config: Dict) -> str:
    """Generate YAML frontmatter for agent."""
    tools_str = '\n'.join([f'  - {tool}' for tool in config['tools']])

    yaml = f"""---
name: {agent_name}
description: |
  {description.replace('{agent_name}', agent_name)}
tools:
{tools_str}
model: {config['model']}
permissionMode: {config['permissionMode']}
color: {config['color']}
---

"""
    return yaml


def add_thinking_tags(content: str) -> str:
    """Add <thinking> tags before workflow sections if not present."""
    if '<thinking>' not in content and '## Workflow' in content:
        thinking_block = """
<thinking>
Before executing any action, analyze:

1. **Current State Assessment**
   - What files/resources exist?
   - What is the current system state?
   - Are preconditions met?

2. **Action Planning**
   - What tools do I need?
   - What's the sequence of operations?
   - What are the dependencies?

3. **Risk Analysis**
   - What could go wrong?
   - How to handle errors?
   - Do I need user confirmation?

4. **Success Criteria**
   - How do I verify success?
   - What should the output look like?
   - What metrics to check?
</thinking>

"""
        content = content.replace('## Workflow', f'## Workflow\n{thinking_block}')

    return content


def convert_agent_file(agent_path: Path, dry_run: bool = False) -> bool:
    """Convert a single agent file to new format."""
    agent_name = extract_agent_name(agent_path.name)

    # Skip template and README
    if agent_name.startswith('_') or agent_name == 'README':
        print(f"  ⏭️  Skipping: {agent_name}")
        return True

    # Check if we have config for this agent
    if agent_name not in AGENT_CONFIGS:
        print(f"  ⚠️  WARNING: No config for {agent_name} (will use default)")
        return False

    # Read existing content
    with open(agent_path, 'r', encoding='utf-8') as f:
        original_content = f.read()

    # Check if already has YAML frontmatter
    if original_content.startswith('---\n'):
        print(f"  ✅ Already converted: {agent_name}")
        return True

    # Extract description from existing content
    description = extract_description(original_content)

    # Get config
    config = AGENT_CONFIGS[agent_name]

    # Generate YAML frontmatter
    yaml_front = generate_yaml_frontmatter(agent_name, description, config)

    # Remove old metadata lines (Type, Purpose, etc.)
    content_lines = original_content.split('\n')
    cleaned_lines = []
    skip_next = False

    for line in content_lines:
        # Skip old metadata
        if line.startswith('**Type**:') or line.startswith('**Purpose**:'):
            continue
        if line.startswith('**Description**:') or line.startswith('**Specialty**:'):
            continue
        cleaned_lines.append(line)

    cleaned_content = '\n'.join(cleaned_lines)

    # Add thinking tags
    cleaned_content = add_thinking_tags(cleaned_content)

    # Combine
    new_content = yaml_front + cleaned_content

    if dry_run:
        print(f"  🔍 DRY RUN: Would convert {agent_name}")
        print(f"     Tools: {len(config['tools'])} tools")
        print(f"     Mode: {config['permissionMode']}")
        return True

    # Write back
    with open(agent_path, 'w', encoding='utf-8') as f:
        f.write(new_content)

    print(f"  ✅ Converted: {agent_name}")
    print(f"     Tools: {len(config['tools'])} tools | Mode: {config['permissionMode']} | Color: {config['color']}")

    return True


def main():
    """Main conversion script."""
    import argparse

    parser = argparse.ArgumentParser(description='Convert agents to YAML format')
    parser.add_argument('--dry-run', action='store_true', help='Show what would be done without making changes')
    args = parser.parse_args()

    agents_dir = Path('/var/www/deschide_news_app/.claude/agents')

    print("🔄 Converting Agents to YAML Frontmatter Format")
    print("=" * 60)
    print(f"Directory: {agents_dir}")
    print(f"Mode: {'DRY RUN' if args.dry_run else 'LIVE CONVERSION'}")
    print()

    # Get all agent files
    agent_files = sorted(agents_dir.glob('*.md'))

    converted = 0
    skipped = 0
    errors = 0

    for agent_path in agent_files:
        try:
            success = convert_agent_file(agent_path, dry_run=args.dry_run)
            if success:
                if agent_path.name.startswith('_') or agent_path.name == 'README.md':
                    skipped += 1
                else:
                    converted += 1
            else:
                errors += 1
        except Exception as e:
            print(f"  ❌ ERROR converting {agent_path.name}: {e}")
            errors += 1

    print()
    print("=" * 60)
    print("📊 Conversion Summary")
    print("=" * 60)
    print(f"  ✅ Converted: {converted}")
    print(f"  ⏭️  Skipped: {skipped}")
    print(f"  ❌ Errors: {errors}")
    print()

    if errors > 0:
        print("⚠️  Some agents could not be converted. Please review.")
        return 1

    if args.dry_run:
        print("🔍 DRY RUN complete. Run without --dry-run to apply changes.")
    else:
        print("✅ All agents successfully converted!")
        print()
        print("Next steps:")
        print("  1. Run validation: ./.claude/commands/validate-agents.sh")
        print("  2. Test agents: @backend-api-tester test all")
        print("  3. Create orchestrator: @workflow-orchestrator")

    return 0


if __name__ == '__main__':
    exit(main())
