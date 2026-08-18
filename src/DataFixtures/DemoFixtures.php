<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Promotion;
use App\Entity\Stone;
use App\Entity\Testimonial;
use App\Enum\DiscountType;
use App\Enum\OrderStatus;
use App\Enum\PromotionType;
use App\Enum\ShippingTier;
use App\Enum\TestimonialStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo content for local development only: fake customers, catalogue
 * products, orders, promotions and testimonials. Never load in production.
 */
class DemoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const GROUP = 'demo';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $customers = $this->loadCustomers($manager);
        $products = $this->loadCatalogueProducts($manager);
        $this->linkRelatedProducts($products);
        $this->loadOrders($manager, $products, $customers);
        $this->loadPromotions($manager);
        $this->loadTestimonials($manager);

        $manager->flush();
    }

    /**
     * @return list<class-string<Fixture>>
     */
    public function getDependencies(): array
    {
        return [CoreFixtures::class];
    }

    /**
     * @return list<string>
     */
    public static function getGroups(): array
    {
        return [self::GROUP];
    }

    /**
     * Import all 222 products from the official catalogue CSV.
     *
     * @return array<string, Product>
     */
    private function loadCatalogueProducts(ObjectManager $manager): array
    {
        $csvPath = \dirname(__DIR__, 2).'/docs/Catalogue/catalogue.csv';
        $handle = \fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open catalogue CSV at '.$csvPath);
        }

        // Skip header
        \fgetcsv($handle, 0, ';');

        $products = [];
        $index = 0;
        $stoneNameMap = $this->getStoneNameMap();
        $wordMap = $this->getTranslationWordMap();

        while (($row = \fgetcsv($handle, 0, ';')) !== false) {
            if (\count($row) < 8) {
                continue;
            }

            [, , $subcategory, $nameFr, $descriptionFr, $stoneCsv, , $price] = $row;

            $categoryReference = 'category-csv:'.$subcategory;
            if (!$this->hasReference($categoryReference, ProductCategory::class)) {
                continue;
            }
            $category = $this->getReference($categoryReference, ProductCategory::class);

            $nameEn = $this->translateProductName($nameFr, $subcategory, $wordMap);

            $product = new Product();
            $product->setName($nameEn);
            $product->setNameFr($nameFr);
            $product->setDescription($this->translateDescription($descriptionFr, $subcategory, $wordMap));
            $product->setDescriptionFr($descriptionFr);
            $product->setBasePrice((float) $price);
            $product->setShippingTier(ShippingTier::Standard);
            $product->setCategory($category);
            $product->setIsPublished(true);
            $product->setIsFeatured($index % 20 === 0);
            $product->setIsSoldOut(false);
            $product->setAvailableIn([Product::COUNTRY_FRANCE]);

            // Associate stones
            $this->associateStonesToProduct($product, $stoneCsv, $stoneNameMap);

            $manager->persist($product);
            $products[$nameFr] = $product;
            ++$index;
        }

        \fclose($handle);

        return $products;
    }

    /**
     * Map CSV stone French names to Stone entity English keys.
     *
     * @return array<string, string>
     */
    private function getStoneNameMap(): array
    {
        return [
            'Onyx noir' => 'Black Onyx',
            'Lapis Lazuli' => 'Lapis Lazuli',
            'Turquoise' => 'Turquoise',
            'Malachite' => 'Malachite',
            'Cornaline' => 'Carnelian',
            'Labradorite' => 'Labradorite',
            'Amazonite' => 'Amazonite',
            'Rhodonite' => 'Rhodonite',
            'Pierre de Lune' => 'Moonstone',
            'Péridot' => 'Peridot',
            'Quartz Rose' => 'Rose Quartz',
            'Smoky Quartz' => 'Smoky Quartz',
            'Sodalite' => 'Sodalite',
            'Nacre' => 'Mother of Pearl',
            'Agate blanche' => 'White Agate',
        ];
    }

    /**
     * @param array<string, string> $stoneNameMap
     */
    private function associateStonesToProduct(Product $product, string $stoneCsv, array $stoneNameMap): void
    {
        if ($stoneCsv === 'INCONNU' || $stoneCsv === '') {
            return;
        }

        // Handle multi-stone entries like "Amazonite, Malachite" or "Nacre, Onyx noir"
        $stoneNames = \array_map('trim', \explode(',', $stoneCsv));

        foreach ($stoneNames as $stoneFr) {
            // Skip non-natural stones
            if (\str_starts_with($stoneFr, 'Zirconium')) {
                continue;
            }

            $stoneEn = $stoneNameMap[$stoneFr] ?? null;
            if ($stoneEn !== null && $this->hasReference('stone-'.$stoneEn, Stone::class)) {
                $product->addStone($this->getReference('stone-'.$stoneEn, Stone::class));
            }
        }
    }

    /**
     * French-to-English word map for product name translation.
     *
     * @return array<string, string>
     */
    private function getTranslationWordMap(): array
    {
        return [
            // Multi-word phrases (matched before single words)
            'Pierre de Lune' => 'Moonstone',
            'Quartz Rose' => 'Rose Quartz',
            'Onyx Noir' => 'Black Onyx',
            'Cristal Noir' => 'Black Crystal',
            'Cristal Bronze' => 'Bronze Crystal',
            'Zircon Noir' => 'Black Zircon',
            'Lapis Lazuli' => 'Lapis Lazuli',
            'Agate Blanche' => 'White Agate',
            'Vert Pâle' => 'Pale Green',
            'Émail Noir' => 'Black Enamel',
            'Bleu Lagon' => 'Blue Lagoon',
            'Smoky Quartz' => 'Smoky Quartz',
            // Stones
            'Turquoise' => 'Turquoise', 'Malachite' => 'Malachite',
            'Cornaline' => 'Carnelian', 'Labradorite' => 'Labradorite',
            'Amazonite' => 'Amazonite', 'Rhodonite' => 'Rhodonite',
            'Sodalite' => 'Sodalite', 'Péridot' => 'Peridot',
            'Nacre' => 'Mother of Pearl', 'Améthyste' => 'Amethyst',
            'Zirconium' => 'Zirconium', 'Zircon' => 'Zircon',
            'Onyx' => 'Onyx', 'Saphir' => 'Sapphire', 'Rubis' => 'Ruby',
            'Cristal' => 'Crystal', 'Opale' => 'Opal',
            // Colors
            'Noir' => 'Black', 'Doré' => 'Golden', 'Dorée' => 'Golden',
            'Dorés' => 'Golden', 'Dorées' => 'Golden',
            'Blanc' => 'White', 'Blanche' => 'White', 'Blanches' => 'White',
            'Vert' => 'Green', 'Verte' => 'Green',
            'Bleu' => 'Blue', 'Bleue' => 'Blue',
            'Rouge' => 'Red', 'Rose' => 'Rose',
            'Gris' => 'Grey', 'Grise' => 'Grey',
            'Brun' => 'Brown', 'Brune' => 'Brown',
            'Bordeaux' => 'Burgundy', 'Bronze' => 'Bronze',
            'Pâle' => 'Pale', 'Châtain' => 'Chestnut',
            'Argenté' => 'Silver', 'Bicolore' => 'Two-Tone',
            // Celestial / Nature
            'Soleil' => 'Sun', 'Lune' => 'Moon', 'Étoile' => 'Star',
            'Minuit' => 'Midnight', 'Céleste' => 'Celestial',
            'Aube' => 'Dawn', 'Aurore' => 'Aurora',
            'Noctambule' => 'Night Owl', 'Solaire' => 'Solar',
            'Ciel' => 'Sky', 'Nuit' => 'Night', 'Nocturne' => 'Nocturnal',
            'Fleur' => 'Flower', 'Feuille' => 'Leaf', 'Trèfle' => 'Clover',
            'Forêt' => 'Forest', 'Tropical' => 'Tropical', 'Olivine' => 'Olive',
            // Adjectives
            'Brillant' => 'Brilliant', 'Brillante' => 'Brilliant',
            'Scintillant' => 'Sparkling', 'Scintillante' => 'Sparkling',
            'Facetté' => 'Faceted', 'Facettée' => 'Faceted',
            'Perlé' => 'Beaded', 'Perlée' => 'Beaded', 'Perlés' => 'Beaded',
            'Texturé' => 'Textured', 'Texturée' => 'Textured',
            'Géométrique' => 'Geometric',
            'Ajouré' => 'Openwork', 'Ajourée' => 'Openwork',
            'Iridescent' => 'Iridescent', 'Iridescente' => 'Iridescent',
            'Épurée' => 'Refined', 'Épuré' => 'Refined',
            'Classique' => 'Classic', 'Discret' => 'Subtle', 'Discrète' => 'Subtle',
            'Pure' => 'Pure', 'Apaisante' => 'Soothing',
            'Protectrice' => 'Protective', 'Mystérieux' => 'Mysterious',
            'Mystique' => 'Mystical', 'Romantique' => 'Romantic',
            'Raffinée' => 'Refined', 'Délicate' => 'Delicate',
            'Bohème' => 'Bohemian', 'Moderne' => 'Modern',
            'Tendre' => 'Soft', 'Éclatante' => 'Radiant', 'Éclatant' => 'Radiant',
            'Cristalline' => 'Crystalline', 'Rayonné' => 'Radiant',
            'Torsadé' => 'Twisted', 'Minimaliste' => 'Minimalist',
            'Sophistiqué' => 'Sophisticated', 'Pointilliste' => 'Pointillist',
            'Fleuri' => 'Floral', 'Aérien' => 'Airy', 'Aérienne' => 'Airy',
            'Étoilé' => 'Starry', 'Étoilée' => 'Starry', 'Poli' => 'Polished',
            'Douce' => 'Soft',
            // Shapes
            'Goutte' => 'Drop', 'Gouttes' => 'Drops',
            'Losange' => 'Diamond', 'Ovale' => 'Oval', 'Ovales' => 'Ovals',
            'Rond' => 'Round', 'Ronde' => 'Round',
            'Carré' => 'Square', 'Carrée' => 'Square',
            'Rectangle' => 'Rectangle', 'Cœur' => 'Heart', 'Cubique' => 'Cubic',
            // Craft / style
            'Mandala' => 'Mandala', 'Médaillon' => 'Medallion', 'Médaillons' => 'Medallions',
            'Monnaie' => 'Coin', 'Roue' => 'Wheel',
            'Dentelle' => 'Lace', 'Motif' => 'Pattern',
            'Harmonie' => 'Harmony', 'Luxe' => 'Luxe',
            'Serties' => 'Set', 'Enchâssé' => 'Encased',
            'Entrelacé' => 'Intertwined', 'Ciselé' => 'Chiseled',
            'Chaîne' => 'Chain', 'Chainés' => 'Chained',
            'Pavé' => 'Pavé', 'Miroir' => 'Mirror',
            'Épingle' => 'Pin', 'Arachnée' => 'Spiderweb',
            'Rayons' => 'Rays', 'Enamel' => 'Enamel',
            // Numbers / Misc
            'Double' => 'Double', 'Doubles' => 'Double',
            'Triple' => 'Triple', 'Trois' => 'Three', 'Quatre' => 'Four',
            'Rangs' => 'Bands', 'Grands' => 'Large', 'Petits' => 'Small',
            'Fin' => 'Fine', 'Fine' => 'Fine', 'Inversé' => 'Reversed',
            'Nord' => 'North', 'Perles' => 'Beads',
            'Éclat' => 'Radiance', 'Mystère' => 'Mystery',
            'Elegance' => 'Elegance', 'Mère' => 'Mother',
        ];
    }

    /**
     * @param array<string, string> $wordMap
     */
    private function translateProductName(string $frenchName, string $subcategory, array $wordMap): string
    {
        $typeSuffix = match ($subcategory) {
            'Bague chevalière' => 'Signet Ring',
            'Bague double' => 'Double Ring',
            'Bague fine' => 'Ring',
            'Bague manchette' => 'Cuff Ring',
            'Bague multirang' => 'Multi-Band Ring',
            'Bague à pampilles' => 'Charm Ring',
            'Duo de bague fine' => 'Ring Duo',
            'Boucles d\'oreilles chaînette' => 'Chain Earrings',
            'Boucles d\'oreilles créole' => 'Hoop Earrings',
            'Boucles d\'oreilles dormeuse' => 'Sleeper Earrings',
            'Boucles d\'oreilles longue' => 'Long Earrings',
            'Boucles d\'oreilles médaillon' => 'Medallion Earrings',
            'Boucles d\'oreilles panier' => 'Basket Earrings',
            'Boucles d\'oreilles pierre' => 'Stone Earrings',
            'Boucles d\'oreilles puce' => 'Stud Earrings',
            'Boucles d\'oreilles rectangle' => 'Rectangle Earrings',
            'Boucles d\'oreilles serpent' => 'Snake Earrings',
            'Bracelet chaîne' => 'Chain Bracelet',
            'Bracelet fin chaîne' => 'Fine Chain Bracelet',
            'Bracelet jonc double' => 'Double Bangle',
            'Bracelet jonc multirang' => 'Multi-Band Bangle',
            'Bracelet jonc simple' => 'Simple Bangle',
            'Duo de bracelet chaîne' => 'Chain Bracelet Duo',
            'Duo de bracelet pierre' => 'Stone Bracelet Duo',
            'Trio de bracelet pierre' => 'Stone Bracelet Trio',
            'Quatuor de bracelet pierre' => 'Stone Bracelet Set',
            'Collier grosse chaîne' => 'Bold Chain Necklace',
            'Collier médaillon pierre' => 'Stone Pendant Necklace',
            'Collier médaillon simple' => 'Pendant Necklace',
            'Collier multi-chaîne' => 'Multi-Chain Necklace',
            'Collier ras-du-cou fin' => 'Choker',
            'Sautoir' => 'Long Necklace',
            default => 'Jewelry',
        };

        // French prefixes to strip from product names (replaced by the type suffix)
        $prefixesToStrip = [
            'Chevalière ', 'Bague Double ', 'Bague Fine ', 'Manchette ',
            'Multirang ', 'Bague à Pampille ', 'Duo ', 'Créole ', 'Dormeuse ',
            'Boucles Médaillon ', 'Boucles Étoile ', 'Boucles Rectangulaires ',
            'Bracelet Chaîne ', 'Bracelet Fine Chaîne ', 'Bracelet Jonc Double ',
            'Bracelet Jonc Multirang ', 'Fine Chaîne ', 'Chaîne ',
            'Jonc Double ', 'Jonc Simple ', 'Jonc Multirang ',
            'Duo Chaîne ', 'Trio Pierre ', 'Quatuor Pierre ',
            'Collier Médaillon ', 'Collier ', 'Sautoir ',
            'Médaillon ', 'Bague ',
        ];

        $descriptivePart = $frenchName;
        foreach ($prefixesToStrip as $prefix) {
            if (\str_starts_with($descriptivePart, $prefix)) {
                $descriptivePart = \substr($descriptivePart, \strlen($prefix));
                break;
            }
        }

        // Translate multi-word phrases first, then single words
        $translated = $descriptivePart;
        foreach ($wordMap as $fr => $en) {
            if (\mb_strlen($fr) <= 3) {
                continue; // Skip short connectors for now
            }
            $translated = \str_replace($fr, $en, $translated);
        }

        // Remove remaining French connectors
        $translated = (string) \preg_replace('/\b(de|du|et|en|aux|la|le|les|des|un|une)\b/iu', '', $translated);
        // Clean up multiple spaces
        $translated = \trim((string) \preg_replace('/\s+/', ' ', $translated));

        if ($translated === '') {
            return $typeSuffix;
        }

        return $translated.' '.$typeSuffix;
    }

    /**
     * @param array<string, string> $wordMap
     */
    private function translateDescription(string $frenchDesc, string $subcategory, array $wordMap): string
    {
        $categoryEn = match (true) {
            \str_starts_with($subcategory, 'Bague'), \str_starts_with($subcategory, 'Duo de bague') => 'ring',
            \str_starts_with($subcategory, 'Boucles') => 'earrings',
            \str_starts_with($subcategory, 'Bracelet'), \str_starts_with($subcategory, 'Duo de bracelet'), \str_starts_with($subcategory, 'Trio'), \str_starts_with($subcategory, 'Quatuor') => 'bracelet',
            \str_starts_with($subcategory, 'Collier'), $subcategory === 'Sautoir' => 'necklace',
            default => 'jewelry piece',
        };

        // Build an English description from the French first sentence
        $sentences = \explode('.', $frenchDesc);
        $firstSentence = \trim($sentences[0]);

        $translated = $firstSentence;
        foreach ($wordMap as $fr => $en) {
            if (\mb_strlen($fr) <= 3) {
                continue;
            }
            $translated = \str_replace($fr, $en, $translated);
        }

        // If translation is too similar to French (didn't translate enough), use a template
        $frenchRatio = \similar_text($translated, $firstSentence) / \max(\mb_strlen($firstSentence), 1);
        if ($frenchRatio > 0.7) {
            return \sprintf(
                'Handcrafted %s in water-resistant gold-plated stainless steel. Unique piece curated between Paris and Mexico.',
                $categoryEn,
            );
        }

        return $translated.'. Water-resistant stainless steel, curated between Paris and Mexico.';
    }

    /**
     * @param array<string, Product> $products
     */
    private function linkRelatedProducts(array $products): void
    {
        // Products are now keyed by French name (nameFr) from the catalogue CSV
        $relations = [
            'Chevalière Soleil de Minuit' => ['Manchette Turquoise Éclatante', 'Bague Double Onyx Noir Soleil'],
            'Manchette Turquoise Éclatante' => ['Bague Fine Amazonite', 'Manchette Lapis Lazuli'],
            'Pierre de Lune Facette Pure' => ['Labradorite Goutte Iridescente', 'Amazonite Facettée Verte'],
            'Créole Minuit Épurée' => ['Onyx Noir Créole Serties', 'Créole Zircon Noir Discret'],
            'Nacre et Perles' => ['Malachite et Dorures', 'Sautoir Labradorite Miroir'],
            'Sautoir Labradorite Miroir' => ['Sautoir Rhodonite Mystique', 'Sautoir Sodalite et Onyx'],
            'Jonc Double Bicolore Lune' => ['Jonc Double Onyx Fleuri', 'Chaîne Turquoise Elegance'],
        ];

        foreach ($relations as $mainName => $relatedNames) {
            if (!isset($products[$mainName])) {
                continue;
            }
            foreach ($relatedNames as $relatedName) {
                if (isset($products[$relatedName])) {
                    $products[$mainName]->addRelatedProduct($products[$relatedName]);
                }
            }
        }
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
                'items' => [['Étoile Ajourée', 20.00, 10.00]],
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
                    ['Pierre de Lune Facette Pure', 29.00, 10.00],
                    ['Chaîne Turquoise Elegance', 22.00, 10.00],
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
                'items' => [['Nacre et Perles', 40.00, 10.00]],
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
                    ['Manchette Turquoise Éclatante', 35.00, 10.00],
                    ['Jonc Double Bicolore Lune', 35.00, 10.00],
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
                    ['Chevalière Soleil de Minuit', 22.00, 10.00],
                    ['Manchette Lapis Lazuli', 35.00, 10.00],
                    ['Créole Minuit Épurée', 25.00, 10.00],
                    ['Sautoir Labradorite Miroir', 45.00, 10.00],
                    ['Bague Double Onyx Noir Soleil', 28.00, 10.00],
                    ['Duo Nacre et Onyx Noir', 36.00, 10.00],
                    ['Turquoise Perlée Bohème', 27.00, 10.00],
                    ['Malachite et Dorures', 40.00, 10.00],
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
                'items' => [['Manchette Malachite Verte', 35.00, 10.00]],
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
                'items' => [['Créole Cristal Noir Classique', 25.00, 10.00]],
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
                'items' => [['Bague Fine Sodalite Bleu', 20.00, 10.00]],
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
                    ['Sodalite Cubique Pierre Douce', 29.00, 10.00],
                    ['Bracelet Fine Chaîne Nocturne', 16.00, 10.00],
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
                'items' => [['Amazonite Bleue Apaisante', 29.00, 10.00]],
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
                    ['Mandala Doré', 20.00, 10.00],
                    ['Péridot Éclat Naturel', 27.00, 10.00],
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
                'items' => [['Bague Double Pierre de Lune', 28.00, 10.00]],
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

    private function loadPromotions(ObjectManager $manager): void
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
        $necklacePromo->addCategory($this->getReference('category-Necklaces', ProductCategory::class));
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
