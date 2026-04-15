<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\ContactMessage;
use App\Entity\Customer;
use App\Form\ContactType;
use App\Service\ContactMailer;
use App\Service\TurnstileVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route(
        path: ['en' => '/contact', 'fr' => '/contact'],
        name: 'shop_contact',
        methods: ['GET', 'POST'],
    )]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ContactMailer $contactMailer,
        RateLimiterFactory $contactFormLimiter,
        TurnstileVerifier $turnstileVerifier,
    ): Response {
        $contactMessage = new ContactMessage();

        $user = $this->getUser();
        if ($user instanceof Customer) {
            $contactMessage->setName($user->getFullName());
            $contactMessage->setEmail($user->getEmail());
        }

        $form = $this->createForm(ContactType::class, $contactMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Honeypot check
            if ((string) $form->get('website')->getData() !== '') {
                return $this->redirectToRoute('shop_contact');
            }

            // Turnstile bot protection check
            $turnstileToken = (string) $request->request->get('cf-turnstile-response', '');
            if (!$turnstileVerifier->verify($turnstileToken, $request->getClientIp())) {
                $this->addFlash('error', 'contact.error.bot_detected');

                return $this->redirectToRoute('shop_contact');
            }

            // Rate limiter check
            $limiter = $contactFormLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'contact.error.rate_limited');

                return $this->redirectToRoute('shop_contact');
            }

            $em->persist($contactMessage);
            $em->flush();

            $contactMailer->sendContactNotification($contactMessage);

            $this->addFlash('success', 'contact.success');

            return $this->redirectToRoute('shop_contact');
        }

        return $this->render('shop/contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
