<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\InvoiceGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class InvoiceController extends AbstractController
{
    #[Route(
        path: '/invoice/{reference}/{token}',
        name: 'shop_invoice_download',
        methods: ['GET'],
    )]
    public function download(
        string $reference,
        string $token,
        string $_locale,
        OrderRepository $orderRepository,
        InvoiceGenerator $invoiceGenerator,
    ): Response {
        $order = $orderRepository->findByReference($reference);

        if (null === $order || $order->getInvoiceToken() !== $token) {
            throw new NotFoundHttpException();
        }

        if (OrderStatus::Delivered !== $order->getStatus()
            && OrderStatus::Shipped !== $order->getStatus()
            && OrderStatus::Processing !== $order->getStatus()
        ) {
            throw new NotFoundHttpException();
        }

        $pdf = $invoiceGenerator->generate($order, $_locale);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \sprintf('inline; filename="invoice-%s.pdf"', $order->getInvoiceNumber() ?? $order->getReference()),
        ]);
    }
}
