<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;

/**
 * Derives tags from topic keywords in the editorial taxonomy YAML.
 * Deduplicates by slug (~1,790 unique tags from ~1,918 keyword entries).
 * No topic_tag pivot — tags are standalone entities for article tagging.
 */
class TagFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['dev', 'test', 'topics'];
    }

    public function getDependencies(): array
    {
        return [TopicFixture::class];
    }

    public function load(ObjectManager $manager): void
    {
        $yamlPath = \dirname(__DIR__, 2) . '/fixtures/data/editorial-topics-fixture.yaml';
        $yaml = Yaml::parseFile($yamlPath);

        $translationRepo = $manager->getRepository(Translation::class);
        $slugger = new AsciiSlugger();

        // Step 1: Collect unique keywords (dedup by computed slug)
        $uniqueBySlug = []; // slug → keyword (first occurrence wins)
        $totalKeywords = 0;

        foreach ($yaml['domains'] as $domainData) {
            foreach ($domainData['sub_groups'] ?? [] as $sgData) {
                foreach ($sgData['topics'] ?? [] as $topicData) {
                    foreach ($topicData['keywords'] ?? [] as $keyword) {
                        $totalKeywords++;
                        $keyword = trim($keyword);
                        if ($keyword === '') {
                            continue;
                        }

                        $name = mb_substr($keyword, 0, 100);
                        $slug = $slugger->slug($name)->lower()->toString();
                        $slug = substr($slug, 0, 100);

                        if ($slug === '' || isset($uniqueBySlug[$slug])) {
                            continue;
                        }

                        $uniqueBySlug[$slug] = $name;
                    }
                }
            }
        }

        // Step 2: Create tags from deduped list
        $created = 0;
        foreach ($uniqueBySlug as $slug => $name) {
            $slug = (string) $slug; // PHP casts numeric-looking keys to int
            $tag = new Tag();
            $tag->setTranslatableLocale('ro');
            $tag->setName($name);
            $tag->setSlug($slug);

            $manager->persist($tag);
            $manager->flush();

            // Force slug from our computation (Gedmo Slug may regenerate from name)
            if ($tag->getSlug() !== $slug) {
                $tag->setSlug($slug);
                $manager->flush();
            }

            // Same keyword stored in all 3 locales (journalists refine later)
            $translationRepo->translate($tag, 'name', 'en', $name);
            $translationRepo->translate($tag, 'name', 'ru', $name);
            $manager->flush();

            $created++;

            if ($created % 500 === 0) {
                echo "  Tags progress: {$created}/" . \count($uniqueBySlug) . "...\n";
            }
        }

        $duplicates = $totalKeywords - $created;
        echo "  Tags loaded: {$created} unique from {$totalKeywords} keywords ({$duplicates} duplicates)\n";
    }
}
