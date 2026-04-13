<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class InvoiceGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function generate(Order $order): string
    {
        $html = $this->twig->render('pdf/invoice.html.twig', [
            'order' => $order,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
