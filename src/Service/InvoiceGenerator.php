<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final class InvoiceGenerator
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function generate(Order $order, string $locale = 'en'): string
    {
        $logoPath = $this->projectDir.'/public/images/logo.png';
        $logoBase64 = null;
        if (\file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,'.\base64_encode((string) \file_get_contents($logoPath));
        }

        $html = $this->twig->render('pdf/invoice.html.twig', [
            'order' => $order,
            'locale' => $locale,
            'logoBase64' => $logoBase64,
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
