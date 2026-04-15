<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Enum\TestimonialStatus;
use App\Form\TestimonialSubmitType;
use App\Repository\TestimonialRepository;
use App\Service\TestimonialMailer;
use App\Service\TurnstileVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestimonialController extends AbstractController
{
    #[Route(
        path: ['en' => '/testimonial/{token}', 'fr' => '/temoignage/{token}'],
        name: 'shop_testimonial_submit',
        methods: ['GET', 'POST'],
    )]
    public function submit(
        string $token,
        Request $request,
        TestimonialRepository $testimonialRepository,
        EntityManagerInterface $em,
        TestimonialMailer $testimonialMailer,
        TurnstileVerifier $turnstileVerifier,
    ): Response {
        $testimonial = $testimonialRepository->findByToken($token);

        if ($testimonial === null) {
            throw $this->createNotFoundException();
        }

        if ($testimonial->isSubmitted()) {
            return $this->render('shop/testimonial/already_submitted.html.twig', [
                'testimonial' => $testimonial,
            ]);
        }

        $form = $this->createForm(TestimonialSubmitType::class, $testimonial);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Turnstile bot protection check
            $turnstileToken = (string) $request->request->get('cf-turnstile-response', '');
            if (!$turnstileVerifier->verify($turnstileToken, $request->getClientIp())) {
                $this->addFlash('error', 'testimonial.error.bot_detected');

                return $this->redirectToRoute('shop_testimonial_submit', ['token' => $token]);
            }

            $testimonial->setSubmittedAt(new \DateTimeImmutable());
            $testimonial->setStatus(TestimonialStatus::Pending);
            $em->flush();

            $testimonialMailer->sendAdminNotification($testimonial);

            return $this->render('shop/testimonial/thank_you.html.twig', [
                'testimonial' => $testimonial,
            ]);
        }

        return $this->render('shop/testimonial/submit.html.twig', [
            'testimonial' => $testimonial,
            'form' => $form,
        ]);
    }

    #[Route(
        path: ['en' => '/testimonials', 'fr' => '/temoignages'],
        name: 'shop_testimonials',
        methods: ['GET'],
    )]
    public function index(
        Request $request,
        TestimonialRepository $testimonialRepository,
        PaginatorInterface $paginator,
    ): Response {
        $query = $testimonialRepository->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.submittedAt IS NOT NULL')
            ->setParameter('status', TestimonialStatus::Approved)
            ->orderBy('t.submittedAt', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12,
        );

        $stats = $testimonialRepository->getApprovedStats();

        return $this->render('shop/testimonial/index.html.twig', [
            'pagination' => $pagination,
            'averageRating' => $stats['averageRating'],
            'totalCount' => $stats['totalCount'],
        ]);
    }
}
