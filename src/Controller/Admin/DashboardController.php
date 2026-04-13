<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
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
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'orders_today' => $this->orderRepository->countTodayOrders(),
            'revenue_week' => $this->orderRepository->revenueThisWeek(),
            'pending_orders' => $this->orderRepository->countByStatus(OrderStatus::Pending),
            'processing_orders' => $this->orderRepository->countByStatus(OrderStatus::Processing),
            'products_available' => $this->productRepository->countAvailable(),
            'recently_sold' => $this->productRepository->countRecentlySold(),
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
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><text y=%221.2em%22 font-size=%2296%22>✦</text></svg>')
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(ProductCrudController::class, 'Produits', 'fa fa-gem');
        yield MenuItem::linkTo(ProductCategoryCrudController::class, 'Catégories', 'fa fa-tags');
        yield MenuItem::linkTo(ShippingSettingsCrudController::class, 'Frais de port', 'fa fa-truck');
        yield MenuItem::section('Ventes');
        yield MenuItem::linkTo(OrderCrudController::class, 'Commandes', 'fa fa-shopping-bag');
        yield MenuItem::linkTo(CustomerCrudController::class, 'Clients', 'fa fa-user');
        yield MenuItem::linkTo(PromotionCrudController::class, 'Promotions', 'fa fa-percent');
        yield MenuItem::section('Communication');
        yield MenuItem::linkTo(ContactMessageCrudController::class, 'Messages de contact', 'fa fa-envelope');
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
