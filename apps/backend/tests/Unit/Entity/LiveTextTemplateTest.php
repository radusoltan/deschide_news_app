<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveTextTemplate;
use App\Enum\TemplateType;
use PHPUnit\Framework\TestCase;

class LiveTextTemplateTest extends TestCase
{
    private LiveTextTemplate $template;

    protected function setUp(): void
    {
        $this->template = new LiveTextTemplate();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->template->getId());
        $this->assertNull($this->template->getDescription());
        $this->assertSame([], $this->template->getConfig());
        $this->assertFalse($this->template->isSystem());
        $this->assertFalse($this->template->getIsSystem());
    }

    public function testSetGetName(): void
    {
        $result = $this->template->setName('Breaking News Template');
        $this->assertSame('Breaking News Template', $this->template->getName());
        $this->assertSame($this->template, $result);
    }

    public function testSetGetNameEmptyString(): void
    {
        $this->template->setName('');
        $this->assertSame('', $this->template->getName());
    }

    public function testSetGetDescription(): void
    {
        $result = $this->template->setDescription('Template for breaking news');
        $this->assertSame('Template for breaking news', $this->template->getDescription());
        $this->assertSame($this->template, $result);
    }

    public function testSetGetDescriptionNull(): void
    {
        $this->template->setDescription('test');
        $this->template->setDescription(null);
        $this->assertNull($this->template->getDescription());
    }

    public function testSetGetType(): void
    {
        $result = $this->template->setType(TemplateType::BREAKING_NEWS);
        $this->assertSame(TemplateType::BREAKING_NEWS, $this->template->getType());
        $this->assertSame($this->template, $result);
    }

    public function testSetGetTypeAllValues(): void
    {
        foreach (TemplateType::cases() as $type) {
            $this->template->setType($type);
            $this->assertSame($type, $this->template->getType());
        }
    }

    public function testSetGetConfig(): void
    {
        $config = [
            'colors' => ['primary' => '#ef4444', 'secondary' => '#dc2626'],
            'layout' => ['headerStyle' => 'bold'],
            'features' => ['enableReactions' => true],
        ];
        $result = $this->template->setConfig($config);
        $this->assertSame($config, $this->template->getConfig());
        $this->assertSame($this->template, $result);
    }

    public function testSetGetIsSystem(): void
    {
        $result = $this->template->setIsSystem(true);
        $this->assertTrue($this->template->isSystem());
        $this->assertTrue($this->template->getIsSystem());
        $this->assertSame($this->template, $result);
    }

    public function testGetConfigValueSimple(): void
    {
        $this->template->setConfig(['key' => 'value']);
        $this->assertSame('value', $this->template->getConfigValue('key'));
    }

    public function testGetConfigValueNested(): void
    {
        $this->template->setConfig([
            'colors' => ['primary' => '#ef4444', 'secondary' => '#dc2626'],
        ]);
        $this->assertSame('#ef4444', $this->template->getConfigValue('colors.primary'));
    }

    public function testGetConfigValueDeeplyNested(): void
    {
        $this->template->setConfig([
            'features' => ['reactions' => ['enabled' => true]],
        ]);
        $this->assertTrue($this->template->getConfigValue('features.reactions.enabled'));
    }

    public function testGetConfigValueReturnsDefault(): void
    {
        $this->template->setConfig([]);
        $this->assertSame('fallback', $this->template->getConfigValue('nonexistent', 'fallback'));
    }

    public function testGetConfigValueReturnsDefaultForMissingNested(): void
    {
        $this->template->setConfig(['colors' => []]);
        $this->assertNull($this->template->getConfigValue('colors.primary'));
    }

    public function testGetConfigValueReturnsDefaultNull(): void
    {
        $this->template->setConfig([]);
        $this->assertNull($this->template->getConfigValue('missing'));
    }

    public function testSetConfigValueSimple(): void
    {
        $result = $this->template->setConfigValue('key', 'value');
        $this->assertSame('value', $this->template->getConfig()['key']);
        $this->assertSame($this->template, $result);
    }

    public function testSetConfigValueNested(): void
    {
        $this->template->setConfigValue('colors.primary', '#ff0000');
        $config = $this->template->getConfig();
        $this->assertSame('#ff0000', $config['colors']['primary']);
    }

    public function testSetConfigValueDeeplyNested(): void
    {
        $this->template->setConfigValue('a.b.c', 'deep');
        $config = $this->template->getConfig();
        $this->assertSame('deep', $config['a']['b']['c']);
    }

    public function testSetConfigValueOverwritesExisting(): void
    {
        $this->template->setConfig(['colors' => ['primary' => '#old']]);
        $this->template->setConfigValue('colors.primary', '#new');
        $this->assertSame('#new', $this->template->getConfigValue('colors.primary'));
    }

    public function testSetConfigValueCreatesIntermediateArrays(): void
    {
        $this->template->setConfig([]);
        $this->template->setConfigValue('deeply.nested.path', 'value');
        $config = $this->template->getConfig();
        $this->assertSame('value', $config['deeply']['nested']['path']);
    }
}
