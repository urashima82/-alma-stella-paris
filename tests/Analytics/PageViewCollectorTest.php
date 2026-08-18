<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\PageViewCollector;
use App\Entity\PageViewStat;
use App\Enum\StatDimension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Pins the countability rules. Every exclusion here fails in silence when it
 * breaks — the catalog's "load more" fetches and Estelle's own admin sessions
 * all keep the figures plausible while making them wrong — which is exactly
 * what a test is for.
 */
final class PageViewCollectorTest extends TestCase
{
    private const string DESKTOP_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    private const string MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private PageViewCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new PageViewCollector();
    }

    public function testPageLocaleAndDeviceAreCollected(): void
    {
        $entries = $this->collector->collect($this->request('/fr/boutique'), $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('/fr/boutique', $this->valueOf($entries, StatDimension::Page));
        self::assertSame('fr', $this->valueOf($entries, StatDimension::Locale));
        self::assertSame('desktop', $this->valueOf($entries, StatDimension::Device));
        // Direct visit: no referrer entry at all.
        self::assertNull($this->valueOf($entries, StatDimension::Referrer));
    }

    /** The locale prefix is kept verbatim, no FR/EN merging. */
    public function testEnglishPathIsKeptVerbatim(): void
    {
        $entries = $this->collector->collect($this->request('/en/shop', locale: 'en'), $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('/en/shop', $this->valueOf($entries, StatDimension::Page));
        self::assertSame('en', $this->valueOf($entries, StatDimension::Locale));
    }

    /** Query strings are dropped — the page is the path only. */
    public function testQueryStringIsDropped(): void
    {
        $entries = $this->collector->collect($this->request('/fr/boutique?page=3&stones=onyx-noir'), $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('/fr/boutique', $this->valueOf($entries, StatDimension::Page));
    }

    /**
     * A reset-password token is a live capability URL. Stored verbatim it would
     * sit in the counter table indefinitely and be rendered on the admin screen.
     */
    public function testResetPasswordTokenIsMaskedOutOfThePath(): void
    {
        $token = \str_repeat('a1b2c3d4', 4);
        $request = $this->routedRequest('/fr/reinitialiser-mot-de-passe/'.$token, 'shop_reset_password', ['token' => $token]);

        $entries = $this->collector->collect($request, $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('/fr/reinitialiser-mot-de-passe/{token}', $this->valueOf($entries, StatDimension::Page));
    }

    /**
     * Localized routes match with a locale suffix (`shop_reset_password.fr`);
     * masking must still apply through the canonical-route fallback.
     */
    public function testLocaleSuffixedRouteNameStillMasks(): void
    {
        $token = 'AB12CD34';
        $request = $this->request('/fr/temoignage/'.$token);
        $request->attributes->set('_route', 'shop_testimonial_submit.fr');
        $request->attributes->set('_route_params', ['token' => $token, '_locale' => 'fr']);

        $entries = $this->collector->collect($request, $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('/fr/temoignage/{token}', $this->valueOf($entries, StatDimension::Page));
    }

    /**
     * Masking is keyed on the matched route, never on the shape of the path: a
     * product page whose slug happens to look like a token is untouched.
     */
    public function testRouteOutsideTheMaskingMapKeepsItsPathVerbatim(): void
    {
        $request = $this->routedRequest('/fr/produit/talisman-azur', 'shop_product', ['slug' => 'talisman-azur']);

        $entries = $this->collector->collect($request, $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('/fr/produit/talisman-azur', $this->valueOf($entries, StatDimension::Page));
    }

    /** Only the host of the referrer is kept, lowercased and de-www'd. */
    public function testExternalReferrerIsReducedToItsHost(): void
    {
        $request = $this->request('/fr/boutique');
        $request->headers->set('Referer', 'https://WWW.Instagram.com/alma_stella_paris/?igsh=secret');

        $entries = $this->collector->collect($request, $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame('instagram.com', $this->valueOf($entries, StatDimension::Referrer));
    }

    public function testInternalReferrerIsNotRecorded(): void
    {
        $request = $this->request('/fr/boutique');
        $request->headers->set('Referer', 'http://localhost/fr/');

        $entries = $this->collector->collect($request, $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertNull($this->valueOf($entries, StatDimension::Referrer));
    }

    public function testUnparseableReferrerIsNotRecorded(): void
    {
        $request = $this->request('/fr/boutique');
        $request->headers->set('Referer', 'not-a-url');

        $entries = $this->collector->collect($request, $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertNull($this->valueOf($entries, StatDimension::Referrer));
    }

    /**
     * `Referer` is attacker-controlled free text and every distinct value opens
     * a row: a host that is not a hostname must not become a counter.
     */
    public function testReferrerThatIsNotAHostnameIsNotRecorded(): void
    {
        foreach (['https://intranet/page', 'https://ex_ample.com/page', 'https://-nope.com/page'] as $referer) {
            $request = $this->request('/fr/boutique');
            $request->headers->set('Referer', $referer);

            $entries = $this->collector->collect($request, $this->htmlResponse());

            self::assertNotNull($entries, $referer);
            self::assertNull($this->valueOf($entries, StatDimension::Referrer), $referer);
        }
    }

    #[DataProvider('userAgentDevices')]
    public function testDeviceIsClassifiedFromTheUserAgent(string $userAgent, string $expected): void
    {
        $entries = $this->collector->collect($this->request('/fr/boutique', $userAgent), $this->htmlResponse());

        self::assertNotNull($entries);
        self::assertSame($expected, $this->valueOf($entries, StatDimension::Device));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function userAgentDevices(): iterable
    {
        yield 'desktop Chrome' => [self::DESKTOP_UA, 'desktop'];
        yield 'desktop Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'desktop'];
        yield 'iPhone' => [self::MOBILE_UA, 'mobile'];
        yield 'Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36', 'mobile'];
    }

    public function testValueIsTruncatedToTheColumnLength(): void
    {
        $entries = $this->collector->collect($this->request('/fr/'.\str_repeat('a', 400)), $this->htmlResponse());

        self::assertNotNull($entries);
        $page = $this->valueOf($entries, StatDimension::Page);
        self::assertNotNull($page);
        self::assertSame(PageViewStat::VALUE_MAX_LENGTH, \mb_strlen($page));
    }

    /**
     * The rule this whole class exists for: the catalog "load more" controller
     * fetches the next page of products as a 200 `text/html` fragment — the
     * `X-Requested-With` header it sets is the ONLY thing separating that fetch
     * from a real visit. Drop this and every scroll books extra page views.
     */
    public function testLoadMoreFetchIsIgnored(): void
    {
        $request = $this->request('/fr/boutique?page=2');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        self::assertNull($this->collector->collect($request, $this->htmlResponse()));
    }

    /** The sitemap, invoices and generated files are machinery, not audience. */
    public function testNonHtmlResponseIsIgnored(): void
    {
        $response = new Response('<?xml version="1.0"?>', Response::HTTP_OK, ['Content-Type' => 'application/xml']);

        self::assertNull($this->collector->collect($this->request('/sitemap.xml'), $response));
    }

    public function testNonGetRequestIsIgnored(): void
    {
        $request = Request::create('/fr/cart/add/1', 'POST');
        $request->headers->set('User-Agent', self::DESKTOP_UA);

        self::assertNull($this->collector->collect($request, $this->htmlResponse()));
    }

    public function testNonOkResponseIsIgnored(): void
    {
        $response = new Response('', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/html; charset=UTF-8']);

        self::assertNull($this->collector->collect($this->request('/fr/nope'), $response));
    }

    public function testBotUserAgentIsIgnored(): void
    {
        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'curl/8.5.0',
            'python-requests/2.31.0',
            'facebookexternalhit/1.1',
            'Mozilla/5.0 HeadlessChrome/120.0.0.0',
        ] as $userAgent) {
            self::assertNull(
                $this->collector->collect($this->request('/fr/boutique', $userAgent), $this->htmlResponse()),
                $userAgent,
            );
        }
    }

    public function testEmptyOrAbsentUserAgentIsIgnored(): void
    {
        self::assertNull($this->collector->collect($this->request('/fr/boutique', ''), $this->htmlResponse()));

        $headerless = $this->request('/fr/boutique');
        $headerless->headers->remove('User-Agent');
        self::assertNull($this->collector->collect($headerless, $this->htmlResponse()));
    }

    public function testExcludedPathIsIgnored(): void
    {
        foreach (['/admin', '/admin/product', '/_profiler/abc123', '/_wdt/abc123', '/assets/app.css', '/uploads/products/x.webp', '/storage/products/y.webp'] as $path) {
            self::assertNull(
                $this->collector->collect($this->request($path), $this->htmlResponse()),
                $path,
            );
        }
    }

    /** The owner browsing the public shop while logged into the back-office. */
    public function testLoggedInAdminIsIgnored(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security_admin', 'serialized-token');

        $request = Request::create('/fr/boutique', 'GET', cookies: [$session->getName() => 'whatever']);
        $request->attributes->set('_locale', 'fr');
        $request->headers->set('User-Agent', self::DESKTOP_UA);
        $request->setSession($session);

        self::assertTrue($request->hasPreviousSession(), 'The fixture must look like a returning session.');
        self::assertNull($this->collector->collect($request, $this->htmlResponse()));
    }

    /** A session without the admin marker is an ordinary customer. */
    public function testLoggedInCustomerIsStillCounted(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security_main', 'serialized-token');

        $request = Request::create('/fr/boutique', 'GET', cookies: [$session->getName() => 'whatever']);
        $request->attributes->set('_locale', 'fr');
        $request->headers->set('User-Agent', self::DESKTOP_UA);
        $request->setSession($session);

        self::assertNotNull($this->collector->collect($request, $this->htmlResponse()));
    }

    private function request(string $uri, string $userAgent = self::DESKTOP_UA, string $locale = 'fr'): Request
    {
        $request = Request::create($uri);
        $request->attributes->set('_locale', $locale);
        $request->headers->set('User-Agent', $userAgent);

        return $request;
    }

    /**
     * A request as the router leaves it: matched route plus its parameters,
     * which is what path masking keys on.
     *
     * @param array<string, string> $routeParams
     */
    private function routedRequest(string $uri, string $route, array $routeParams): Request
    {
        $request = $this->request($uri);
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', $routeParams + ['_locale' => 'fr']);

        return $request;
    }

    private function htmlResponse(): Response
    {
        return new Response('<html lang="fr"></html>', Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param list<array{StatDimension, string}> $entries
     */
    private function valueOf(array $entries, StatDimension $dimension): ?string
    {
        foreach ($entries as [$candidate, $value]) {
            if ($candidate === $dimension) {
                return $value;
            }
        }

        return null;
    }
}
