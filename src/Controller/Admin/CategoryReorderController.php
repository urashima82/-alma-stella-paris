<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class CategoryReorderController extends AbstractController
{
    #[Route('/admin/category/reorder', name: 'admin_category_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        ProductCategoryRepository $categoryRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array{orderedIds?: int[]} $data */
        $data = \json_decode($request->getContent(), true);
        $orderedIds = $data['orderedIds'] ?? [];

        if ($orderedIds === []) {
            return new JsonResponse(['error' => 'No data'], 400);
        }

        $categories = $categoryRepository->findBy(['id' => $orderedIds]);
        $indexed = [];
        foreach ($categories as $category) {
            $indexed[$category->getId()] = $category;
        }

        // Group IDs by parent, preserving the drag order
        $groups = [];
        foreach ($orderedIds as $id) {
            $category = $indexed[$id] ?? null;
            if ($category === null) {
                continue;
            }
            $parentId = $category->getParent()?->getId() ?? 0;
            $groups[$parentId][] = $category;
        }

        // Set positions within each group
        foreach ($groups as $siblings) {
            foreach ($siblings as $pos => $category) {
                $category->setPosition($pos);
            }
        }

        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
