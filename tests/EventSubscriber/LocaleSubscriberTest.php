<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class LocaleSubscriberTest extends TestCase
{
    public function testAdminRequestIsForcedToFrench(): void
    {
        // Back-office URLs carry no locale prefix. Without the forced locale they
        // fall through to the visitor's shop cookie, and EasyAdmin's own labels
        // come out in English.
        $request = Request::create('/admin/product');
        $request->cookies->set('locale', 'en');

        $this->dispatchRequest($request);

        self::assertSame('fr', $request->getLocale());
    }

    public function testAdminLoginIsAlsoFrench(): void
    {
        $request = Request::create('/admin/login');

        $this->dispatchRequest($request);

        self::assertSame('fr', $request->getLocale());
    }

    /**
     * `/administration` on the storefront side must not be mistaken for the back
     * office — the prefix only matches `/admin` itself and `/admin/...`.
     */
    public function testLookalikeShopPathIsNotTreatedAsAdmin(): void
    {
        $request = Request::create('/en/administration');

        $this->dispatchRequest($request);

        self::assertSame('en', $request->getLocale());
    }

    public function testShopPrefixStillWins(): void
    {
        $request = Request::create('/fr/boutique');

        $this->dispatchRequest($request);

        self::assertSame('fr', $request->getLocale());
    }

    /**
     * The session and cookie hold the visitor's storefront choice. An admin page
     * runs on a locale nobody picked, so writing it back would flip the shop to
     * French behind the back office.
     */
    public function testAdminResponseLeavesTheShopPreferenceAlone(): void
    {
        $request = Request::create('/admin/product');
        $request->setSession($session = new Session(new MockArraySessionStorage()));
        $session->set('_locale', 'en');
        $request->cookies->set('locale', 'en');

        $this->dispatchRequest($request);
        $response = $this->dispatchResponse($request);

        self::assertSame('en', $session->get('_locale'));
        self::assertSame([], $response->headers->getCookies());
    }

    public function testShopResponseStillPersistsTheChosenLocale(): void
    {
        $request = Request::create('/fr/boutique');
        $request->setSession($session = new Session(new MockArraySessionStorage()));

        $this->dispatchRequest($request);
        $response = $this->dispatchResponse($request);

        self::assertSame('fr', $session->get('_locale'));

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('locale', $cookies[0]->getName());
        self::assertSame('fr', $cookies[0]->getValue());
    }

    private function dispatchRequest(Request $request): void
    {
        (new LocaleSubscriber())->onKernelRequest(new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));
    }

    private function dispatchResponse(Request $request): Response
    {
        $response = new Response();
        (new LocaleSubscriber())->onKernelResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        return $response;
    }
}
