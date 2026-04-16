<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\OrderStatus;
use App\Repository\ContactMessageRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\SiteSettingsRepository;
use App\Repository\TestimonialRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ProductRepository $productRepository,
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly TestimonialRepository $testimonialRepository,
        private readonly SiteSettingsRepository $siteSettingsRepository,
    ) {
    }

    public function index(): Response
    {
        $revenueThisWeek = $this->orderRepository->revenueThisWeek();
        $revenueLastWeek = $this->orderRepository->revenueLastWeek();
        $ordersToday = $this->orderRepository->countTodayOrders();
        $ordersSameDayLastWeek = $this->orderRepository->countOrdersSameDayLastWeek();
        $pendingOrders = $this->orderRepository->countByStatus(OrderStatus::Pending);
        $processingOrders = $this->orderRepository->countByStatus(OrderStatus::Processing);
        $shippedOrders = $this->orderRepository->countByStatus(OrderStatus::Shipped);
        $unreadMessages = $this->contactMessageRepository->countUnread();
        $pendingTestimonials = $this->testimonialRepository->countPending();
        $shippedWithoutTracking = $this->orderRepository->countShippedWithoutTracking();
        $expiringSold = $this->productRepository->countExpiringSoldSoon();

        $actionItems = $processingOrders + $unreadMessages + $pendingTestimonials + $shippedWithoutTracking;

        $settings = $this->siteSettingsRepository->findOneBy([]);

        return $this->render('admin/dashboard.html.twig', [
            'orders_today' => $ordersToday,
            'orders_same_day_last_week' => $ordersSameDayLastWeek,
            'revenue_week' => $revenueThisWeek,
            'revenue_last_week' => $revenueLastWeek,
            'pending_orders' => $pendingOrders,
            'processing_orders' => $processingOrders,
            'shipped_orders' => $shippedOrders,
            'products_available' => $this->productRepository->countAvailable(),
            'recently_sold' => $this->productRepository->countRecentlySold(),
            'unread_messages' => $unreadMessages,
            'pending_testimonials' => $pendingTestimonials,
            'shipped_without_tracking' => $shippedWithoutTracking,
            'expiring_sold' => $expiringSold,
            'action_items' => $actionItems,
            'latest_orders' => $this->orderRepository->findLatest(5),
            'is_maintenance' => $settings?->isMaintenanceMode() ?? false,
        ]);
    }

    #[Route('/admin/flash-messages', name: 'admin_flush_flashes', methods: ['GET'])]
    public function flushFlashes(Request $request): JsonResponse
    {
        $session = $request->getSession();
        /** @var \Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface $session */
        $flashes = $session->getFlashBag()->all();

        $aggregated = [];
        foreach ($flashes as $type => $messages) {
            $counts = [];
            foreach ($messages as $msg) {
                $counts[$msg] = ($counts[$msg] ?? 0) + 1;
            }
            $aggregated[$type] = [];
            foreach ($counts as $message => $count) {
                $aggregated[$type][] = $count > 1
                    ? $this->pluralizeFlash((string) $message, $count)
                    : (string) $message;
            }
        }

        return $this->json($aggregated);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('✦ Alma Stella Paris')
            ->setFaviconPath('favicons-admin/favicon.ico')
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        $unreadMessages = $this->contactMessageRepository->countUnread();
        $pendingTestimonials = $this->testimonialRepository->countPending();
        $processingOrders = $this->orderRepository->countByStatus(OrderStatus::Processing);

        $messagesLabel = 'Messages de contact';
        if ($unreadMessages > 0) {
            $messagesLabel .= \sprintf(' (%d)', $unreadMessages);
        }

        $testimonialsLabel = 'Témoignages';
        if ($pendingTestimonials > 0) {
            $testimonialsLabel .= \sprintf(' (%d)', $pendingTestimonials);
        }

        $ordersLabel = 'Commandes';
        if ($processingOrders > 0) {
            $ordersLabel .= \sprintf(' (%d)', $processingOrders);
        }

        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(ProductCrudController::class, 'Produits', 'fa fa-gem');
        yield MenuItem::linkTo(ProductCategoryCrudController::class, 'Catégories', 'fa fa-tags');
        yield MenuItem::linkTo(StoneCrudController::class, 'Pierres', 'fa fa-diamond');
        yield MenuItem::linkTo(ShippingSettingsCrudController::class, 'Frais de port', 'fa fa-truck');
        yield MenuItem::section('Ventes');
        yield MenuItem::linkTo(OrderCrudController::class, $ordersLabel, 'fa fa-shopping-bag');
        yield MenuItem::linkTo(CustomerCrudController::class, 'Clients', 'fa fa-user');
        yield MenuItem::linkTo(PromotionCrudController::class, 'Promotions', 'fa fa-percent');
        yield MenuItem::section('Communication');
        yield MenuItem::linkTo(ContactMessageCrudController::class, $messagesLabel, 'fa fa-envelope');
        yield MenuItem::linkTo(TestimonialCrudController::class, $testimonialsLabel, 'fa fa-star');
        yield MenuItem::section('Réglages');
        yield MenuItem::linkTo(SiteSettingsCrudController::class, 'Paramètres du site', 'fa fa-cog');
        yield MenuItem::linkTo(AdminCrudController::class, 'Administrateurs', 'fa fa-users');
        yield MenuItem::section('');
        yield MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', '/');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/admin.css')
            ->addCssFile('vendor/cropperjs/cropper.min.css')
            ->addCssFile('css/admin-crop.css')
            ->addJsFile('vendor/cropperjs/cropper.min.js')
            ->addJsFile('js/admin-image-crop.js')
            ->addJsFile('js/admin-toast.js');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier());
    }

    private function pluralizeFlash(string $message, int $count): string
    {
        $plurals = [
            'L\'élément a été supprimé.' => '%d éléments supprimés.',
        ];

        if (isset($plurals[$message])) {
            return \sprintf($plurals[$message], $count);
        }

        return \sprintf('%s (×%d)', $message, $count);
    }
}
