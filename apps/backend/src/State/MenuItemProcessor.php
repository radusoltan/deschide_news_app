<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Category;
use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<MenuItem>
 */
final class MenuItemProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?MenuItem
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
            if ($data instanceof MenuItem) {
                $managedEntity = $this->entityManager->getRepository(MenuItem::class)->find($data->getId());

                if (!$managedEntity) {
                    throw new RuntimeException('Menu item not found');
                }

                $this->entityManager->remove($managedEntity);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST and PATCH operations
        if ($data instanceof MenuItem) {
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(MenuItem::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('Menu item not found');
                }

                // Determine which fields were actually sent in the request
                $requestContent = $request?->getContent() ?? '{}';
                $sentFields = array_keys(json_decode($requestContent, true) ?? []);

                // Only update fields that were explicitly sent in the PATCH request
                if (in_array('label', $sentFields, true) && null !== $data->getLabel()) {
                    $existingEntity->setLabel($data->getLabel());
                }
                if (in_array('menu', $sentFields, true) && null !== $data->getMenu()) {
                    $existingEntity->setMenu($data->getMenu());
                }
                if (in_array('type', $sentFields, true) && null !== $data->getType()) {
                    $existingEntity->setType($data->getType());
                }
                if (in_array('url', $sentFields, true)) {
                    $existingEntity->setUrl($data->getUrl());
                }
                if (in_array('position', $sentFields, true)) {
                    $existingEntity->setPosition($data->getPosition());
                }
                if (in_array('isActive', $sentFields, true)) {
                    $existingEntity->setIsActive($data->isActive());
                }
                if (in_array('openInNewTab', $sentFields, true)) {
                    $existingEntity->setOpenInNewTab($data->isOpenInNewTab());
                }
                if (in_array('cssClass', $sentFields, true)) {
                    $existingEntity->setCssClass($data->getCssClass());
                }

                // Handle category relation only if explicitly sent
                if (in_array('category', $sentFields, true)) {
                    if ($data->getCategory()) {
                        $categoryId = $data->getCategory()->getId();
                        if ($categoryId) {
                            $managedCategory = $this->entityManager->getRepository(Category::class)->find($categoryId);
                            $existingEntity->setCategory($managedCategory);
                        }
                    } else {
                        $existingEntity->setCategory(null);
                    }
                }

                $data = $existingEntity;
            }

            $isNew = !$data->getId();

            if ($isNew) {
                // CREATE: New entity - always save in default locale
                $data->setTranslatableLocale('ro');

                // Handle category relation (get managed entity)
                if ($data->getCategory()) {
                    $categoryId = $data->getCategory()->getId();
                    if ($categoryId) {
                        $managedCategory = $this->entityManager->getRepository(Category::class)->find($categoryId);
                        $data->setCategory($managedCategory);
                    }
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();

                // If created with non-default locale, also add translation
                if ($locale !== 'ro') {
                    $this->addTranslation($data, $locale);
                }
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

                // Reload entity with correct locale
                $data->setTranslatableLocale($locale);
                $this->entityManager->refresh($data);
            }

            return $data;
        }

        return null;
    }

    private function addTranslation(MenuItem $menuItem, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        if ($menuItem->getLabel()) {
            $translationRepo->translate($menuItem, 'label', $locale, $menuItem->getLabel());
        }

        $this->entityManager->flush();
    }
}
