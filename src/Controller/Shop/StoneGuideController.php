<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Repository\StoneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StoneGuideController extends AbstractController
{
    #[Route(
        path: ['en' => '/stones', 'fr' => '/pierres'],
        name: 'shop_stone_index',
    )]
    public function index(StoneRepository $stoneRepository): Response
    {
        return $this->render('shop/stone/index.html.twig', [
            'stones' => $stoneRepository->findAllOrdered(),
        ]);
    }

    #[Route(
        path: ['en' => '/stones/{slug}', 'fr' => '/pierres/{slug}'],
        name: 'shop_stone_show',
    )]
    public function show(
        string $slug,
        Request $request,
        StoneRepository $stoneRepository,
    ): Response {
        $stone = $stoneRepository->findBySlug($slug, $request->getLocale());

        if ($stone === null) {
            throw $this->createNotFoundException('Stone not found.');
        }

        return $this->render('shop/stone/show.html.twig', [
            'stone' => $stone,
        ]);
    }
}
