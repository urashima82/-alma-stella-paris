<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'shop_home')]
    public function index(): Response
    {
        return $this->render('shop/home/index.html.twig');
    }
}
