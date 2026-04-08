<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AboutController extends AbstractController
{
    #[Route(
        path: ['en' => '/about', 'fr' => '/a-propos'],
        name: 'shop_about',
    )]
    public function index(): Response
    {
        return $this->render('shop/about/index.html.twig');
    }
}
