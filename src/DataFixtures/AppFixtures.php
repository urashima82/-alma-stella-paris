<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ShippingSettings;
use App\Entity\SiteSettings;
use App\Enum\OrderStatus;
use App\Enum\ShippingTier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->loadAdmins($manager);
        $this->loadShippingSettings($manager);
        $this->loadSiteSettings($manager);
        $categories = $this->loadCategories($manager);
        $products = $this->loadProducts($manager, $categories);
        $this->linkRelatedProducts($products);
        $this->loadOrders($manager, $products);

        $manager->flush();
    }

    private function loadShippingSettings(ObjectManager $manager): void
    {
        foreach (ShippingTier::cases() as $tier) {
            $manager->persist(ShippingSettings::createFromTier($tier));
        }
    }

    private function loadSiteSettings(ObjectManager $manager): void
    {
        $settings = new SiteSettings();
        $settings->setActiveCollection(SiteSettings::COLLECTION_ALL);
        $manager->persist($settings);
    }

    private function loadAdmins(ObjectManager $manager): void
    {
        $admins = [
            'admin@almastellaparis.com',
            'contact@nicolas-bede.fr',
        ];

        foreach ($admins as $email) {
            $admin = new Admin();
            $admin->setEmail($email);
            $manager->persist($admin);
        }
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function loadCategories(ObjectManager $manager): array
    {
        $data = [
            ['Necklaces', 'Colliers', 'colliers', 0],
            ['Earrings', 'Boucles d\'oreilles', 'boucles-d-oreilles', 1],
            ['Bracelets', 'Bracelets', 'bracelets', 2],
            ['Rings', 'Bagues', 'bagues', 3],
            ['Anklets', 'Chaînes de cheville', 'chaines-de-cheville', 4],
            ['Sets', 'Coffrets', 'coffrets', 5],
        ];

        $categories = [];
        foreach ($data as [$name, $nameFr, $slugFr, $position]) {
            $category = new ProductCategory();
            $category->setName($name);
            $category->setNameFr($nameFr);
            $category->setSlugFr($slugFr);
            $category->setPosition($position);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        return $categories;
    }

    /**
     * @param array<string, ProductCategory> $categories
     *
     * @return array<string, Product>
     */
    private function loadProducts(ObjectManager $manager, array $categories): array
    {
        $data = [
            [
                'Gold Star Pendant Necklace',
                'Pendentif Étoile Doré',
                'Delicate gold star pendant on a fine chain, inspired by the night sky over the Mexican desert. Made from water-resistant stainless steel with 18K gold plating.',
                'Délicat pendentif étoile en acier doré, inspiré du ciel nocturne au-dessus du désert mexicain. Acier inoxydable résistant à l\'eau avec placage or 18 carats.',
                38.00, ShippingTier::Standard, 'Necklaces', true, true, [Product::COUNTRY_MEXICO],
            ],
            [
                'Turquoise Stone Ring',
                'Bague Pierre Turquoise',
                'Statement turquoise ring set in gold-plated stainless steel. Each stone is unique, hand-selected for its vibrant color.',
                'Bague turquoise sertie dans de l\'acier inoxydable plaqué or. Chaque pierre est unique, sélectionnée à la main pour sa couleur vibrante.',
                28.00, ShippingTier::Standard, 'Rings', true, true, [Product::COUNTRY_MEXICO],
            ],
            [
                'Black Onyx Drop Earrings',
                'Boucles d\'Oreilles Onyx Noir',
                'Elegant drop earrings featuring polished black onyx stones. A timeless piece that transitions effortlessly from day to night.',
                'Élégantes boucles d\'oreilles pendantes en onyx noir poli. Une pièce intemporelle qui passe facilement du jour à la nuit.',
                32.00, ShippingTier::Standard, 'Earrings', true, true, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Layered Gold Chain Bracelet',
                'Bracelet Chaînes Dorées Superposées',
                'Multi-layered gold chain bracelet with a delicate bohemian feel. Water-resistant, perfect for everyday wear.',
                'Bracelet multi-chaînes dorées à l\'esprit bohème délicat. Résistant à l\'eau, parfait pour le quotidien.',
                26.00, ShippingTier::Standard, 'Bracelets', true, true, [Product::COUNTRY_FRANCE],
            ],
            [
                'Mother of Pearl Choker',
                'Ras-de-Cou Nacre',
                'Stunning choker necklace featuring natural mother of pearl elements. A statement piece inspired by the coasts of Oaxaca.',
                'Superbe ras-de-cou avec éléments en nacre naturelle. Une pièce forte inspirée des côtes d\'Oaxaca.',
                40.00, ShippingTier::Heavy, 'Necklaces', true, false, [Product::COUNTRY_MEXICO],
            ],
            [
                'Lapis Lazuli Stud Earrings',
                'Puces d\'Oreilles Lapis-Lazuli',
                'Minimalist stud earrings with genuine lapis lazuli stones. The deep blue evokes the Mediterranean sky.',
                'Puces d\'oreilles minimalistes en véritable lapis-lazuli. Le bleu profond évoque le ciel méditerranéen.',
                24.00, ShippingTier::Standard, 'Earrings', true, false, [Product::COUNTRY_FRANCE],
            ],
            [
                'Hammered Gold Cuff',
                'Manchette Dorée Martelée',
                'Bold hammered gold cuff bracelet. Each piece is hand-finished, making every cuff subtly unique.',
                'Manchette dorée martelée audacieuse. Chaque pièce est finie à la main, rendant chaque manchette subtilement unique.',
                52.00, ShippingTier::Heavy, 'Bracelets', true, false, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Shell & Gold Anklet',
                'Chaîne de Cheville Coquillage & Or',
                'Dainty anklet combining natural shell elements with gold-plated chain. Summer-ready and water-resistant.',
                'Chaîne de cheville délicate alliant coquillages naturels et chaîne plaquée or. Prête pour l\'été et résistante à l\'eau.',
                18.00, ShippingTier::Standard, 'Anklets', true, false, [Product::COUNTRY_MEXICO],
            ],
            [
                'Moonstone Solitaire Ring',
                'Bague Solitaire Pierre de Lune',
                'Ethereal moonstone solitaire ring with a luminous, milky glow. Set in brushed gold-plated steel.',
                'Bague solitaire en pierre de lune éthérée avec un éclat lumineux et laiteux. Sertie dans de l\'acier plaqué or brossé.',
                48.00, ShippingTier::Standard, 'Rings', true, false, [Product::COUNTRY_FRANCE],
            ],
            [
                'Coin Charm Necklace',
                'Collier Médaillon',
                'Vintage-inspired coin charm on a fine gold chain. A versatile everyday piece with old-world charm.',
                'Médaillon d\'inspiration vintage sur une fine chaîne dorée. Une pièce polyvalente au charme d\'antan.',
                34.00, ShippingTier::Standard, 'Necklaces', true, false, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Beaded Stone Bracelet',
                'Bracelet Perles de Pierre',
                'Natural stone bead bracelet with gold accent beads. Each stone carries its own unique pattern and energy.',
                'Bracelet de perles en pierre naturelle avec perles dorées. Chaque pierre porte son propre motif et énergie unique.',
                22.00, ShippingTier::Standard, 'Bracelets', true, false, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Pearl & Gold Hoops',
                'Créoles Perles & Or',
                'Modern gold hoops adorned with freshwater pearls. A contemporary twist on a timeless classic.',
                'Créoles dorées modernes ornées de perles d\'eau douce. Une touche contemporaine sur un classique intemporel.',
                36.00, ShippingTier::Standard, 'Earrings', true, false, [Product::COUNTRY_FRANCE],
            ],
        ];

        $products = [];
        foreach ($data as [$name, $nameFr, $desc, $descFr, $price, $tier, $cat, $published, $featured, $availableIn]) {
            $product = new Product();
            $product->setName($name);
            $product->setNameFr($nameFr);
            $product->setDescription($desc);
            $product->setDescriptionFr($descFr);
            $product->setBasePrice($price);
            $product->setShippingTier($tier);
            $product->setCategory($categories[$cat]);
            $product->setIsPublished($published);
            $product->setIsFeatured($featured);
            $product->setIsSoldOut(false);
            $product->setAvailableIn($availableIn);
            $manager->persist($product);
            $products[$name] = $product;
        }

        return $products;
    }

    /**
     * @param array<string, Product> $products
     */
    private function linkRelatedProducts(array $products): void
    {
        $products['Gold Star Pendant Necklace']->addRelatedProduct($products['Mother of Pearl Choker']);
        $products['Gold Star Pendant Necklace']->addRelatedProduct($products['Coin Charm Necklace']);
        $products['Gold Star Pendant Necklace']->addRelatedProduct($products['Turquoise Stone Ring']);

        $products['Turquoise Stone Ring']->addRelatedProduct($products['Moonstone Solitaire Ring']);
        $products['Turquoise Stone Ring']->addRelatedProduct($products['Beaded Stone Bracelet']);

        $products['Black Onyx Drop Earrings']->addRelatedProduct($products['Lapis Lazuli Stud Earrings']);
        $products['Black Onyx Drop Earrings']->addRelatedProduct($products['Pearl & Gold Hoops']);

        $products['Layered Gold Chain Bracelet']->addRelatedProduct($products['Hammered Gold Cuff']);
        $products['Layered Gold Chain Bracelet']->addRelatedProduct($products['Beaded Stone Bracelet']);
        $products['Layered Gold Chain Bracelet']->addRelatedProduct($products['Shell & Gold Anklet']);
    }

    /**
     * @param array<string, Product> $products
     */
    private function loadOrders(ObjectManager $manager, array $products): void
    {
        $orders = [
            [
                'reference' => 'ASP-2026-00001',
                'status' => OrderStatus::Delivered,
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@example.com',
                'locale' => 'en',
                'line1' => '742 Evergreen Terrace',
                'city' => 'Portland',
                'state' => 'OR',
                'postal' => '97201',
                'country' => 'US',

                'tracking' => 'CF123456789FR',
                'stripe_status' => 'succeeded',
                'items' => [['Gold Star Pendant Necklace', 38.00, 4.90]],
                'days_ago' => 21,
            ],
            [
                'reference' => 'ASP-2026-00002',
                'status' => OrderStatus::Shipped,
                'name' => 'Marie Dupont',
                'email' => 'marie.dupont@example.com',
                'locale' => 'fr',
                'line1' => '15 rue de la Paix',
                'city' => 'Paris',
                'state' => null,
                'postal' => '75002',
                'country' => 'FR',

                'tracking' => '6A12345678901',
                'stripe_status' => 'succeeded',
                'items' => [
                    ['Black Onyx Drop Earrings', 32.00, 4.90],
                    ['Layered Gold Chain Bracelet', 26.00, 4.90],
                ],
                'days_ago' => 5,
            ],
            [
                'reference' => 'ASP-2026-00003',
                'status' => OrderStatus::Processing,
                'name' => 'Emily Carter',
                'email' => 'emily.carter@example.com',
                'locale' => 'en',
                'line1' => '88 Queen Street West',
                'city' => 'Toronto',
                'state' => 'ON',
                'postal' => 'M5H 2N2',
                'country' => 'CA',

                'tracking' => null,
                'stripe_status' => 'succeeded',
                'items' => [['Mother of Pearl Choker', 40.00, 6.90]],
                'days_ago' => 2,
            ],
            [
                'reference' => 'ASP-2026-00004',
                'status' => OrderStatus::Shipped,
                'name' => 'Ana García López',
                'email' => 'ana.garcia@example.com',
                'locale' => 'en',
                'line1' => '4521 Sunset Blvd',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postal' => '90027',
                'country' => 'US',

                'tracking' => 'MX987654321',
                'stripe_status' => 'succeeded',
                'items' => [
                    ['Turquoise Stone Ring', 28.00, 4.90],
                    ['Beaded Stone Bracelet', 22.00, 4.90],
                ],
                'days_ago' => 3,
            ],
            [
                'reference' => 'ASP-2026-00005',
                'status' => OrderStatus::Pending,
                'name' => 'James Wilson',
                'email' => 'james.wilson@example.com',
                'locale' => 'en',
                'line1' => '12 Baker Street',
                'city' => 'London',
                'state' => null,
                'postal' => 'W1U 3BW',
                'country' => 'GB',

                'tracking' => null,
                'stripe_status' => null,
                'items' => [['Hammered Gold Cuff', 52.00, 6.90]],
                'days_ago' => 0,
            ],
            [
                'reference' => 'ASP-2026-00006',
                'status' => OrderStatus::Cancelled,
                'name' => 'Sophie Martin',
                'email' => 'sophie.martin@example.com',
                'locale' => 'fr',
                'line1' => '7 avenue des Champs-Élysées',
                'city' => 'Paris',
                'state' => null,
                'postal' => '75008',
                'country' => 'FR',

                'tracking' => null,
                'stripe_status' => 'canceled',
                'items' => [['Pearl & Gold Hoops', 36.00, 4.90]],
                'days_ago' => 10,
            ],
        ];

        foreach ($orders as $data) {
            $order = new Order();
            $order->setReference($data['reference']);
            $order->setStatus($data['status']);
            $order->setCustomerName($data['name']);
            $order->setCustomerEmail($data['email']);
            $order->setCustomerLocale($data['locale']);
            $order->setShippingAddressLine1($data['line1']);
            $order->setShippingCity($data['city']);
            $order->setShippingState($data['state']);
            $order->setShippingPostalCode($data['postal']);
            $order->setShippingCountry($data['country']);

            $order->setTrackingNumber($data['tracking']);
            $order->setStripePaymentStatus($data['stripe_status']);

            $total = 0.0;
            foreach ($data['items'] as [$productName, $price, $shipping]) {
                $item = new OrderItem();
                $item->setProduct($products[$productName] ?? null);
                $item->setProductName($productName);
                $item->setProductPrice($price);
                $item->setShippingCost($shipping);
                $order->addItem($item);
                $total += $price + $shipping;
            }

            $order->setTotalUsd($total);
            $manager->persist($order);
        }
    }
}
