<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Service\Clustering\PressReleaseIndexManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PressReleaseIndexManagerStopWordsTest extends TestCase
{
    #[Test]
    public function contextStopWordsContainsMoldovaTerms(): void
    {
        $words = PressReleaseIndexManager::CONTEXT_STOP_WORDS;

        $this->assertContains('moldova', $words);
        $this->assertContains('republica', $words);
        $this->assertContains('chișinău', $words);
        $this->assertContains('chisinau', $words);
        $this->assertContains('moldovei', $words);
    }

    #[Test]
    public function contextStopWordsContainsEnglishContextual(): void
    {
        $words = PressReleaseIndexManager::CONTEXT_STOP_WORDS;

        $this->assertContains('republic', $words);
        $this->assertContains('government', $words);
    }

    #[Test]
    public function contextStopWordsContainsRussianContextual(): void
    {
        $words = PressReleaseIndexManager::CONTEXT_STOP_WORDS;

        $this->assertContains('молдова', $words);
        $this->assertContains('республика', $words);
        $this->assertContains('кишинев', $words);
        $this->assertContains('правительство', $words);
    }

    #[Test]
    public function contextStopWordsContainsNoiseTerms(): void
    {
        $words = PressReleaseIndexManager::CONTEXT_STOP_WORDS;

        $this->assertContains('www', $words);
        $this->assertContains('http', $words);
        $this->assertContains('https', $words);
        $this->assertContains('foto', $words);
        $this->assertContains('video', $words);
    }

    #[Test]
    public function contextStopWordsDoNotContainContentWords(): void
    {
        $words = PressReleaseIndexManager::CONTEXT_STOP_WORDS;

        $this->assertNotContains('armistițiu', $words);
        $this->assertNotContains('prizonier', $words);
        $this->assertNotContains('alegeri', $words);
        $this->assertNotContains('corupție', $words);
        $this->assertNotContains('sancțiuni', $words);
    }

    #[Test]
    public function analyzerSettingsIncludeStopWordFilters(): void
    {
        // Use reflection to call the private getSettings method
        $manager = new PressReleaseIndexManager('');
        $ref = new \ReflectionMethod($manager, 'getSettings');
        $settings = $ref->invoke($manager);

        $filters = $settings['analysis']['analyzer']['multilingual_standard']['filter'];

        $this->assertContains('ro_stop', $filters);
        $this->assertContains('en_stop', $filters);
        $this->assertContains('ru_stop', $filters);
        $this->assertContains('context_stop', $filters);
    }

    #[Test]
    public function analyzerSettingsDefineStopWordFilterTypes(): void
    {
        $manager = new PressReleaseIndexManager('');
        $ref = new \ReflectionMethod($manager, 'getSettings');
        $settings = $ref->invoke($manager);

        $filterDefs = $settings['analysis']['filter'];

        $this->assertSame('stop', $filterDefs['ro_stop']['type']);
        $this->assertSame('_romanian_', $filterDefs['ro_stop']['stopwords']);
        $this->assertSame('stop', $filterDefs['en_stop']['type']);
        $this->assertSame('_english_', $filterDefs['en_stop']['stopwords']);
        $this->assertSame('stop', $filterDefs['ru_stop']['type']);
        $this->assertSame('_russian_', $filterDefs['ru_stop']['stopwords']);
        $this->assertSame('stop', $filterDefs['context_stop']['type']);
        $this->assertSame(PressReleaseIndexManager::CONTEXT_STOP_WORDS, $filterDefs['context_stop']['stopwords']);
    }
}
