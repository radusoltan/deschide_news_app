#!/bin/bash
# Validate Agents Configuration
# Checks that all agents follow Anthropic best practices

AGENTS_DIR=".claude/agents"
ERRORS=0
WARNINGS=0

echo "🔍 Validating Agents Configuration..."
echo ""

# Check if agents directory exists
if [ ! -d "$AGENTS_DIR" ]; then
    echo "❌ ERROR: Agents directory not found: $AGENTS_DIR"
    exit 1
fi

# Iterate through all agent files
for agent_file in "$AGENTS_DIR"/*.md; do
    filename=$(basename "$agent_file")

    # Skip README and templates
    if [[ "$filename" == "README.md" || "$filename" == "_TEMPLATE_"* ]]; then
        continue
    fi

    echo "Checking: $filename"

    # Check 1: Has YAML frontmatter
    if ! head -n 1 "$agent_file" | grep -q "^---$"; then
        echo "  ❌ Missing YAML frontmatter"
        ((ERRORS++))
    else
        echo "  ✅ Has YAML frontmatter"
    fi

    # Check 2: Has 'name:' field
    if ! grep -q "^name:" "$agent_file"; then
        echo "  ❌ Missing 'name:' field"
        ((ERRORS++))
    else
        echo "  ✅ Has 'name' field"
    fi

    # Check 3: Has 'tools:' field
    if ! grep -q "^tools:" "$agent_file"; then
        echo "  ⚠️  WARNING: No explicit 'tools:' whitelist (inherits all)"
        ((WARNINGS++))
    else
        echo "  ✅ Has 'tools' whitelist"
    fi

    # Check 4: Has 'permissionMode:' field
    if ! grep -q "^permissionMode:" "$agent_file"; then
        echo "  ⚠️  WARNING: No explicit 'permissionMode' (uses default)"
        ((WARNINGS++))
    else
        echo "  ✅ Has 'permissionMode'"
    fi

    # Check 5: Has 'model:' field
    if ! grep -q "^model:" "$agent_file"; then
        echo "  ⚠️  INFO: No explicit 'model' (uses default)"
    else
        echo "  ✅ Has 'model' specified"
    fi

    # Check 6: Has <thinking> tags
    if ! grep -q "<thinking>" "$agent_file"; then
        echo "  ⚠️  WARNING: No <thinking> tags (Chain of Thought)"
        ((WARNINGS++))
    else
        echo "  ✅ Uses <thinking> tags"
    fi

    echo ""
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Validation Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "✅ All agents follow best practices!"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo "⚠️  $WARNINGS warnings found (non-critical)"
    echo ""
    echo "Consider addressing warnings for optimal configuration."
    exit 0
else
    echo "❌ $ERRORS errors found (critical)"
    echo "⚠️  $WARNINGS warnings found"
    echo ""
    echo "Please fix errors before proceeding."
    exit 1
fi
