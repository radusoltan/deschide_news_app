<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Category;
use App\Service\PerformanceService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @implements ProcessorInterface<Category>
 */
final class CategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly PerformanceService $performance,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Category
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof Category) {
                // Get managed entity from database (the provided entity may be detached)
                $managedEntity = $this->entityManager->getRepository(Category::class)->find($data->getId());

                if (!$managedEntity) {
                    throw new RuntimeException('Category not found');
                }

                // Check if category has articles
                if ($managedEntity->getArticles()->count() > 0) {
                    throw new LogicException('Cannot delete category with existing articles');
                }

                $categoryId = $managedEntity->getId();
                $this->entityManager->remove($managedEntity);
                $this->entityManager->flush();
                $this->performance->invalidateCategory($categoryId);
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof Category) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(Category::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('Category not found');
                }

                // Update fields from deserialized data
                $existingEntity->setTitle($data->getTitle());
                $existingEntity->setStatus($data->getStatus());
                $existingEntity->setOnFrontPage($data->isOnFrontPage());
                $existingEntity->setInMenu($data->isInMenu());
                $existingEntity->setInFooterMenu($data->isInFooterMenu());

                // Handle parent (get managed entity)
                if ($data->getParent()) {
                    $parentId = $data->getParent()->getId();
                    if ($parentId && $parentId !== $existingEntity->getId()) {
                        $managedParent = $this->entityManager->getRepository(Category::class)->find($parentId);
                        $existingEntity->setParent($managedParent);
                    }
                } else {
                    $existingEntity->setParent(null);
                }

                // Use existing entity instead of deserialized one
                $data = $existingEntity;
            }

            $isNew = !$data->getId();

            if ($isNew) {
                // CREATE: New entity - always save in default locale
                $data->setTranslatableLocale('ro');

                // Handle parent (get managed entity)
                if ($data->getParent()) {
                    $parentId = $data->getParent()->getId();
                    if ($parentId) {
                        $managedParent = $this->entityManager->getRepository(Category::class)->find($parentId);
                        $data->setParent($managedParent);
                    }
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();

                // If created with non-default locale, also add translation
                if ($locale !== 'ro') {
                    $this->addTranslation($data, $locale);
                }

                // Invalidate category list cache after create
                $this->performance->invalidateCategory($data->getId());
            } else {
                // UPDATE: Existing entity
                if ($locale === 'ro') {
                    // Update default locale fields directly
                    $data->setTranslatableLocale($locale);
                    $this->entityManager->flush();
                } else {
                    // Add/Update translation for non-default locale
                    $this->addTranslation($data, $locale);
                }

                // Invalidate cache after update
                $this->performance->invalidateCategory($data->getId());

                // Reload entity with correct locale
                $data->setTranslatableLocale($locale);
                $this->entityManager->refresh($data);
            }

            return $data;
        }

        return null;
    }

    private function addTranslation(Category $category, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        if ($category->getTitle()) {
            $translationRepo->translate($category, 'title', $locale, $category->getTitle());

            // Generate translated slug: use explicit slug if provided, otherwise auto-generate from title
            $slug = $category->getSlug();
            $translatedSlug = ($slug && $slug !== '' && $slug !== $this->slugger->slug($category->getTitle())->lower()->toString())
                ? $slug
                : $this->slugger->slug($category->getTitle())->lower()->toString();
            $translationRepo->translate($category, 'slug', $locale, $translatedSlug);
        }

        $this->entityManager->flush();
    }
}
