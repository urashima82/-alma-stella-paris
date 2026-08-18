<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Entity\PageViewStat;
use App\Enum\StatDimension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whether a request/response pair counts as a page view, and extracts
 * the values of the four dimensions. Stateless and framework-free on purpose:
 * this is where the feature can lie in silence — figures stay plausible while
 * counting the wrong thing — so it is the piece under unit test.
 *
 * What must NOT be counted: the catalog "load more" AJAX fetches return
 * `text/html` fragments of a page already counted when it was loaded, and
 * Estelle's own admin sessions would visibly inflate every figure at the
 * shop's current scale.
 *
 * The user-agent is matched to reject bots and to classify mobile/desktop, and
 * is never stored — no visitor identity leaves this class.
 */
final class PageViewCollector
{
    /**
     * Paths served by the app that are not audience: back-office, dev toolbar,
     * asset routes, uploaded files and the AI source-photo storage
     * (see `App\Controller\StorageController`).
     */
    private const array EXCLUDED_PREFIXES = ['/admin', '/_profiler', '/_wdt', '/assets', '/uploads', '/storage'];

    /** Session key written by the `admin` firewall (see `config/packages/security.yaml`). */
    private const string ADMIN_TOKEN_SESSION_KEY = '_security_admin';

    /**
     * Route parameters whose value is a secret and must never reach the counter
     * table: a reset-password token, an invoice capability URL or a testimonial
     * invite in a stored path would turn the aggregate into a durable registry
     * of live capability URLs, and the admin screen renders `page` values
     * verbatim. Keyed on the canonical route name (locale suffix stripped).
     *
     * @var array<string, list<string>> route name => parameters to mask
     */
    private const array MASKED_ROUTE_PARAMS = [
        'shop_reset_password' => ['token'],
        'shop_testimonial_submit' => ['token'],
        'shop_invoice_download' => ['reference', 'token'],
    ];

    private const string BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|headless/i';

    private const string MOBILE_PATTERN = '/Mobi|Android|iPhone|iPad|iPod|Windows Phone/i';

    /**
     * @return list<array{StatDimension, string}>|null the dimension/value pairs
     *                                                 to increment, or null when the request is not a countable page view
     */
    public function collect(Request $request, Response $response): ?array
    {
        if (!$this->isCountable($request, $response)) {
            return null;
        }

        $entries = [
            [StatDimension::Page, $this->page($request)],
            [StatDimension::Locale, $this->locale($request)],
            [StatDimension::Device, $this->device((string) $request->headers->get('User-Agent'))],
        ];

        $referrer = $this->referrer($request);
        if ($referrer !== null) {
            $entries[] = [StatDimension::Referrer, $referrer];
        }

        return $entries;
    }

    private function isCountable(Request $request, Response $response): bool
    {
        if ($request->getMethod() !== Request::METHOD_GET) {
            return false;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return false;
        }

        // Anything that is not an HTML document is machinery, not a page view:
        // the sitemap, invoices, generated files.
        $contentType = $response->headers->get('Content-Type');
        if ($contentType !== null && !\str_starts_with($contentType, 'text/html')) {
            return false;
        }

        // The catalog "load more" fetch (see catalog_load_more_controller.js):
        // a 200 `text/html` fragment of a page already counted when it was
        // loaded. The `X-Requested-With` header set by that controller is the
        // only thing distinguishing it from a real visit, so it is load-bearing
        // on both sides: drop it in the JS and every appended page of products
        // starts counting as a page view.
        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        $path = $request->getPathInfo();
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (\str_starts_with($path, $prefix)) {
                return false;
            }
        }

        $userAgent = (string) $request->headers->get('User-Agent');
        if ($userAgent === '' || \preg_match(self::BOT_PATTERN, $userAgent) === 1) {
            return false;
        }

        // The owner browsing the public shop while logged into the back-office.
        // The `admin` firewall shares the session with `main`, so its token
        // marker identifies those requests. No previous session means no admin,
        // and saves a needless session read.
        //
        // This reads the session, which is why the collector runs on
        // `kernel.response` and not on `kernel.terminate`: by terminate the
        // session listener has closed it and the response is flushed, so
        // reopening it throws (headers already sent). See PageViewSubscriber.
        if ($request->hasPreviousSession() && $request->getSession()->has(self::ADMIN_TOKEN_SESSION_KEY)) {
            return false;
        }

        return true;
    }

    /**
     * The measured path (verbatim, locale prefix included, query string already
     * absent from `getPathInfo()`), with any secret route parameter replaced by
     * its own name: `/fr/reinitialiser-mot-de-passe/{token}` rather than the
     * live capability URL. Routes outside {@see self::MASKED_ROUTE_PARAMS} are
     * untouched — the map is keyed on the matched route, so a path that merely
     * looks alike is never rewritten.
     */
    private function page(Request $request): string
    {
        $path = $request->getPathInfo();

        $route = $this->canonicalRoute($request);
        $params = $request->attributes->get('_route_params');
        if ($route === null || !\is_array($params) || !isset(self::MASKED_ROUTE_PARAMS[$route])) {
            return $this->truncate($path);
        }

        foreach (self::MASKED_ROUTE_PARAMS[$route] as $name) {
            $secret = $params[$name] ?? null;
            if (\is_string($secret) && $secret !== '') {
                $path = \str_replace($secret, '{'.$name.'}', $path);
            }
        }

        return $this->truncate($path);
    }

    /**
     * The matched route without its locale suffix: localized routes declared
     * with a path-per-locale array match as `shop_reset_password.fr`, while
     * `_canonical_route` carries the declared name.
     */
    private function canonicalRoute(Request $request): ?string
    {
        $canonical = $request->attributes->get('_canonical_route');
        if (\is_string($canonical) && $canonical !== '') {
            return $canonical;
        }

        $route = $request->attributes->get('_route');

        return \is_string($route) && $route !== '' ? \preg_replace('/\.(fr|en)$/', '', $route) : null;
    }

    /**
     * The request's locale — the routing attribute first, since the whole public
     * shop is locale-prefixed.
     */
    private function locale(Request $request): string
    {
        $routeLocale = $request->attributes->get('_locale');

        return \is_string($routeLocale) && $routeLocale !== ''
            ? $this->truncate($routeLocale)
            : $this->truncate($request->getLocale());
    }

    private function device(string $userAgent): string
    {
        return \preg_match(self::MOBILE_PATTERN, $userAgent) === 1 ? 'mobile' : 'desktop';
    }

    /**
     * The referring host, lowercased and stripped of `www.`: referrer paths can
     * carry tokens or identifiers, so only the host is ever kept. Internal
     * referrers and direct visits produce nothing at all.
     */
    private function referrer(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');
        if ($referer === null || $referer === '') {
            return null;
        }

        $host = \parse_url($referer, \PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return null;
        }

        $host = $this->normalizeHost($host);
        if ($host === '' || $host === $this->normalizeHost($request->getHost())) {
            return null;
        }

        // `Referer` is attacker-controlled free text, and every distinct value
        // opens a row: without this, a scripted run of junk referrers writes one
        // counter per request and the aggregate stops aggregating. A real
        // referring site is a hostname with a dot.
        if (!\str_contains($host, '.') || \filter_var($host, \FILTER_VALIDATE_DOMAIN, \FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        return $this->truncate($host);
    }

    private function normalizeHost(string $host): string
    {
        $host = \mb_strtolower($host);

        return \str_starts_with($host, 'www.') ? \substr($host, 4) : $host;
    }

    private function truncate(string $value): string
    {
        return \mb_substr($value, 0, PageViewStat::VALUE_MAX_LENGTH);
    }
}
