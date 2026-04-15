<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Promotion;
use App\Entity\ShippingSettings;
use App\Entity\SiteSettings;
use App\Entity\Testimonial;
use App\Enum\AdminRole;
use App\Enum\DiscountType;
use App\Enum\OrderStatus;
use App\Enum\PromotionType;
use App\Enum\ShippingTier;
use App\Enum\TestimonialStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadAdmins($manager);
        $this->loadShippingSettings($manager);
        $this->loadSiteSettings($manager);
        $customers = $this->loadCustomers($manager);
        $categories = $this->loadCategories($manager);
        $products = $this->loadProducts($manager, $categories);
        $this->linkRelatedProducts($products);
        $this->loadOrders($manager, $products, $customers);
        $this->loadPromotions($manager, $products, $categories);
        $this->loadTestimonials($manager);

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
            ['admin@almastellaparis.com', AdminRole::Admin, true],
            ['contact@nicolas-bede.fr', AdminRole::SuperAdmin, true],
        ];

        foreach ($admins as [$email, $role, $receivesEmails]) {
            $admin = new Admin();
            $admin->setEmail($email);
            $admin->setRole($role);
            $admin->setReceivesAdminEmails($receivesEmails);
            $manager->persist($admin);
        }
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function loadCategories(ObjectManager $manager): array
    {
        // Parent categories (roots)
        $roots = [
            ['Necklaces', 'Colliers', 'colliers', 0],
            ['Earrings', 'Boucles d\'oreilles', 'boucles-d-oreilles', 1],
            ['Bracelets', 'Bracelets', 'bracelets', 2],
            ['Rings', 'Bagues', 'bagues', 3],
            ['Anklets', 'Chaînes de cheville', 'chaines-de-cheville', 4],
            ['Sets', 'Coffrets', 'coffrets', 5],
        ];

        $categories = [];
        foreach ($roots as [$name, $nameFr, $slugFr, $position]) {
            $category = new ProductCategory();
            $category->setName($name);
            $category->setNameFr($nameFr);
            $category->setSlugFr($slugFr);
            $category->setPosition($position);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        // Subcategories: [name, nameFr, slugFr, position, parentKey]
        $children = [
            ['Pendants', 'Pendentifs', 'pendentifs', 0, 'Necklaces'],
            ['Chokers', 'Ras-de-cou', 'ras-de-cou', 1, 'Necklaces'],
            ['Long & Chain', 'Sautoirs & Chaînes', 'sautoirs-chaines', 2, 'Necklaces'],
            ['Hoops', 'Créoles', 'creoles', 0, 'Earrings'],
            ['Studs', 'Puces', 'puces', 1, 'Earrings'],
            ['Drops', 'Pendantes', 'pendantes', 2, 'Earrings'],
            ['Chain', 'Chaînes', 'chaines', 0, 'Bracelets'],
            ['Cuffs', 'Manchettes', 'manchettes', 1, 'Bracelets'],
            ['Beaded', 'Perles & Pierres', 'perles-pierres', 2, 'Bracelets'],
            ['Stone Rings', 'Pierres', 'pierres', 0, 'Rings'],
            ['Plain & Stackable', 'Simples & Empilables', 'simples-empilables', 1, 'Rings'],
        ];

        foreach ($children as [$name, $nameFr, $slugFr, $position, $parentKey]) {
            $category = new ProductCategory();
            $category->setName($name);
            $category->setNameFr($nameFr);
            $category->setSlugFr($slugFr);
            $category->setPosition($position);
            $category->setParent($categories[$parentKey]);
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
                35.00, 44.00, ShippingTier::Standard, 'Pendants', true, true, [Product::COUNTRY_MEXICO],
            ],
            [
                'Turquoise Stone Ring',
                'Bague Pierre Turquoise',
                'Statement turquoise ring set in gold-plated stainless steel. Each stone is unique, hand-selected for its vibrant color.',
                'Bague turquoise sertie dans de l\'acier inoxydable plaqué or. Chaque pierre est unique, sélectionnée à la main pour sa couleur vibrante.',
                26.00, null, ShippingTier::Standard, 'Stone Rings', true, true, [Product::COUNTRY_MEXICO],
            ],
            [
                'Black Onyx Drop Earrings',
                'Boucles d\'Oreilles Onyx Noir',
                'Elegant drop earrings featuring polished black onyx stones. A timeless piece that transitions effortlessly from day to night.',
                'Élégantes boucles d\'oreilles pendantes en onyx noir poli. Une pièce intemporelle qui passe facilement du jour à la nuit.',
                29.00, 39.00, ShippingTier::Standard, 'Drops', true, true, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Layered Gold Chain Bracelet',
                'Bracelet Chaînes Dorées Superposées',
                'Multi-layered gold chain bracelet with a delicate bohemian feel. Water-resistant, perfect for everyday wear.',
                'Bracelet multi-chaînes dorées à l\'esprit bohème délicat. Résistant à l\'eau, parfait pour le quotidien.',
                24.00, null, ShippingTier::Standard, 'Chain', true, true, [Product::COUNTRY_FRANCE],
            ],
            [
                'Mother of Pearl Choker',
                'Ras-de-Cou Nacre',
                'Stunning choker necklace featuring natural mother of pearl elements. A statement piece inspired by the coasts of Oaxaca.',
                'Superbe ras-de-cou avec éléments en nacre naturelle. Une pièce forte inspirée des côtes d\'Oaxaca.',
                37.00, null, ShippingTier::Heavy, 'Chokers', true, false, [Product::COUNTRY_MEXICO],
            ],
            [
                'Lapis Lazuli Stud Earrings',
                'Puces d\'Oreilles Lapis-Lazuli',
                'Minimalist stud earrings with genuine lapis lazuli stones. The deep blue evokes the Mediterranean sky.',
                'Puces d\'oreilles minimalistes en véritable lapis-lazuli. Le bleu profond évoque le ciel méditerranéen.',
                22.00, null, ShippingTier::Standard, 'Studs', true, false, [Product::COUNTRY_FRANCE],
            ],
            [
                'Hammered Gold Cuff',
                'Manchette Dorée Martelée',
                'Bold hammered gold cuff bracelet. Each piece is hand-finished, making every cuff subtly unique.',
                'Manchette dorée martelée audacieuse. Chaque pièce est finie à la main, rendant chaque manchette subtilement unique.',
                48.00, 60.00, ShippingTier::Heavy, 'Cuffs', true, false, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Shell & Gold Anklet',
                'Chaîne de Cheville Coquillage & Or',
                'Dainty anklet combining natural shell elements with gold-plated chain. Summer-ready and water-resistant.',
                'Chaîne de cheville délicate alliant coquillages naturels et chaîne plaquée or. Prête pour l\'été et résistante à l\'eau.',
                17.00, null, ShippingTier::Standard, 'Anklets', true, false, [Product::COUNTRY_MEXICO],
            ],
            [
                'Moonstone Solitaire Ring',
                'Bague Solitaire Pierre de Lune',
                'Ethereal moonstone solitaire ring with a luminous, milky glow. Set in brushed gold-plated steel.',
                'Bague solitaire en pierre de lune éthérée avec un éclat lumineux et laiteux. Sertie dans de l\'acier plaqué or brossé.',
                44.00, null, ShippingTier::Standard, 'Stone Rings', true, false, [Product::COUNTRY_FRANCE],
            ],
            [
                'Coin Charm Necklace',
                'Collier Médaillon',
                'Vintage-inspired coin charm on a fine gold chain. A versatile everyday piece with old-world charm.',
                'Médaillon d\'inspiration vintage sur une fine chaîne dorée. Une pièce polyvalente au charme d\'antan.',
                31.00, null, ShippingTier::Standard, 'Long & Chain', true, false, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Beaded Stone Bracelet',
                'Bracelet Perles de Pierre',
                'Natural stone bead bracelet with gold accent beads. Each stone carries its own unique pattern and energy.',
                'Bracelet de perles en pierre naturelle avec perles dorées. Chaque pierre porte son propre motif et énergie unique.',
                20.00, null, ShippingTier::Standard, 'Beaded', true, false, [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
            ],
            [
                'Pearl & Gold Hoops',
                'Créoles Perles & Or',
                'Modern gold hoops adorned with freshwater pearls. A contemporary twist on a timeless classic.',
                'Créoles dorées modernes ornées de perles d\'eau douce. Une touche contemporaine sur un classique intemporel.',
                33.00, 41.00, ShippingTier::Standard, 'Hoops', true, false, [Product::COUNTRY_FRANCE],
            ],
        ];

        $products = [];
        foreach ($data as [$name, $nameFr, $desc, $descFr, $price, $compareAtPrice, $tier, $cat, $published, $featured, $availableIn]) {
            $product = new Product();
            $product->setName($name);
            $product->setNameFr($nameFr);
            $product->setDescription($desc);
            $product->setDescriptionFr($descFr);
            $product->setBasePrice($price);
            $product->setCompareAtPrice($compareAtPrice);
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
     * @return array<string, Customer>
     */
    private function loadCustomers(ObjectManager $manager): array
    {
        $data = [
            [
                'email' => 'sarah.johnson@example.com',
                'password' => 'password123',
                'firstName' => 'Sarah',
                'lastName' => 'Johnson',
                'addresses' => [
                    ['Home', '742 Evergreen Terrace', null, 'Portland', 'OR', '97201', 'US', true],
                ],
            ],
            [
                'email' => 'marie.dupont@example.com',
                'password' => 'password123',
                'firstName' => 'Marie',
                'lastName' => 'Dupont',
                'addresses' => [
                    ['Domicile', '15 rue de la Paix', null, 'Paris', null, '75002', 'FR', true],
                    ['Bureau', '100 avenue de la République', '3e étage', 'Paris', null, '75011', 'FR', false],
                ],
            ],
            [
                'email' => 'emily.carter@example.com',
                'password' => 'password123',
                'firstName' => 'Emily',
                'lastName' => 'Carter',
                'addresses' => [
                    ['Home', '88 Queen Street West', null, 'Toronto', 'ON', 'M5H 2N2', 'CA', true],
                ],
            ],
        ];

        $customers = [];
        foreach ($data as $item) {
            $customer = new Customer();
            $customer->setEmail($item['email']);
            $customer->setFirstName($item['firstName']);
            $customer->setLastName($item['lastName']);
            $customer->setPassword($this->passwordHasher->hashPassword($customer, $item['password']));

            foreach ($item['addresses'] as [$label, $line1, $line2, $city, $state, $postal, $country, $isDefault]) {
                $address = new CustomerAddress();
                $address->setLabel($label);
                $address->setAddressLine1($line1);
                $address->setAddressLine2($line2);
                $address->setCity($city);
                $address->setState($state);
                $address->setPostalCode($postal);
                $address->setCountry($country);
                $address->setIsDefault($isDefault);
                $customer->addAddress($address);
            }

            $manager->persist($customer);
            $customers[$item['email']] = $customer;
        }

        return $customers;
    }

    /**
     * @param array<string, Product>  $products
     * @param array<string, Customer> $customers
     */
    private function loadOrders(ObjectManager $manager, array $products, array $customers): void
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
                'items' => [['Gold Star Pendant Necklace', 35.00, 10.00]],
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
                    ['Black Onyx Drop Earrings', 29.00, 10.00],
                    ['Layered Gold Chain Bracelet', 24.00, 10.00],
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
                'items' => [['Mother of Pearl Choker', 37.00, 15.00]],
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
                    ['Turquoise Stone Ring', 26.00, 10.00],
                    ['Beaded Stone Bracelet', 20.00, 10.00],
                ],
                'days_ago' => 3,
            ],
            [
                'reference' => 'ASP-2026-00005',
                'status' => OrderStatus::Processing,
                'name' => 'Catherine Dubois',
                'email' => 'catherine.dubois@example.com',
                'locale' => 'fr',
                'line1' => '24 boulevard Haussmann',
                'city' => 'Paris',
                'state' => null,
                'postal' => '75009',
                'country' => 'FR',

                'tracking' => null,
                'stripe_status' => 'succeeded',
                'items' => [
                    ['Gold Star Pendant Necklace', 35.00, 10.00],
                    ['Turquoise Stone Ring', 26.00, 10.00],
                    ['Black Onyx Drop Earrings', 29.00, 10.00],
                    ['Layered Gold Chain Bracelet', 24.00, 10.00],
                    ['Mother of Pearl Choker', 37.00, 15.00],
                    ['Lapis Lazuli Stud Earrings', 22.00, 10.00],
                    ['Hammered Gold Cuff', 48.00, 15.00],
                    ['Shell & Gold Anklet', 17.00, 10.00],
                    ['Moonstone Solitaire Ring', 44.00, 10.00],
                    ['Coin Charm Necklace', 31.00, 10.00],
                    ['Beaded Stone Bracelet', 20.00, 10.00],
                    ['Pearl & Gold Hoops', 33.00, 10.00],
                    ['Rose Quartz Pendant Necklace', 39.00, 10.00],
                    ['Aventurine Twist Ring', 28.00, 10.00],
                    ['Tiger Eye Huggie Earrings', 24.00, 10.00],
                    ['Amethyst Chain Bracelet', 31.00, 10.00],
                    ['Jade Teardrop Necklace', 42.00, 15.00],
                    ['Garnet Cluster Studs', 26.00, 10.00],
                    ['Citrine Wave Bangle', 35.00, 15.00],
                    ['Coral & Gold Ankle Chain', 18.00, 10.00],
                    ['Agate Slice Pendant', 40.00, 10.00],
                    ['Opal Crescent Earrings', 46.00, 10.00],
                    ['Malachite Signet Ring', 33.00, 10.00],
                    ['Carnelian Sun Necklace', 37.00, 10.00],
                    ['Labradorite Charm Bracelet', 29.00, 10.00],
                    ['Amazonite Drop Earrings', 26.00, 10.00],
                    ['Smoky Quartz Chain Ring', 22.00, 10.00],
                    ['Rhodonite Heart Pendant', 35.00, 10.00],
                    ['Peridot Vine Anklet', 20.00, 10.00],
                    ['Jasper Bohemian Cuff', 52.00, 15.00],
                ],
                'days_ago' => 1,
            ],
            [
                'reference' => 'ASP-2026-00006',
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
                'items' => [['Hammered Gold Cuff', 48.00, 15.00]],
                'days_ago' => 0,
            ],
            [
                'reference' => 'ASP-2026-00007',
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
                'items' => [['Pearl & Gold Hoops', 33.00, 10.00]],
                'days_ago' => 10,
            ],
            // --- Abandoned order test cases (pending, no payment initiated) ---
            // Pending 3 days ago, no PaymentIntent → cleaner SHOULD delete
            [
                'reference' => 'ASP-2026-00011',
                'status' => OrderStatus::Pending,
                'name' => 'David Thompson',
                'email' => 'david.thompson@example.com',
                'locale' => 'en',
                'line1' => '200 University Ave',
                'city' => 'Palo Alto',
                'state' => 'CA',
                'postal' => '94301',
                'country' => 'US',

                'tracking' => null,
                'stripe_status' => null,
                'items' => [['Turquoise Stone Ring', 26.00, 10.00]],
                'days_ago' => 3,
            ],
            // Pending 7 days ago, no PaymentIntent → cleaner SHOULD delete
            [
                'reference' => 'ASP-2026-00012',
                'status' => OrderStatus::Pending,
                'name' => 'Camille Lefèvre',
                'email' => 'camille.lefevre@example.com',
                'locale' => 'fr',
                'line1' => '8 place Bellecour',
                'city' => 'Lyon',
                'state' => null,
                'postal' => '69002',
                'country' => 'FR',

                'tracking' => null,
                'stripe_status' => null,
                'items' => [
                    ['Lapis Lazuli Stud Earrings', 22.00, 10.00],
                    ['Shell & Gold Anklet', 17.00, 10.00],
                ],
                'days_ago' => 7,
            ],
            // --- Testimonial test orders ---
            // Delivered 18 days ago, NO testimonial → scheduler SHOULD send email (EN)
            [
                'reference' => 'ASP-2026-00008',
                'status' => OrderStatus::Delivered,
                'name' => 'Rachel Green',
                'email' => 'rachel.green@example.com',
                'locale' => 'en',
                'line1' => '90 Bedford Street, Apt 19',
                'city' => 'New York',
                'state' => 'NY',
                'postal' => '10014',
                'country' => 'US',

                'tracking' => 'US123456789EN',
                'stripe_status' => 'succeeded',
                'items' => [['Lapis Lazuli Stud Earrings', 22.00, 10.00]],
                'days_ago' => 18,
            ],
            // Delivered 25 days ago, NO testimonial → scheduler SHOULD send email (FR)
            [
                'reference' => 'ASP-2026-00009',
                'status' => OrderStatus::Delivered,
                'name' => 'Lucie Moreau',
                'email' => 'lucie.moreau@example.com',
                'locale' => 'fr',
                'line1' => '42 rue du Faubourg Saint-Honoré',
                'city' => 'Paris',
                'state' => null,
                'postal' => '75008',
                'country' => 'FR',

                'tracking' => 'CF987654321FR',
                'stripe_status' => 'succeeded',
                'items' => [
                    ['Coin Charm Necklace', 31.00, 10.00],
                    ['Shell & Gold Anklet', 17.00, 10.00],
                ],
                'days_ago' => 25,
            ],
            // Delivered 10 days ago, NO testimonial → scheduler should NOT send (< 14 days)
            [
                'reference' => 'ASP-2026-00010',
                'status' => OrderStatus::Delivered,
                'name' => 'Karen Smith',
                'email' => 'karen.smith@example.com',
                'locale' => 'en',
                'line1' => '55 Yonge Street',
                'city' => 'Toronto',
                'state' => 'ON',
                'postal' => 'M5E 1J4',
                'country' => 'CA',

                'tracking' => 'CA555666777',
                'stripe_status' => 'succeeded',
                'items' => [['Moonstone Solitaire Ring', 44.00, 10.00]],
                'days_ago' => 10,
            ],
        ];

        $invoiceSequence = 0;

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

            // Link to customer account if exists
            if (isset($customers[$data['email']])) {
                $order->setCustomer($customers[$data['email']]);
            }

            $order->setTrackingNumber($data['tracking']);
            $order->setStripePaymentStatus($data['stripe_status']);

            // Shift order dates to simulate past orders
            if ($data['days_ago'] > 0) {
                $pastDate = new \DateTimeImmutable(\sprintf('-%d days', $data['days_ago']));
                $ref = new \ReflectionProperty(Order::class, 'createdAt');
                $ref->setValue($order, $pastDate);
                $ref = new \ReflectionProperty(Order::class, 'updatedAt');
                $ref->setValue($order, $pastDate);
            }

            if ($data['stripe_status'] === 'succeeded') {
                $order->setPaidAt($order->getCreatedAt());
                ++$invoiceSequence;
                $order->setInvoiceNumber(\sprintf('FA-%s-%05d', \date('Y'), $invoiceSequence));
            }

            $total = 0.0;
            foreach ($data['items'] as [$productName, $price, $shipping]) {
                $product = $products[$productName] ?? null;
                $item = new OrderItem();
                $item->setProduct($product);
                $item->setProductName($productName);
                $item->setProductNameFr($product !== null ? $product->getNameFr() : $productName);
                $item->setProductPrice($price);
                $item->setShippingCost($shipping);
                $order->addItem($item);
                $total += $price + $shipping;
            }

            $order->setTotalEur($total);
            $manager->persist($order);
        }
    }

    /**
     * @param Product[]         $products
     * @param ProductCategory[] $categories
     */
    private function loadPromotions(ObjectManager $manager, array $products, array $categories): void
    {
        // 1. Product automatic: -15% on necklaces
        $necklacePromo = new Promotion();
        $necklacePromo->setName('Necklaces promo -15%');
        $necklacePromo->setNameFr('Promo colliers -15%');
        $necklacePromo->setType(PromotionType::ProductAutomatic);
        $necklacePromo->setDiscountType(DiscountType::Percentage);
        $necklacePromo->setDiscountValue(15.00);
        $necklacePromo->setIsActive(true);
        $necklacePromo->setOverridesCompareAtPrice(false);
        $necklacePromo->setStartsAt(new \DateTimeImmutable('-7 days'));
        $necklacePromo->setEndsAt(new \DateTimeImmutable('+30 days'));
        if (isset($categories['Necklaces'])) {
            $necklacePromo->addCategory($categories['Necklaces']);
        }
        $manager->persist($necklacePromo);

        // 2. Cart automatic: -5€ over 75€
        $cartAutoPromo = new Promotion();
        $cartAutoPromo->setName('5€ off over 75€');
        $cartAutoPromo->setNameFr('5 € offerts dès 75 €');
        $cartAutoPromo->setType(PromotionType::CartAutomatic);
        $cartAutoPromo->setDiscountType(DiscountType::FixedAmount);
        $cartAutoPromo->setDiscountValue(5.00);
        $cartAutoPromo->setIsActive(true);
        $cartAutoPromo->setIsCumulable(true);
        $cartAutoPromo->setMinimumAmountEur(75.00);
        $manager->persist($cartAutoPromo);

        // 3. Cart automatic: -10% over 55€ (non-cumulable, competes with #2)
        $cartAutoPromoNonCumulable = new Promotion();
        $cartAutoPromoNonCumulable->setName('-10% over 55€');
        $cartAutoPromoNonCumulable->setNameFr('-10% dès 55 € d\'achat');
        $cartAutoPromoNonCumulable->setType(PromotionType::CartAutomatic);
        $cartAutoPromoNonCumulable->setDiscountType(DiscountType::Percentage);
        $cartAutoPromoNonCumulable->setDiscountValue(10.00);
        $cartAutoPromoNonCumulable->setIsActive(true);
        $cartAutoPromoNonCumulable->setIsCumulable(false);
        $cartAutoPromoNonCumulable->setMinimumAmountEur(55.00);
        $manager->persist($cartAutoPromoNonCumulable);

        // 4. Cart automatic: -15% over 90€ (non-cumulable, competes with #3)
        $cartAutoPromo15 = new Promotion();
        $cartAutoPromo15->setName('-15% over 90€');
        $cartAutoPromo15->setNameFr('-15% dès 90 € d\'achat');
        $cartAutoPromo15->setType(PromotionType::CartAutomatic);
        $cartAutoPromo15->setDiscountType(DiscountType::Percentage);
        $cartAutoPromo15->setDiscountValue(15.00);
        $cartAutoPromo15->setIsActive(true);
        $cartAutoPromo15->setIsCumulable(false);
        $cartAutoPromo15->setMinimumAmountEur(90.00);
        $manager->persist($cartAutoPromo15);

        // 5. Cart code: BIENVENUE10 — 10% off
        $codePromo = new Promotion();
        $codePromo->setName('Welcome 10% off');
        $codePromo->setNameFr('Code bienvenue 10%');
        $codePromo->setCode('BIENVENUE10');
        $codePromo->setType(PromotionType::CartCode);
        $codePromo->setDiscountType(DiscountType::Percentage);
        $codePromo->setDiscountValue(10.00);
        $codePromo->setIsActive(true);
        $codePromo->setMaxUsagesPerEmail(1);
        $manager->persist($codePromo);
    }

    private function loadTestimonials(ObjectManager $manager): void
    {
        $testimonials = [
            [
                'email' => 'sarah.johnson@example.com',
                'rating' => 5,
                'text' => 'I absolutely love my Gold Star Pendant! The quality is outstanding and it truly is water-resistant. I wear it every day, even to the beach. The packaging was beautiful and delivery was faster than expected.',
                'firstName' => 'Sarah',
                'lastNameInitial' => 'J',
                'city' => 'Portland, OR',
                'status' => TestimonialStatus::Approved,
                'daysAgo' => 7,
            ],
            [
                'email' => 'claire.m@example.com',
                'rating' => 5,
                'text' => "J'ai offert le collier en turquoise à ma mère et elle ne le quitte plus ! La pierre est magnifique, chaque pièce est vraiment unique. Merci Estelle pour cette sélection avec tant de soin.",
                'firstName' => 'Claire',
                'lastNameInitial' => 'M',
                'city' => 'Lyon',
                'status' => TestimonialStatus::Approved,
                'daysAgo' => 12,
            ],
            [
                'email' => 'jessica.w@example.com',
                'rating' => 4,
                'text' => 'Beautiful craftsmanship and the natural stones are gorgeous. I ordered the Moonstone Ring and it arrived in perfect condition. The only reason for 4 stars is that shipping took a bit longer than expected, but it was worth the wait!',
                'firstName' => 'Jessica',
                'lastNameInitial' => 'W',
                'city' => 'Toronto',
                'status' => TestimonialStatus::Approved,
                'daysAgo' => 3,
            ],
            [
                'email' => 'amelie.d@example.com',
                'rating' => 5,
                'text' => "Des bijoux qui ont une âme ! J'ai craqué pour le bracelet en lapis-lazuli et les boucles d'oreilles en onyx. La qualité est irréprochable et le service client adorable.",
                'firstName' => 'Amélie',
                'lastNameInitial' => 'D',
                'city' => 'Montréal',
                'status' => TestimonialStatus::Approved,
                'daysAgo' => 18,
            ],
            [
                'email' => 'pending.reviewer@example.com',
                'rating' => 5,
                'text' => 'Wonderful jewelry, can\'t wait to order more!',
                'firstName' => 'Emily',
                'lastNameInitial' => 'C',
                'city' => 'New York',
                'status' => TestimonialStatus::Pending,
                'daysAgo' => 1,
            ],
        ];

        foreach ($testimonials as $data) {
            $testimonial = new Testimonial();
            $testimonial->setEmail($data['email']);
            $testimonial->setRating($data['rating']);
            $testimonial->setText($data['text']);
            $testimonial->setFirstName($data['firstName']);
            $testimonial->setLastNameInitial($data['lastNameInitial']);
            $testimonial->setCity($data['city']);
            $testimonial->setStatus($data['status']);
            $testimonial->setSubmittedAt(new \DateTimeImmutable(\sprintf('-%d days', $data['daysAgo'])));
            $manager->persist($testimonial);
        }
    }
}
