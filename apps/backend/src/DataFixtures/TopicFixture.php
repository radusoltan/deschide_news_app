<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Topic;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads the editorial taxonomy v2 from YAML: 10 domains → 38 sub-groups → 116 topics.
 * Uses Gedmo Tree (nested set) — DFS traversal, persist+flush per node.
 */
class TopicFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['dev', 'test', 'topics'];
    }

    public function load(ObjectManager $manager): void
    {
        $yamlPath = \dirname(__DIR__, 2) . '/fixtures/data/editorial-topics-fixture.yaml';
        $yaml = Yaml::parseFile($yamlPath);

        $translationRepo = $manager->getRepository(Translation::class);

        $domainCount = 0;
        $subGroupCount = 0;
        $topicCount = 0;

        // Pass 1: Domains (lvl 0, parent_id = NULL)
        foreach ($yaml['domains'] as $domainData) {
            $domain = new Topic();
            $domain->setTranslatableLocale('ro');
            $domain->setTitle($domainData['translations']['ro']);
            $domain->setSlug($domainData['slug']);
            $domain->setPosition($domainData['sort_order']);
            $domain->setIsSensitive(false);
            $domain->setIsActive(true);

            $manager->persist($domain);
            $manager->flush();

            // Force slug from YAML (Gedmo Slug may have regenerated from title)
            if ($domain->getSlug() !== $domainData['slug']) {
                $domain->setSlug($domainData['slug']);
                $manager->flush();
            }

            // EN/RU translations via Translation repository (avoids locale switching)
            $translationRepo->translate($domain, 'title', 'en', $domainData['translations']['en']);
            $translationRepo->translate($domain, 'title', 'ru', $domainData['translations']['ru']);
            $manager->flush();

            $this->addReference("topic-domain-{$domainData['slug']}", $domain);
            $domainCount++;
        }

        // Pass 2: Sub-groups (lvl 1, parent = domain)
        foreach ($yaml['domains'] as $domainData) {
            $parent = $this->getReference("topic-domain-{$domainData['slug']}", Topic::class);

            foreach ($domainData['sub_groups'] as $sgData) {
                $sg = new Topic();
                $sg->setParent($parent);
                $sg->setTranslatableLocale('ro');
                $sg->setTitle($sgData['translations']['ro']);
                $sg->setSlug($sgData['slug']);
                $sg->setIsSensitive(false);
                $sg->setIsActive(true);

                $manager->persist($sg);
                $manager->flush();

                if ($sg->getSlug() !== $sgData['slug']) {
                    $sg->setSlug($sgData['slug']);
                    $manager->flush();
                }

                $translationRepo->translate($sg, 'title', 'en', $sgData['translations']['en']);
                $translationRepo->translate($sg, 'title', 'ru', $sgData['translations']['ru']);
                $manager->flush();

                $this->addReference("topic-subgroup-{$sgData['slug']}", $sg);
                $subGroupCount++;
            }
        }

        // Pass 3: Topics (lvl 2, parent = sub-group)
        foreach ($yaml['domains'] as $domainData) {
            foreach ($domainData['sub_groups'] as $sgData) {
                $parent = $this->getReference("topic-subgroup-{$sgData['slug']}", Topic::class);

                foreach ($sgData['topics'] as $topicData) {
                    $topic = new Topic();
                    $topic->setParent($parent);
                    $topic->setTranslatableLocale('ro');
                    $topic->setTitle($topicData['translations']['ro']['name']);
                    $topic->setSlug($topicData['slug']);
                    $topic->setDescription($topicData['translations']['ro']['description'] ?? null);
                    $topic->setIsSensitive($topicData['is_sensitive'] ?? false);
                    $topic->setKeywords($topicData['keywords'] ?? []);
                    $topic->setIsActive(true);

                    $manager->persist($topic);
                    $manager->flush();

                    if ($topic->getSlug() !== $topicData['slug']) {
                        $topic->setSlug($topicData['slug']);
                        $manager->flush();
                    }

                    // EN/RU translations for both title and description
                    $translationRepo->translate($topic, 'title', 'en', $topicData['translations']['en']['name']);
                    $translationRepo->translate($topic, 'title', 'ru', $topicData['translations']['ru']['name']);

                    $enDesc = $topicData['translations']['en']['description'] ?? null;
                    $ruDesc = $topicData['translations']['ru']['description'] ?? null;
                    if ($enDesc !== null) {
                        $translationRepo->translate($topic, 'description', 'en', $enDesc);
                    }
                    if ($ruDesc !== null) {
                        $translationRepo->translate($topic, 'description', 'ru', $ruDesc);
                    }
                    $manager->flush();

                    $this->addReference("topic-{$topicData['slug']}", $topic);
                    $topicCount++;
                }
            }
        }

        echo "  Topics loaded: {$domainCount} domains, {$subGroupCount} sub-groups, {$topicCount} leaf topics\n";
    }
}
