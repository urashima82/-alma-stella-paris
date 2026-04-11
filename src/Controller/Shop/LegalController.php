<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route(
        path: ['en' => '/legal-notice', 'fr' => '/mentions-legales'],
        name: 'shop_legal_notice',
    )]
    public function legalNotice(): Response
    {
        return $this->render('shop/legal/legal_notice.html.twig');
    }

    #[Route(
        path: ['en' => '/terms-of-sale', 'fr' => '/conditions-generales-de-vente'],
        name: 'shop_terms',
    )]
    public function terms(): Response
    {
        return $this->render('shop/legal/terms.html.twig');
    }
}
