<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Service\Editorial\FrontmatterValidator;
use PHPUnit\Framework\TestCase;

class FrontmatterValidatorTest extends TestCase
{
    private FrontmatterValidator $validator;

    protected function setUp(): void
    {
        $schemaPath = dirname(__DIR__, 3) . '/../config/schemas/article-frontmatter.schema.json';
        $this->validator = new FrontmatterValidator($schemaPath);
    }

    public function testValidCompleteFrontmatter(): void
    {
        $fm = $this->getValidFrontmatter();
        $result = $this->validator->validate($fm);

        $this->assertTrue($result->isValid, 'Expected valid frontmatter but got errors: ' . implode(', ', $result->errors));
        $this->assertSame([], $result->errors);
    }

    public function testMissingRequiredFieldId(): void
    {
        $fm = $this->getValidFrontmatter();
        unset($fm['id']);
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
    }

    public function testMissingRequiredFieldType(): void
    {
        $fm = $this->getValidFrontmatter();
        unset($fm['type']);
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testMissingRequiredFieldLanguage(): void
    {
        $fm = $this->getValidFrontmatter();
        unset($fm['language']);
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testMissingRequiredFieldTitle(): void
    {
        $fm = $this->getValidFrontmatter();
        unset($fm['title']);
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testMissingRequiredFieldStatus(): void
    {
        $fm = $this->getValidFrontmatter();
        unset($fm['status']);
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testMissingRequiredFieldDateCreated(): void
    {
        $fm = $this->getValidFrontmatter();
        unset($fm['date_created']);
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testInvalidTypeEnum(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['type'] = 'invalid-type';
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testInvalidStatusEnum(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['status'] = 'xyz';
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testInvalidIdPattern(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['id'] = 'invalid-no-art-prefix';
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testTitleWithoutRoIsInvalid(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['title'] = ['en' => 'English Only'];
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testTitleWithEmptyRoIsInvalid(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['title'] = ['ro' => '', 'en' => 'English'];
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testMinimalValidFrontmatter(): void
    {
        $fm = [
            'id' => 'art-minimal',
            'type' => 'news',
            'language' => 'ro',
            'title' => ['ro' => 'Titlu minimal'],
            'status' => 'draft',
            'date_created' => '2026-04-02T10:00:00Z',
        ];
        $result = $this->validator->validate($fm);

        $this->assertTrue($result->isValid, 'Errors: ' . implode(', ', $result->errors));
    }

    public function testInvalidPriorityEnum(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['priority'] = 'critical';
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testValidAiMetadata(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['ai'] = [
            'translation_status' => ['ro' => 'complete', 'en' => 'pending', 'ru' => 'pending'],
            'classification_confidence' => 0.85,
            'entities_extracted' => ['Moldova', 'UE'],
            'sentiment' => 'neutral',
            'processed_by' => 'claude-agent',
            'processed_at' => '2026-04-02T12:00:00Z',
            'auto_generated' => false,
            'reviewed' => false,
        ];
        $result = $this->validator->validate($fm);

        $this->assertTrue($result->isValid, 'Errors: ' . implode(', ', $result->errors));
    }

    public function testClassificationConfidenceOutOfRange(): void
    {
        $fm = $this->getValidFrontmatter();
        $fm['ai'] = [
            'classification_confidence' => 1.5,
        ];
        $result = $this->validator->validate($fm);

        $this->assertFalse($result->isValid);
    }

    public function testSchemaFileNotFound(): void
    {
        $validator = new FrontmatterValidator('/nonexistent/path/schema.json');
        $result = $validator->validate(['id' => 'art-test']);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Schema file not found', $result->errors[0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidFrontmatter(): array
    {
        return [
            'id' => 'art-2026-04-02-reforma-energetica',
            'type' => 'press-release',
            'language' => 'ro',
            'title' => [
                'ro' => 'Guvernul aprobă noul plan de reformă energetică',
                'en' => 'Government Approves Energy Reform Plan',
                'ru' => 'Правительство утвердило план',
            ],
            'description' => [
                'ro' => 'Cabinetul a aprobat planul de reformă.',
            ],
            'slug' => [
                'ro' => 'guvernul-aproba-plan-reforma',
                'en' => 'government-approves-reform',
            ],
            'status' => 'draft',
            'priority' => 'normal',
            'date_created' => '2026-04-02T06:30:00Z',
            'author' => 'ion-popescu',
            'categories' => ['politică', 'energie'],
            'tags' => ['reforma-energetică', 'guvern'],
            'seo' => [
                'canonical' => 'https://deschide.md/ro/reforma',
                'og_image' => '/images/reform.jpg',
                'og_type' => 'article',
                'twitter_card' => 'summary_large_image',
                'keywords' => [
                    'ro' => ['reformă', 'energie'],
                    'en' => ['reform', 'energy'],
                ],
            ],
            'source' => [
                'name' => 'Moldpres',
                'url' => 'https://moldpres.md/rom/news/12345',
                'original_language' => 'ro',
            ],
        ];
    }
}
