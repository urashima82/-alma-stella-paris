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
use App\Entity\Stone;
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
        $stones = $this->loadStones($manager);
        $products = $this->loadProducts($manager, $categories, $stones);
        $this->generateBulkProducts($manager, $categories, $stones);
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
        // Parent categories (roots): [name, nameFr, slugFr, position, description, descriptionFr]
        $roots = [
            ['Necklaces', 'Colliers', 'colliers', 0, 'Pendants, chokers & chains — each piece curated between Paris and Mexico.', 'Pendentifs, ras-de-cou & chaînes — chaque pièce chinée entre Paris et le Mexique.'],
            ['Earrings', 'Boucles d\'oreilles', 'boucles-d-oreilles', 1, 'Hoops, studs & drops to frame your face with effortless elegance.', 'Créoles, puces & pendantes pour sublimer votre visage avec élégance.'],
            ['Bracelets', 'Bracelets', 'bracelets', 2, 'Chains, cuffs & beaded designs — water-resistant everyday companions.', 'Chaînes, manchettes & perles — des compagnons du quotidien résistants à l\'eau.'],
            ['Rings', 'Bagues', 'bagues', 3, 'Natural stones & stackable bands to adorn every finger.', 'Pierres naturelles & anneaux empilables pour orner chaque doigt.'],
            ['Anklets', 'Chaînes de cheville', 'chaines-de-cheville', 4, 'Delicate ankle chains for a sun-kissed bohemian touch.', 'Délicates chaînes de cheville pour une touche bohème ensoleillée.'],
            ['Sets', 'Coffrets', 'coffrets', 5, 'Thoughtfully paired pieces, ready to give or to keep.', 'Des pièces assorties avec soin, à offrir ou à s\'offrir.'],
        ];

        $categories = [];
        foreach ($roots as [$name, $nameFr, $slugFr, $position, $description, $descriptionFr]) {
            $category = new ProductCategory();
            $category->setName($name);
            $category->setNameFr($nameFr);
            $category->setSlugFr($slugFr);
            $category->setPosition($position);
            $category->setDescription($description);
            $category->setDescriptionFr($descriptionFr);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        // Subcategories: [name, nameFr, slugFr, position, parentKey, description, descriptionFr]
        $children = [
            ['Pendants', 'Pendentifs', 'pendentifs', 0, 'Necklaces', 'Statement pendants with natural stones and gold details.', 'Pendentifs affirmés aux pierres naturelles et détails dorés.'],
            ['Chokers', 'Ras-de-cou', 'ras-de-cou', 1, 'Necklaces', 'Close-fitting necklaces for a refined, modern look.', 'Colliers ajustés pour un look raffiné et moderne.'],
            ['Long & Chain', 'Sautoirs & Chaînes', 'sautoirs-chaines', 2, 'Necklaces', 'Layering-friendly long necklaces and fine chains.', 'Sautoirs et chaînes fines parfaits pour le layering.'],
            ['Hoops', 'Créoles', 'creoles', 0, 'Earrings', 'Classic and textured hoops in every size.', 'Créoles classiques et texturées dans toutes les tailles.'],
            ['Studs', 'Puces', 'puces', 1, 'Earrings', 'Minimal studs for understated everyday sparkle.', 'Puces minimalistes pour une touche d\'éclat au quotidien.'],
            ['Drops', 'Pendantes', 'pendantes', 2, 'Earrings', 'Dangling earrings that catch the light and the eye.', 'Boucles pendantes qui captent la lumière et les regards.'],
            ['Chain', 'Chaînes', 'chaines', 0, 'Bracelets', 'Fine chain bracelets in stainless steel.', 'Bracelets chaîne en acier inoxydable.'],
            ['Cuffs', 'Manchettes', 'manchettes', 1, 'Bracelets', 'Bold cuff bracelets for a strong wrist statement.', 'Manchettes audacieuses pour affirmer votre poignet.'],
            ['Beaded', 'Perles & Pierres', 'perles-pierres', 2, 'Bracelets', 'Natural stone and bead bracelets with artisan charm.', 'Bracelets en pierres naturelles et perles au charme artisanal.'],
            ['Stone Rings', 'Pierres', 'pierres', 0, 'Rings', 'Rings featuring hand-selected natural gemstones.', 'Bagues ornées de pierres naturelles sélectionnées à la main.'],
            ['Plain & Stackable', 'Simples & Empilables', 'simples-empilables', 1, 'Rings', 'Sleek bands to mix, match and stack your way.', 'Anneaux épurés à mixer et empiler selon vos envies.'],
        ];

        foreach ($children as [$name, $nameFr, $slugFr, $position, $parentKey, $description, $descriptionFr]) {
            $category = new ProductCategory();
            $category->setName($name);
            $category->setNameFr($nameFr);
            $category->setSlugFr($slugFr);
            $category->setPosition($position);
            $category->setParent($categories[$parentKey]);
            $category->setDescription($description);
            $category->setDescriptionFr($descriptionFr);
            $manager->persist($category);
            $categories[$name] = $category;
        }

        return $categories;
    }

    /**
     * @return array<string, Stone>
     */
    private function loadStones(ObjectManager $manager): array
    {
        $stoneContent = $this->getStoneContent();

        $stones = [];
        foreach ($stoneContent as $position => $data) {
            $stone = new Stone();
            $stone->setName($data['name']);
            $stone->setNameFr($data['nameFr']);
            $stone->setShortDescription($data['short']);
            $stone->setShortDescriptionFr($data['shortFr']);
            $stone->setDescription($data['desc']);
            $stone->setDescriptionFr($data['descFr']);
            $stone->setVirtues($data['virtues']);
            $stone->setVirtuesFr($data['virtuesFr']);
            $stone->setFunFact($data['funFact'] ?? null);
            $stone->setFunFactFr($data['funFactFr'] ?? null);
            $stone->setTraditions($data['traditions'] ?? null);
            $stone->setTraditionsFr($data['traditionsFr'] ?? null);
            $stone->setCare($data['care'] ?? null);
            $stone->setCareFr($data['careFr'] ?? null);
            $stone->setColor($data['color']);
            $stone->setChakra($data['chakra']);
            $stone->setLustre($data['lustre']);
            $stone->setOrigin($data['origin']);
            $stone->setPosition($position);
            $manager->persist($stone);
            $stones[$data['name']] = $stone;
        }

        return $stones;
    }

    /**
     * Full stone content sourced from docs/STONES.md.
     *
     * @return list<array<string, ?string>>
     */
    private function getStoneContent(): array
    {
        return [
            [
                'name' => 'Black Onyx', 'nameFr' => 'Onyx noir',
                'short' => 'The builder\'s stone. An absolute black that grounds and protects.',
                'shortFr' => 'La pierre des bâtisseuses. Un noir absolu qui ancre et protège.',
                'color' => 'Noir profond', 'chakra' => 'Racine', 'lustre' => 'Vitreux à cireux', 'origin' => 'Brésil, Inde, Uruguay, Madagascar',
                'desc' => 'Black onyx is a deep black chalcedony that has been used in jewelry for over two millennia. The Romans carved cameos and intaglios for their signet rings. In Greek mythology, its name comes from the word onux (nail): Cupid supposedly cut the nails of a sleeping Venus, and the gods turned them to stone so that no part of a divine body would perish.'
                    ."\n\n".'Onyx experienced a spectacular revival in the 1920s-30s with Art Deco: Cartier, Van Cleef & Arpels and Boucheron paired it with diamonds and rock crystal to create pieces of striking geometry. Today, it remains a timeless classic. Its absolute black surface captures light only to hold it.',
                'descFr' => 'L\'onyx noir est une calcédoine d\'un noir profond, utilisée depuis plus de deux millénaires dans l\'art du bijou. Les Romains en gravaient des camées et des intailles pour leurs bagues de sceau. Dans la mythologie grecque, son nom vient du mot onux (ongle) : Cupidon aurait taillé les ongles de Vénus endormie, et les dieux les auraient changés en pierre pour qu\'aucune partie d\'un corps divin ne périsse.'
                    ."\n\n".'L\'onyx a connu un renouveau spectaculaire dans les années 1920–30 avec l\'Art Déco : Cartier, Van Cleef & Arpels et Boucheron l\'associaient aux diamants et au cristal de roche pour créer des pièces d\'une géométrie saisissante. Aujourd\'hui, il reste un classique intemporel. Sa surface d\'un noir absolu capture la lumière pour mieux la retenir.',
                'virtues' => 'Black onyx is a shield. It absorbs what weighs you down — doubts, intrusive thoughts, energies that don\'t belong to you — and grounds you in your own strength. The ultimate grounding stone, it brings you back to what matters when everything swirls around you. Wear it to find your inner stability, to stand firm, to move forward without looking back. It is the stone of those who build.',
                'virtuesFr' => 'L\'onyx noir est un bouclier. Il absorbe ce qui pèse (les doutes, les pensées parasites, les énergies qui ne vous appartiennent pas) et vous ancre dans votre propre force. Pierre d\'enracinement par excellence, il ramène à l\'essentiel quand tout s\'agite autour de vous. On le porte pour retrouver sa stabilité intérieure, pour tenir bon, pour avancer sans se retourner. C\'est la pierre de celles qui bâtissent.',
                'funFact' => 'The Romans had already mastered the technique of dyeing agate to obtain a uniform black onyx over 2,000 years ago, by soaking in honey then carbonizing with acid. This same technique, barely modernized, is still used today.',
                'funFactFr' => 'Les Romains maîtrisaient déjà la technique de teinture de l\'agate pour obtenir un onyx noir uniforme il y a plus de 2 000 ans, par trempage au miel puis carbonisation à l\'acide. Cette même technique, à peine modernisée, est encore utilisée aujourd\'hui.',
                'traditions' => 'Roman soldiers wore carved onyx amulets of Mars or Hercules before battle. Across cultures, black onyx is traditionally associated with inner strength, concentration, and determination.',
                'traditionsFr' => 'Les soldats romains portaient des amulettes d\'onyx gravées de Mars ou d\'Hercule avant le combat. À travers les cultures, l\'onyx noir est traditionnellement associé à la force intérieure, à la concentration et à la détermination.',
                'care' => 'Clean with warm soapy water and a soft brush. Avoid prolonged sun exposure (risk of fading) and ultrasonic cleaners. Store separately from harder stones.',
                'careFr' => 'Nettoyer à l\'eau tiède savonneuse avec une brosse douce. Éviter l\'exposition prolongée au soleil (risque de décoloration) et les nettoyeurs à ultrasons. Ranger séparément des pierres plus dures.',
            ],
            [
                'name' => 'Lapis Lazuli', 'nameFr' => 'Lapis Lazuli',
                'short' => 'The pharaohs\' blue. A stone that gave its name to the sky.',
                'shortFr' => 'Le bleu des pharaons. Une pierre qui a donné son nom au ciel.',
                'color' => 'Bleu outremer', 'chakra' => 'Troisième œil, Gorge', 'lustre' => 'Vitreux à mat', 'origin' => 'Afghanistan, Chili, Russie',
                'desc' => 'Lapis lazuli is not a single mineral but a metamorphic rock, composed mainly of lazurite (giving it its intense blue), calcite (white veins) and pyrite (golden flecks). The finest specimens display a deep blue studded with gold flakes, like a miniature starry sky.'
                    ."\n\n".'The Sar-e-Sang mines in Badakhshan (Afghanistan) have been continuously exploited for over 6,000 years. The Egyptians adorned sarcophagi and Tutankhamun\'s death mask with it. During the Renaissance, ground lapis became the pigment ultramarine, more expensive than gold by weight, which Vermeer and Michelangelo reserved for the robes of the Virgin Mary.',
                'descFr' => 'Le lapis lazuli n\'est pas un minéral unique mais une roche métamorphique, composée principalement de lazurite (qui lui donne son bleu intense), de calcite (veines blanches) et de pyrite (éclats dorés). Les plus beaux spécimens arborent un bleu profond parsemé de paillettes d\'or, comme un ciel étoilé miniature.'
                    ."\n\n".'Les mines de Sar-e-Sang au Badakhshan (Afghanistan) sont exploitées sans interruption depuis plus de 6 000 ans. Les Égyptiens en ornaient les sarcophages et le masque mortuaire de Toutânkhamon. À la Renaissance, le lapis broyé devenait le pigment outremer, plus cher que l\'or au poids, que Vermeer et Michel-Ange réservaient aux robes de la Vierge Marie.',
                'virtues' => 'Lapis lazuli is the stone of inner truth. It awakens intuition, clarifies thought and invites you to look beyond appearances. For those seeking to express what they carry within — suppressed emotions, deep ideas, silent convictions — it opens the path to authentic speech. A stone of serenity, it calms the restless mind and guides toward lucid peace.',
                'virtuesFr' => 'Le lapis lazuli est la pierre de la vérité intérieure. Il éveille l\'intuition, clarifie la pensée et invite à regarder au-delà des apparences. Pour celles qui cherchent à exprimer ce qu\'elles portent en elles (émotions refoulées, idées profondes, convictions silencieuses), il ouvre la voie d\'une parole authentique. Pierre de sérénité, il apaise le mental agité et guide vers une paix lucide.',
                'funFact' => 'The word "azure" (and by extension the French Riviera\'s name, Côte d\'Azur) comes from the Arabic lāzaward, itself from the Persian lājevard, which designated the Afghan region where lapis was mined. This stone literally gave its name to the color of the sky.',
                'funFactFr' => 'Le mot « azur » (et par extension le nom de la Côte d\'Azur) vient de l\'arabe lāzaward, lui-même du persan lājevard, qui désignait la région afghane où l\'on extrayait le lapis. Cette pierre a littéralement donné son nom à la couleur du ciel.',
                'traditions' => 'Stone of wisdom and truth in Mesopotamian civilizations. The Sumerians decorated temples with it, and the Epic of Gilgamesh mentions walls inlaid with lapis lazuli.',
                'traditionsFr' => 'Pierre de sagesse et de vérité dans les civilisations mésopotamiennes. Les Sumériens en décoraient les temples, et l\'Épopée de Gilgamesh mentionne des murs incrustés de lapis lazuli.',
                'care' => 'Clean only with a slightly damp soft cloth. Never soak. Avoid all acids (even lemon juice), ultrasonic cleaners, perfume and hairspray. Store separately.',
                'careFr' => 'Nettoyer uniquement avec un chiffon doux légèrement humide. Ne jamais tremper. Éviter tout acide (même le jus de citron), les nettoyeurs à ultrasons, le parfum et la laque. Ranger séparément.',
            ],
            [
                'name' => 'Turquoise', 'nameFr' => 'Turquoise',
                'short' => 'The traveler\'s stone. From Sinai to Arizona, 5,000 years of history.',
                'shortFr' => 'La pierre voyageuse. Du Sinaï à l\'Arizona, 5 000 ans d\'histoire.',
                'color' => 'Bleu ciel', 'chakra' => 'Gorge, Cœur', 'lustre' => 'Cireux à mat', 'origin' => 'Iran, États-Unis, Chine, Mexique',
                'desc' => 'Turquoise is one of the first stones ever mined by humans. The Sinai mines were exploited by the Egyptians as early as 3000 BC, where it was consecrated to Hathor, goddess of joy and love. Its very name tells a journey: "pierre turquoise" in medieval French meant "Turkish stone," not because it came from Turkey, but because it arrived in Europe through Turkish merchants.'
                    ."\n\n".'Sacred to the Navajo, Zuni and Pueblo peoples of North America for over 2,000 years, turquoise was equally revered by the Aztecs, who valued it above gold. The famous mask of Quetzalcóatl (British Museum) is covered with turquoise tesserae.',
                'descFr' => 'La turquoise est l\'une des premières pierres jamais extraites par l\'homme. Les mines du Sinaï étaient exploitées par les Égyptiens dès 3000 av. J.-C., où elle était consacrée à Hathor, déesse de la joie et de l\'amour. Son nom même raconte un voyage : « pierre turquoise » en français médiéval désignait la « pierre turque », non parce qu\'elle venait de Turquie, mais parce qu\'elle arrivait en Europe par les marchands turcs.'
                    ."\n\n".'Sacrée pour les peuples Navajo, Zuni et Pueblo d\'Amérique du Nord depuis plus de 2 000 ans, la turquoise l\'était tout autant pour les Aztèques, qui la valorisaient au-dessus de l\'or. Le célèbre masque de Quetzalcóatl (British Museum) est recouvert de tesselles de turquoise.',
                'virtues' => 'Turquoise watches over those who move, who travel, who cross thresholds. For millennia, it has been worn as a talisman of protection, a shield against heavy energies and harmful influences. It soothes anger, invites kindness and frees speech: with it, you finally say what you feel, with clarity and gentleness. A stone of empathy, it brings hearts closer.',
                'virtuesFr' => 'La turquoise veille sur celles qui bougent, qui voyagent, qui traversent. Depuis des millénaires, elle est portée comme un talisman de protection, un rempart contre les énergies lourdes et les influences néfastes. Elle apaise la colère, invite à la bienveillance et libère la parole : avec elle, on dit enfin ce que l\'on ressent, avec clarté et douceur. Pierre d\'empathie, elle rapproche les cœurs.',
                'funFact' => 'Turquoise is one of the rare stones whose color can change over time. It can shift from blue to green through dehydration, skin oils or cosmetics. In the Middle Ages, people believed it changed color to signal danger or illness.',
                'funFactFr' => 'La turquoise est l\'une des rares pierres dont la couleur peut évoluer avec le temps. Elle peut virer du bleu vers le vert sous l\'effet de la déshydratation, des huiles de peau ou des cosmétiques. Au Moyen Âge, on croyait qu\'elle changeait de couleur pour signaler un danger ou une maladie.',
                'traditions' => 'Stone of protection for travelers in the Persian tradition. Tibetans consider it their national stone, a symbol of sky and water. Indigenous peoples of the Americas hold it sacred to this day.',
                'traditionsFr' => 'Pierre de protection des voyageurs dans la tradition perse. Les Tibétains la considèrent comme leur pierre nationale, symbole de ciel et d\'eau. Les peuples autochtones d\'Amérique la tiennent pour sacrée encore aujourd\'hui.',
                'care' => 'Very porous stone. Avoid all contact with perfumes, creams, soaps and household products. Clean only with a dry or very slightly damp cloth. Remove before washing hands. Store away from direct light.',
                'careFr' => 'Pierre très poreuse. Éviter tout contact avec les parfums, crèmes, savons et produits ménagers. Nettoyer uniquement avec un chiffon sec ou très légèrement humide. Retirer avant de se laver les mains. Ranger à l\'abri de la lumière directe.',
            ],
            [
                'name' => 'Malachite', 'nameFr' => 'Malachite',
                'short' => 'The stone of metamorphosis. A deep green that tells Earth\'s story.',
                'shortFr' => 'La pierre des métamorphoses. Un vert profond qui raconte l\'histoire de la Terre.',
                'color' => 'Vert bandé', 'chakra' => 'Cœur, Plexus solaire', 'lustre' => 'Soyeux à vitreux', 'origin' => 'Congo, Zambie, Russie, Australie',
                'desc' => 'Malachite fascinates with its concentric bands of light and dark green, drawn by millennia of mineral growth layer after layer. This copper carbonate forms in the alteration zones of copper deposits, and each pattern is unique, like a geological fingerprint.'
                    ."\n\n".'The Egyptians ground it into powder to make green eyeshadow as early as 4000 BC. Cleopatra herself used it. In Imperial Russia, the tsars made it an architectural material: the Malachite Room of the Winter Palace (now the Hermitage Museum) in Saint Petersburg remains one of the most sumptuous halls in the world.',
                'descFr' => 'La malachite fascine par ses bandes concentriques de vert clair et vert sombre, dessinées par des millénaires de croissance minérale couche après couche. Ce carbonate de cuivre se forme dans les zones d\'altération des gisements de cuivre, et chaque motif est unique, comme une empreinte géologique.'
                    ."\n\n".'Les Égyptiens la broyaient en poudre pour en faire un fard à paupières vert dès 4000 av. J.-C. Cléopâtre elle-même l\'utilisait. En Russie impériale, les tsars en firent un matériau d\'architecture : la Salle de Malachite du Palais d\'Hiver (aujourd\'hui musée de l\'Ermitage) à Saint-Pétersbourg reste l\'une des salles les plus somptueuses au monde.',
                'virtues' => 'Malachite is the stone of inner metamorphosis. It accompanies those going through change: a breakup, a new beginning, a silent transformation. By opening the heart, it releases old wounds, sometimes buried since childhood, and transforms pain into momentum. It is said to absorb negative energies from the environment like a sponge. To wear malachite is to accept transformation.',
                'virtuesFr' => 'La malachite est la pierre des métamorphoses intérieures. Elle accompagne celles qui traversent un changement : une rupture, un nouveau départ, une mue silencieuse. En ouvrant le cœur, elle libère les blessures anciennes, parfois enfouies depuis l\'enfance, et transforme la douleur en élan. On dit qu\'elle absorbe les énergies négatives de l\'environnement comme une éponge. Porter la malachite, c\'est accepter de se transformer.',
                'funFact' => 'A single block of malachite weighing 260 tons was discovered in the Mednorudyansk mine in the Urals in 1835. Russian artisans developed the "Russian mosaic" technique: thin slices assembled to create a continuous banded surface on entire columns, tables and fireplaces.',
                'funFactFr' => 'Un bloc unique de malachite pesant 260 tonnes fut découvert dans la mine de Mednorudyansk dans l\'Oural en 1835. Les artisans russes développèrent la technique de la « mosaïque russe » : des tranches fines assemblées pour créer une surface bandée continue sur des colonnes, tables et cheminées entières.',
                'traditions' => 'Associated with transformation and rebirth in ancient cultures. In Egypt, the green color was sacred, linked to the fertility of the Nile and eternal life.',
                'traditionsFr' => 'Associée à la transformation et à la renaissance dans les cultures antiques. En Égypte, la couleur verte était sacrée, liée à la fertilité du Nil et à la vie éternelle.',
                'care' => 'Soft and fragile stone. Clean only with a soft dry cloth. Never soak. Absolutely avoid all acids. No ultrasonic cleaner. Store separately in a soft pouch.',
                'careFr' => 'Pierre tendre et fragile. Nettoyer uniquement avec un chiffon doux sec ou à peine humide. Ne jamais tremper. Éviter absolument tout acide. Pas de nettoyeur à ultrasons. Ranger séparément dans une pochette souple.',
            ],
            [
                'name' => 'Carnelian', 'nameFr' => 'Cornaline',
                'short' => 'The stone of seals. A fiery orange that spans empires.',
                'shortFr' => 'La pierre des sceaux. Un orange ardent qui traverse les empires.',
                'color' => 'Orange-rouge', 'chakra' => 'Racine, Sacré', 'lustre' => 'Vitreux à cireux', 'origin' => 'Inde, Brésil, Uruguay, Madagascar',
                'desc' => 'Carnelian is a chalcedony tinted with iron oxide, ranging from soft orange to deep brown-red. The Cambay region (Gujarat, India) has been its cutting center for over 4,000 years, and carnelian beads dating from 4500 BC have been found in Mehrgarh (Pakistan), among the oldest known human ornaments.'
                    ."\n\n".'The Romans made signet rings from it for a surprisingly practical reason: hot wax does not stick to polished carnelian. This property made it the stone of choice for official seals for centuries, from the Roman Senate to Renaissance merchants. Napoleon himself wore an octagonal carnelian seal found during the Egyptian campaign in 1798.',
                'descFr' => 'La cornaline est une calcédoine teintée d\'oxyde de fer, dont les nuances vont de l\'orange doux au brun-rouge profond. La région de Cambay (Gujarat, Inde) en est le centre de taille depuis plus de 4 000 ans, et des perles de cornaline datant de 4500 av. J.-C. ont été retrouvées à Mehrgarh (Pakistan), parmi les plus anciennes parures humaines connues.'
                    ."\n\n".'Les Romains en faisaient des bagues de sceau pour une raison étonnamment pratique : la cire chaude n\'adhère pas à la cornaline polie. Cette propriété en fit la pierre de choix pour les sceaux officiels pendant des siècles. Napoléon lui-même portait un sceau octogonal en cornaline, trouvé lors de la campagne d\'Égypte en 1798, qu\'il conserva toute sa vie.',
                'virtues' => 'Carnelian is a gentle fire. It awakens the vital impulse, that raw energy that drives you to create, to dare, to take the leap. It is the stone for those who need a spark: against procrastination, against doubt, against the fear of not being enough. It breathes courage and confidence, revives joy and stimulates creativity. With carnelian, you move from hesitation to action.',
                'virtuesFr' => 'La cornaline est un feu doux. Elle réveille l\'élan vital, cette énergie brute qui pousse à créer, à oser, à se lancer. C\'est la pierre de celles qui ont besoin d\'un coup de flamme : contre la procrastination, contre les doutes, contre la peur de ne pas être à la hauteur. Elle insuffle courage et confiance, ravive la joie et stimule la créativité. Avec la cornaline, on passe de l\'hésitation à l\'action.',
                'funFact' => 'In ancient Egypt, carnelian was called desher ("red") and associated with blood and vital energy. It is found in abundance in Tutankhamun\'s treasure, alongside lapis lazuli and turquoise.',
                'funFactFr' => 'En Égypte ancienne, la cornaline était appelée desher (« rouge ») et associée au sang et à l\'énergie vitale. On la retrouve en abondance dans le trésor de Toutânkhamon, aux côtés du lapis lazuli et de la turquoise.',
                'traditions' => 'Stone of courage and vitality in the Roman tradition. In the Islamic tradition, carnelian (aqiq) is held in high esteem. Collections of hadiths report that the Prophet Mohammed wore a carnelian ring.',
                'traditionsFr' => 'Pierre de courage et de vitalité dans la tradition romaine. Dans la tradition islamique, la cornaline (aqiq) est tenue en haute estime. Les collections de hadiths rapportent que le Prophète Mohammed portait une bague de cornaline.',
                'care' => 'Resistant stone (quartz family). Clean with warm soapy water and a soft brush. Avoid prolonged heat. Store away from harder stones.',
                'careFr' => 'Pierre résistante (famille du quartz). Nettoyer à l\'eau tiède savonneuse avec une brosse douce. Éviter la chaleur prolongée. Ranger à l\'écart des pierres plus dures.',
            ],
            [
                'name' => 'Garnet', 'nameFr' => 'Grenat',
                'short' => 'The pomegranate stone. A deep red traveling since the Bronze Age.',
                'shortFr' => 'La pierre grenade. Un rouge profond qui voyage depuis l\'âge du Bronze.',
                'color' => 'Rouge profond', 'chakra' => 'Racine, Cœur', 'lustre' => 'Vitreux', 'origin' => 'Inde, Mozambique, Madagascar, Sri Lanka',
                'desc' => 'Garnet is not a single stone but a family of silicates sharing the same crystal structure. The classic garnet in jewelry, almandine or pyrope, displays a deep red to purple hue. Its name comes from the Latin granatum (pomegranate), because the crystals in the host rock resemble the fruit\'s seeds.'
                    ."\n\n".'Garnet beads dating from 3100 BC have been found in Egyptian jewelry. But it was the Anglo-Saxon and Merovingian goldsmiths (5th–7th centuries) who brought it to the summit of their art: garnets set in cloisonné gold created pieces of striking beauty.',
                'descFr' => 'Le grenat n\'est pas une pierre unique mais une famille de silicates partageant une même structure cristalline. Le grenat classique en joaillerie, almandin ou pyrope, arbore un rouge profond à pourpré. Son nom vient du latin granatum (grenade), car les cristaux dans la roche mère ressemblent aux grains du fruit.'
                    ."\n\n".'Des perles de grenat datant de 3100 av. J.-C. ont été retrouvées dans des bijoux égyptiens. Mais ce sont les orfèvres anglo-saxons et mérovingiens (Vᵉ–VIIᵉ siècles) qui l\'ont porté au sommet de leur art : les grenats sertis en cloisonné dans l\'or créaient des pièces d\'une beauté saisissante.',
                'virtues' => 'Garnet carries the warmth of the earth. A stone of inner strength and resilience, it supports in moments when you doubt yourself. It revives passion — of the heart, of creation, of life itself — and deeply anchors in the present. Wear it to find your stability, to nourish your devotion, and to move forward with the quiet certainty that you deserve abundance.',
                'virtuesFr' => 'Le grenat porte en lui la chaleur de la terre. Pierre de force intérieure et de résilience, il soutient dans les moments où l\'on doute de soi. Il ravive la passion (celle du cœur, celle de la création, celle de la vie elle-même) et ancre profondément dans le présent. On le porte pour retrouver sa stabilité, pour nourrir sa dévotion et pour avancer avec la certitude tranquille que l\'on mérite l\'abondance.',
                'funFact' => 'The Staffordshire Hoard, discovered in 2009, contained over 3,500 garnets set in gold — the largest collection of Anglo-Saxon goldwork ever found. Chemical analysis traced the garnets back to Sri Lanka and India, proving that 7th-century artisans were connected to trade networks spanning thousands of kilometers.',
                'funFactFr' => 'Le Trésor de Staffordshire, découvert en 2009, contenait plus de 3 500 grenats sertis dans l\'or, la plus grande collection d\'orfèvrerie anglo-saxonne jamais trouvée. L\'analyse chimique a retracé l\'origine des grenats jusqu\'au Sri Lanka et à l\'Inde, prouvant que les artisans du VIIᵉ siècle étaient connectés à des réseaux commerciaux s\'étendant sur des milliers de kilomètres.',
                'traditions' => 'Travelers\' stone in the Middle Ages. Bohemian pyrope garnets were immensely popular in Victorian jewelry of the 19th century.',
                'traditionsFr' => 'Pierre des voyageurs au Moyen Âge. Les marchands et pèlerins en portaient pour s\'assurer un retour sain et sauf. En Bohême, les grenats pyropes ont été immensément populaires dans la joaillerie victorienne du XIXᵉ siècle.',
                'care' => 'Resistant stone for daily use. Clean with warm soapy water. Ultrasonic cleaner generally safe. Avoid sharp impacts.',
                'careFr' => 'Pierre résistante pour un usage quotidien. Nettoyer à l\'eau tiède savonneuse. Nettoyeur à ultrasons généralement sûr. Éviter les chocs brutaux.',
            ],
            [
                'name' => 'Labradorite', 'nameFr' => 'Labradorite',
                'short' => 'The aurora borealis stone. A play of light trapped in rock.',
                'shortFr' => 'La pierre des aurores boréales. Un jeu de lumière emprisonné dans la roche.',
                'color' => 'Gris iridescent', 'chakra' => 'Troisième œil, Gorge', 'lustre' => 'Vitreux à nacré', 'origin' => 'Canada, Madagascar, Finlande',
                'desc' => 'Labradorite is a feldspar whose beauty only reveals itself in motion: its dark grey surface suddenly ignites with iridescent flashes (electric blue, emerald green, coppery gold, sometimes violet), an optical phenomenon called labradorescence. This effect is created by the diffraction of light on nanometric layers of alternating feldspar compositions.',
                'descFr' => 'La labradorite est un feldspath dont la beauté ne se révèle qu\'en mouvement : sa surface gris sombre s\'enflamme soudain d\'éclats iridescents (bleu électrique, vert émeraude, or cuivré, parfois violet), un phénomène optique nommé labradorescence. Cet effet est créé par la diffraction de la lumière sur des couches nanométriques de feldspaths de compositions alternées.',
                'virtues' => 'Labradorite is the guardian of the aura. It is the ultimate protection stone: it forms an energetic shield that prevents other people\'s emotions from overwhelming you. Ideal for the highly sensitive, it absorbs parasitic energies and preserves your inner space. But it is also a stone of transformation: it dispels fears, awakens intuition and accompanies life transitions with luminous strength.',
                'virtuesFr' => 'La labradorite est la gardienne de l\'aura. C\'est la pierre de protection par excellence : elle forme un bouclier énergétique qui empêche les émotions des autres de vous envahir. Idéale pour les hypersensibles, elle absorbe les énergies parasites et préserve votre espace intérieur. Mais elle est aussi une pierre de transformation : elle dissipe les peurs, éveille l\'intuition et accompagne les passages de vie avec une force lumineuse.',
                'funFact' => 'The Inuit legend tells that the northern lights were once trapped in the rocks of the Labrador coast. A warrior struck the stones with his spear, freeing most of the lights toward the sky, but some remained captive in the stone, creating labradorescence.',
                'funFactFr' => 'La légende inuite raconte que les aurores boréales étaient autrefois emprisonnées dans les roches de la côte du Labrador. Un guerrier frappa les pierres de sa lance, libérant la plupart des lumières vers le ciel, mais certaines restèrent captives dans la pierre, créant la labradorescence.',
                'traditions' => null, 'traditionsFr' => null,
                'care' => 'Clean with warm soapy water. Absolutely avoid ultrasonic cleaners. Avoid direct impacts. Store separately in a soft pouch.',
                'careFr' => 'Nettoyer à l\'eau tiède savonneuse. Éviter absolument les nettoyeurs à ultrasons. Éviter les chocs directs. Ranger séparément dans une pochette souple.',
            ],
            [
                'name' => 'Amazonite', 'nameFr' => 'Amazonite',
                'short' => 'The Amazons\' stone. A blue-green that evokes lagoons.',
                'shortFr' => 'La pierre des Amazones. Un bleu-vert d\'eau qui évoque les lagons.',
                'color' => 'Bleu-vert', 'chakra' => 'Gorge, Cœur', 'lustre' => 'Vitreux à cireux', 'origin' => 'Russie, Brésil, Madagascar, Éthiopie',
                'desc' => 'Amazonite is a microcline feldspar with a striking blue-green color caused by traces of lead in its crystal structure. Its name evokes the Amazon River, though no deposit has ever been confirmed there. Amazonite is featured among the inlays of Tutankhamun\'s death mask, alongside lapis lazuli and carnelian.',
                'descFr' => 'L\'amazonite est un feldspath microcline d\'un bleu-vert saisissant, dont la couleur provient de traces de plomb dans la structure cristalline. Son nom évoque le fleuve Amazone, bien qu\'aucun gisement n\'y ait jamais été confirmé. L\'amazonite figure parmi les incrustations du masque mortuaire de Toutânkhamon, aux côtés du lapis lazuli et de la cornaline.',
                'virtues' => 'Amazonite is a breath of fresh air for the soul. It dispels fears, soothes worries and restores hope where doubt had settled. A stone of courageous communication, it helps speak one\'s truth — not with brutality, but with the gentleness of water. It harmonizes heart and speech, transforms anger into positive action and opens an inner space of luminous serenity.',
                'virtuesFr' => 'L\'amazonite est une bouffée d\'air frais pour l\'âme. Elle dissipe les peurs, apaise les inquiétudes et replace l\'espoir là où le doute s\'était installé. Pierre de communication courageuse, elle aide à dire sa vérité, non pas avec brutalité, mais avec la douceur de l\'eau. Elle harmonise le cœur et la parole, transforme la colère en action positive et ouvre un espace intérieur de sérénité lumineuse.',
                'funFact' => 'Chapter 7 of the Egyptian Book of the Dead was engraved on amazonite tablets, suggesting the Egyptians accorded it considerable importance in the journey to the afterlife.',
                'funFactFr' => 'Le chapitre 7 du Livre des Morts égyptien était gravé sur des tablettes d\'amazonite, suggérant que les Égyptiens lui accordaient une importance considérable dans le voyage vers l\'au-delà.',
                'traditions' => null, 'traditionsFr' => null,
                'care' => 'Clean with warm soapy water. Avoid ultrasonic and steam cleaners. Avoid prolonged sun exposure.',
                'careFr' => 'Nettoyer à l\'eau tiède savonneuse. Éviter les nettoyeurs à ultrasons et à vapeur. Éviter l\'exposition prolongée au soleil.',
            ],
            [
                'name' => 'Rhodonite', 'nameFr' => 'Rhodonite',
                'short' => 'Russia\'s rosy stone. A deep pink veined with black.',
                'shortFr' => 'La pierre rosée de Russie. Un rose profond veiné de noir.',
                'color' => 'Rose veiné noir', 'chakra' => 'Cœur', 'lustre' => 'Vitreux à nacré', 'origin' => 'Russie, Australie, Brésil, Suède',
                'desc' => 'Rhodonite takes its name from the Greek rhodon (rose) and is immediately recognizable by its deep pink crossed with black veins of manganese oxide. Its history is intimately linked to Russia: the Hermitage Museum houses a rhodonite sarcophagus of Empress Maria Alexandrovna weighing about 7 tons.',
                'descFr' => 'La rhodonite tire son nom du grec rhodon (rose) et se reconnaît immédiatement à son rose profond traversé de veines noires d\'oxyde de manganèse. Son histoire est intimement liée à la Russie : le musée de l\'Ermitage abrite un sarcophage en rhodonite de l\'impératrice Maria Alexandrovna pesant environ 7 tonnes.',
                'virtues' => 'Rhodonite is the stone of forgiveness — toward others, but especially toward oneself. It soothes emotional wounds, releases resentments that weigh down and opens a path toward inner reconciliation. For those who carry too much, who give too much, who forget to care for themselves: rhodonite reminds you that compassion begins with yourself.',
                'virtuesFr' => 'La rhodonite est la pierre du pardon, envers les autres, mais surtout envers soi-même. Elle apaise les blessures affectives, libère les rancœurs qui pèsent et ouvre un chemin vers la réconciliation intérieure. Pour celles qui portent trop, qui donnent trop, qui oublient de prendre soin d\'elles : la rhodonite rappelle que la compassion commence par soi.',
                'funFact' => 'Russian folklore calls rhodonite orletz, "eagle stone," because eagles supposedly carried small pieces of rhodonite in their nests. Rhodonite has been the official state gem of Massachusetts (USA) since 1979.',
                'funFactFr' => 'Le folklore russe appelle la rhodonite orletz, « pierre de l\'aigle », car les aigles auraient transporté de petits morceaux de rhodonite dans leurs nids. La rhodonite est la pierre officielle de l\'État du Massachusetts (USA) depuis 1979.',
                'traditions' => null, 'traditionsFr' => null,
                'care' => 'Clean with a soft cloth and warm soapy water. Avoid ultrasonic cleaners and prolonged soaking. Store separately.',
                'careFr' => 'Nettoyer avec un chiffon doux et de l\'eau tiède savonneuse. Éviter les nettoyeurs à ultrasons et le trempage prolongé. Ranger séparément.',
            ],
            [
                'name' => 'Moonstone', 'nameFr' => 'Pierre de Lune',
                'short' => 'Moonlight in stone. A bluish veil dancing beneath the surface.',
                'shortFr' => 'Le clair de lune en pierre. Un voile bleuté qui danse sous la surface.',
                'color' => 'Translucide bleuté', 'chakra' => 'Sacré, Troisième œil, Couronne', 'lustre' => 'Vitreux à nacré', 'origin' => 'Sri Lanka, Inde, Myanmar, Madagascar',
                'desc' => 'Moonstone is a feldspar whose magic lies in a unique optical phenomenon: adularescence. A bluish to silvery luminous veil floats beneath the stone\'s surface, like trapped moonlight. The Romans believed it was formed from solidified moonbeams. The Art Nouveau movement (1890–1910) gave it its letters of nobility: René Lalique and Louis Comfort Tiffany used it abundantly.',
                'descFr' => 'La pierre de lune est un feldspath dont la magie tient à un phénomène optique unique : l\'adularescence. Un voile lumineux bleuté à argenté flotte sous la surface de la pierre, comme un clair de lune piégé dans la matière. Les Romains croyaient qu\'elle était formée de rayons de lune solidifiés. L\'Art Nouveau (1890–1910) lui donna ses lettres de noblesse : René Lalique et Louis Comfort Tiffany l\'utilisèrent abondamment.',
                'virtues' => 'Moonstone is a whisper of light. It awakens intuition, that inner voice we hear without listening, and reconnects to the wisdom of the sacred feminine. Linked to the cycles of the moon, it accompanies the natural rhythms of body and soul, soothes tumultuous emotions and opens the door to dreams and inspiration. It is the stone of new beginnings.',
                'virtuesFr' => 'La pierre de lune est un murmure de lumière. Elle éveille l\'intuition, cette voix intérieure que l\'on entend sans l\'écouter, et reconnecte à la sagesse du féminin sacré. Liée aux cycles de la lune, elle accompagne les rythmes naturels du corps et de l\'âme, apaise les émotions tumultueuses et ouvre la porte aux rêves et à l\'inspiration. C\'est la pierre des nouveaux départs.',
                'funFact' => 'Florida designated the moonstone as its official state gem in 1970, to commemorate the Apollo missions launching from Cape Canaveral, even though no moonstone has ever been found in Florida. The finest ones come from Sri Lanka.',
                'funFactFr' => 'La Floride a désigné la pierre de lune comme gemme officielle de l\'État en 1970, pour commémorer les missions Apollo décollant de Cap Canaveral, alors même qu\'aucune pierre de lune n\'a jamais été trouvée en Floride. Les plus belles viennent du Sri Lanka.',
                'traditions' => 'Stone of femininity and intuition in the Hindu tradition. In medieval Europe, people believed it could reveal the future if placed in the mouth during a full moon.',
                'traditionsFr' => 'Pierre de féminité et d\'intuition dans la tradition hindoue. En Europe médiévale, on croyait qu\'elle pouvait révéler l\'avenir si on la plaçait en bouche lors d\'une pleine lune.',
                'care' => 'Relatively fragile stone. Clean with a very soft cloth and warm water. Absolutely avoid ultrasonic and steam cleaners. Avoid perfume and hairspray. Store wrapped in soft fabric.',
                'careFr' => 'Pierre relativement fragile. Nettoyer avec un chiffon très doux et de l\'eau tiède. Éviter absolument les nettoyeurs à ultrasons et à vapeur. Éviter le parfum et la laque. Ranger enveloppée dans un tissu doux.',
            ],
            [
                'name' => 'Peridot', 'nameFr' => 'Péridot',
                'short' => 'The sun\'s gem. A luminous green from Earth\'s depths... and space.',
                'shortFr' => 'La gemme du soleil. Un vert lumineux venu des profondeurs de la Terre... et de l\'espace.',
                'color' => 'Vert lumineux', 'chakra' => 'Cœur, Plexus solaire', 'lustre' => 'Vitreux à huileux', 'origin' => 'Égypte, Myanmar, Pakistan, États-Unis',
                'desc' => 'Peridot is one of the few idiochromatic gems: its green color comes not from impurities but from an essential element of its composition (ferrous iron). It exists only in green. Cleopatra\'s famous "emeralds" were very likely peridots: the two stones were regularly confused in antiquity.',
                'descFr' => 'Le péridot est l\'une des rares pierres idiochromatiques : sa couleur verte ne vient pas d\'impuretés mais d\'un élément essentiel de sa composition (le fer ferreux). Il n\'existe qu\'en vert. Les fameuses « émeraudes » de Cléopâtre étaient très probablement des péridots : les deux pierres étaient régulièrement confondues dans l\'Antiquité.',
                'virtues' => 'Peridot is a burst of sunlight trapped in stone. It chases away melancholy, dispels guilt and brings back a simple, luminous joy — the pleasure of being alive. A stone of renewal, it helps shed negative patterns and toxic emotions. It encourages moving forward with lightness and letting your own light shine.',
                'virtuesFr' => 'Le péridot est un éclat de soleil emprisonné dans la pierre. Il chasse la mélancolie, dissipe la culpabilité et ramène une joie simple, lumineuse. Pierre de renouveau, il aide à se défaire des schémas négatifs et des émotions toxiques. Il encourage à avancer avec légèreté et à laisser briller sa propre lumière.',
                'funFact' => 'Peridot is one of the very few gems of extraterrestrial origin. It is found in pallasite meteorites, and in 2005, NASA\'s Stardust probe discovered it in dust from Comet Wild 2.',
                'funFactFr' => 'Le péridot est l\'une des très rares gemmes d\'origine extraterrestre. On le trouve dans les météorites pallasites, et en 2005, la sonde Stardust de la NASA en a découvert dans la poussière de la comète Wild 2.',
                'traditions' => null, 'traditionsFr' => null,
                'care' => 'Clean with warm soapy water. Avoid ultrasonic and steam cleaners. Avoid acids. Suitable for daily wear with care.',
                'careFr' => 'Nettoyer à l\'eau tiède savonneuse. Éviter les nettoyeurs à ultrasons et à vapeur. Éviter les acides. Convient à un usage quotidien avec précaution.',
            ],
            [
                'name' => 'Rose Quartz', 'nameFr' => 'Quartz Rose',
                'short' => 'The tender stone. A powdery pink spanning civilizations.',
                'shortFr' => 'La pierre tendre. Un rose poudré qui traverse les civilisations.',
                'color' => 'Rose poudré', 'chakra' => 'Cœur', 'lustre' => 'Vitreux', 'origin' => 'Brésil, Madagascar, Inde, Afrique du Sud',
                'desc' => 'Rose quartz owes its delicate color to microscopic fibrous inclusions, identified only in 2001. Rose quartz beads dating from about 7000 BC have been found in Mesopotamia — one of the oldest ornamental stones in the world. In Greek mythology, Aphrodite cut herself on a thorn bush and her blood tinted white quartz pink.',
                'descFr' => 'Le quartz rose doit sa couleur délicate à des inclusions microscopiques fibreuses, identifiées seulement en 2001. Des perles de quartz rose datant d\'environ 7000 av. J.-C. ont été retrouvées en Mésopotamie. C\'est l\'une des plus anciennes pierres d\'ornement au monde. Dans la mythologie grecque, Aphrodite se blessa sur un buisson de ronces et son sang teinta le quartz blanc en rose.',
                'virtues' => 'Rose quartz is the stone of love, in all its forms. Love of others, self-love, love of life. It gently opens the heart, heals deep emotional wounds and envelops in unconditional tenderness. For those who struggle to show themselves kindness, it whispers that you are enough, just as you are.',
                'virtuesFr' => 'Le quartz rose est la pierre de l\'amour, sous toutes ses formes. Amour de l\'autre, amour de soi, amour de la vie. Il ouvre le cœur en douceur, guérit les blessures affectives profondes et enveloppe d\'une tendresse inconditionnelle. Pour celles qui ont du mal à s\'accorder de la bienveillance, il murmure que l\'on est assez, telle que l\'on est.',
                'funFact' => 'Transparent, well-formed rose quartz crystals were considered impossible until 1959 when the first true crystals were discovered in Brazil. Gem-quality transparent rose quartz can sell for more than amethyst or citrine.',
                'funFactFr' => 'Les cristaux transparents et bien formés de quartz rose étaient considérés comme impossibles jusqu\'en 1959, quand les premiers vrais cristaux furent découverts au Brésil. Le quartz rose cristallisé de qualité gemme peut se vendre plus cher que l\'améthyste ou la citrine.',
                'traditions' => 'Stone of love and gentleness in Greek, Roman, and Egyptian traditions. Associated with Aphrodite and Venus.',
                'traditionsFr' => 'Pierre d\'amour et de douceur dans les traditions grecque, romaine et égyptienne. Associée à Aphrodite et Vénus.',
                'care' => 'Resistant stone. Clean with warm soapy water. Avoid prolonged sun exposure as rose quartz can fade significantly under UV.',
                'careFr' => 'Pierre résistante. Nettoyer à l\'eau tiède savonneuse. Éviter l\'exposition prolongée au soleil, car le quartz rose peut pâlir significativement sous les UV.',
            ],
            [
                'name' => 'Smoky Quartz', 'nameFr' => 'Quartz Fumé',
                'short' => 'The Highlands stone. A deep brown forged by natural radioactivity.',
                'shortFr' => 'La pierre des Highlands. Un brun profond forgé par la radioactivité naturelle.',
                'color' => 'Brun translucide', 'chakra' => 'Racine, Plexus solaire', 'lustre' => 'Vitreux', 'origin' => 'Brésil, Écosse, Suisse, Madagascar',
                'desc' => 'Smoky quartz owes its brown color to natural irradiation: gamma rays from surrounding radioactive minerals create "color centers" that absorb light. Scotland\'s national stone, it is called cairngorm there and has adorned Highland jewelry and kilt pins for centuries.',
                'descFr' => 'Le quartz fumé doit sa couleur brune à l\'irradiation naturelle : les rayons gamma des minéraux radioactifs environnants créent des « centres colorés » qui absorbent la lumière. Pierre nationale de l\'Écosse, il y est appelé cairngorm et orne les bijoux traditionnels des Highlands depuis des siècles.',
                'virtues' => 'Smoky quartz is a silent anchor. When stress rises, when anxiety clouds thinking, when the ground seems to give way, it brings feet back to earth. It is one of the most powerful stones for dispelling negative energies and dark thoughts. It helps let go and traverse difficult periods with quiet resilience.',
                'virtuesFr' => 'Le quartz fumé est un ancrage silencieux. Quand le stress monte, quand l\'anxiété brouille la pensée, quand le sol semble se dérober, il ramène les pieds sur terre. C\'est l\'une des pierres les plus puissantes pour dissiper les énergies négatives et les pensées sombres. Il aide à lâcher prise et à traverser les périodes difficiles avec une résilience tranquille.',
                'funFact' => 'Chinese artisans of the 12th century carved smoky quartz into flat lenses to create the first sunglasses in history. Imperial court judges wore them to conceal their facial expressions during interrogations.',
                'funFactFr' => 'Les artisans chinois du XIIᵉ siècle taillaient le quartz fumé en lentilles plates pour fabriquer les premières lunettes de soleil de l\'histoire. Les juges des tribunaux impériaux les portaient pour dissimuler leurs expressions faciales pendant les interrogatoires.',
                'traditions' => 'Stone of grounding and stability in the Celtic tradition. Popular in Victorian mourning jewelry (1860–1880).',
                'traditionsFr' => 'Pierre d\'ancrage et de stabilité dans la tradition celtique. Populaire dans la bijouterie de deuil victorienne (1860–1880).',
                'care' => 'Robust and easy to care for. Clean with warm soapy water. Ultrasonic cleaner generally safe. Excellent durability for daily wear.',
                'careFr' => 'Pierre robuste et facile d\'entretien. Nettoyer à l\'eau tiède savonneuse. Nettoyeur à ultrasons généralement sûr. Excellente durabilité pour un usage quotidien.',
            ],
            [
                'name' => 'Green Aventurine', 'nameFr' => 'Aventurine verte',
                'short' => 'The stone of happy chance. A shimmering green born of accident.',
                'shortFr' => 'La pierre du hasard heureux. Un vert scintillant né d\'un accident.',
                'color' => 'Vert scintillant', 'chakra' => 'Cœur', 'lustre' => 'Vitreux avec éclat métallique', 'origin' => 'Inde, Brésil, Russie, Tanzanie',
                'desc' => 'Green aventurine is a quartz made green by inclusions of fuchsite, a chrome-rich mica. These microscopic mica flakes produce a characteristic metallic shimmer called aventurescence. Its name tells of a historical accident: a ventura ("by chance" in Italian) refers to Murano glass created accidentally when copper shavings fell into molten glass.',
                'descFr' => 'L\'aventurine verte est un quartz rendu vert par des inclusions de fuchsite, un mica riche en chrome. Ces paillettes microscopiques de mica produisent un scintillement métallique caractéristique appelé aventurescence. Son nom raconte un accident historique : a ventura (« par hasard » en italien) fait référence à un verre de Murano créé accidentellement quand des copeaux de cuivre tombèrent dans un bain de verre en fusion.',
                'virtues' => 'Green aventurine is a lucky charm. Wear it to attract luck, opportunities and happy coincidences. But beyond superstition, it soothes the heart, calms irritability and instils luminous optimism. It helps see the glass half full and move forward with the quiet confidence of those who know something good awaits them.',
                'virtuesFr' => 'L\'aventurine verte est un porte-bonheur. On la porte pour attirer la chance, les opportunités et les heureux hasards. Mais au-delà de la superstition, elle apaise le cœur, calme l\'irritabilité et insuffle un optimisme lumineux. Elle aide à voir le verre à moitié plein et à avancer avec la confiance tranquille de celles qui savent que quelque chose de bon les attend.',
                'funFact' => 'The Hermitage Museum in Saint Petersburg houses a monumental aventurine vase 146 cm high and 246 cm wide. The Kolyvan manufactory in Siberia took 14 years (1842–1856) to carve and polish it from a single block.',
                'funFactFr' => 'Le musée de l\'Ermitage à Saint-Pétersbourg abrite un vase monumental en aventurine de 146 cm de haut et 246 cm de large. La manufacture de Kolyvan en Sibérie mit 14 ans (1842–1856) à le façonner et le polir.',
                'traditions' => 'In ancient Tibet, aventurine was used to decorate statues, especially as inlaid eyes. Traditionally associated with luck and prosperity.',
                'traditionsFr' => 'Dans le Tibet ancien, l\'aventurine servait à décorer les statues, notamment comme yeux incrustés. Pierre traditionnellement associée à la chance et à la prospérité.',
                'care' => 'Clean with warm soapy water. Avoid ultrasonic cleaners. Good durability for daily wear.',
                'careFr' => 'Nettoyer à l\'eau tiède savonneuse. Éviter les nettoyeurs à ultrasons. Durabilité correcte pour un usage quotidien.',
            ],
            [
                'name' => 'Sodalite', 'nameFr' => 'Sodalite',
                'short' => 'Canada\'s royal blue. The discreet sister of lapis lazuli.',
                'shortFr' => 'Le bleu royal du Canada. Sœur discrète du lapis lazuli.',
                'color' => 'Bleu royal', 'chakra' => 'Gorge, Troisième œil', 'lustre' => 'Vitreux à gras', 'origin' => 'Canada, Brésil, Namibie, Bolivie',
                'desc' => 'Sodalite displays a deep royal blue veined with white (calcite), often confused with lapis lazuli. The distinction is simple: sodalite never has golden pyrite flecks. It became an important ornamental stone in 1891 with the discovery of major deposits in Ontario (Canada).',
                'descFr' => 'La sodalite arbore un bleu royal profond veiné de blanc (calcite), souvent confondu avec le lapis lazuli. La distinction est simple : la sodalite n\'a jamais de paillettes de pyrite dorée. Elle ne devint une pierre ornementale importante qu\'en 1891, avec la découverte de gisements majeurs en Ontario (Canada).',
                'virtues' => 'Sodalite is the stone of clarity. When the mind races, when thoughts loop, when anxiety clouds judgment, it restores order. It organizes thought, sharpens lucidity and opens the path to authentic self-expression. A stone of truth, it helps align what you think, what you feel and what you say.',
                'virtuesFr' => 'La sodalite est la pierre de la clarté. Quand le mental s\'emballe, quand les pensées tournent en boucle, quand l\'anxiété brouille le jugement, elle remet de l\'ordre. Elle organise la pensée, aiguise la lucidité et ouvre le chemin vers une expression authentique de soi. Pierre de vérité, elle aide à aligner ce que l\'on pense, ce que l\'on ressent et ce que l\'on dit.',
                'funFact' => 'In 1901, the Princess of Wales was so captivated by sodalite at the Buffalo World\'s Fair that she had 130 tons shipped from Ontario to England in 1906 to decorate the interior of Marlborough House.',
                'funFactFr' => 'En 1901, la Princesse de Galles fut si captivée par la sodalite présentée à l\'Exposition universelle de Buffalo qu\'elle fit expédier 130 tonnes de sodalite de Bancroft (Ontario) en Angleterre en 1906 pour décorer l\'intérieur de Marlborough House.',
                'traditions' => null, 'traditionsFr' => null,
                'care' => 'Clean with a soft cloth and warm soapy water. Avoid prolonged sun exposure and salt water. Store separately.',
                'careFr' => 'Nettoyer avec un chiffon doux et de l\'eau tiède savonneuse. Éviter le soleil prolongé et l\'eau salée. Ranger séparément.',
            ],
            [
                'name' => 'Dalmatian Jasper', 'nameFr' => 'Jaspe Dalmatien',
                'short' => 'The spotted stone. A fake jasper with a secret identity.',
                'shortFr' => 'La pierre mouchetée. Un faux jaspe à l\'identité secrète.',
                'color' => 'Crème moucheté noir', 'chakra' => 'Racine, Sacré', 'lustre' => 'Mat à vitreux', 'origin' => 'Mexique, Madagascar, Brésil',
                'desc' => 'Despite its universally used commercial name, Dalmatian jasper is not a jasper at all. It\'s an igneous rock — a peralkaline microgranite. This discovery only dates from 2017, when a GIA study revealed that neither the white base (feldspar, not chalcedony) nor the black spots (arfvedsonite, not tourmaline) matched what everyone assumed.',
                'descFr' => 'Malgré son nom commercial universellement utilisé, le jaspe dalmatien n\'est pas un jaspe. C\'est une roche ignée, un microgranite peralcalin. Cette découverte ne date que de 2017, quand une étude du GIA révéla que ni le fond blanc (feldspath, pas calcédoine) ni les taches noires (arfvédsonite, pas tourmaline) ne correspondaient à ce que tout le monde supposait.',
                'virtues' => 'Dalmatian jasper is an invitation to lightness. It reconnects to simple joy, childlike wonder, the pleasure of not taking anything too seriously. A stone of joyful grounding, it protects from discouragement and melancholy while keeping feet on the ground. It strengthens bonds of friendship and loyalty.',
                'virtuesFr' => 'Le jaspe dalmatien est une invitation à la légèreté. Il reconnecte à la joie simple, à l\'émerveillement enfantin, au plaisir de ne rien prendre trop au sérieux. Pierre d\'ancrage joyeux, il protège du découragement et de la mélancolie tout en gardant les pieds sur terre. Il renforce les liens d\'amitié et de loyauté.',
                'funFact' => 'Its name comes solely from the visual resemblance to the Dalmatian dog\'s coat. It is one of the rare cases in the gem world where a commercial name has survived scientific proof that the stone doesn\'t belong to the indicated family at all.',
                'funFactFr' => 'Son nom vient uniquement de la ressemblance visuelle avec la robe du chien dalmatien. C\'est l\'un des rares cas dans le monde des gemmes où un nom commercial a survécu à la preuve scientifique que la pierre n\'appartient pas du tout à la famille indiquée.',
                'traditions' => null, 'traditionsFr' => null,
                'care' => 'Resistant stone. Clean with warm soapy water. No special precautions. One of the easiest stones to maintain.',
                'careFr' => 'Pierre résistante. Nettoyer à l\'eau tiède savonneuse. Pas de précaution particulière. C\'est l\'une des pierres les plus faciles d\'entretien.',
            ],
            [
                'name' => 'Pink Opal', 'nameFr' => 'Opale Rose',
                'short' => 'The tender pink of the Andes. A gift from Pachamama.',
                'shortFr' => 'Le rose tendre des Andes. Un cadeau de la Pachamama.',
                'color' => 'Rose poudré', 'chakra' => 'Cœur', 'lustre' => 'Vitreux à cireux', 'origin' => 'Pérou',
                'desc' => 'Peruvian pink opal is a common opal: it does not have the rainbow play-of-color of Australian opals. Its beauty lies in its milky, opaque pink. Peru\'s national stone, it forms exclusively in the hydrothermal veins of copper-bearing volcanic rocks in the Andes. The Incas considered it a gift from Pachamama (Mother Earth).',
                'descFr' => 'L\'opale rose du Pérou est une opale commune : elle n\'a pas le jeu de couleurs arc-en-ciel des opales australiennes. Sa beauté réside dans son rose laiteux et opaque. Pierre nationale du Pérou, elle se forme exclusivement dans les veines hydrothermales des roches volcaniques cuivrifères des Andes. Les Incas la considéraient comme un don de la Pachamama (la Terre-Mère).',
                'virtues' => 'Pink opal is a balm for the heart. It soothes the deepest wounds — old sorrows, silent griefs, lost loves — with infinite gentleness. A stone of tenderness and emotional renewal, it invites forgiveness, turning the page without brutality, and welcoming what comes with an open heart.',
                'virtuesFr' => 'L\'opale rose est un baume pour le cœur. Elle apaise les blessures les plus profondes (les chagrins anciens, les deuils silencieux, les amours perdues) avec une douceur infinie. Pierre de tendresse et de renouveau émotionnel, elle invite à se pardonner, à tourner la page sans brutalité et à accueillir ce qui vient avec un cœur ouvert.',
                'funFact' => 'Unlike precious opals that play with light, Peruvian pink opal seduces through its monochrome softness. It is an opal of silence and calm, not of fire and brilliance.',
                'funFactFr' => 'Contrairement aux opales précieuses qui jouent avec la lumière, l\'opale rose péruvienne séduit par sa douceur monochrome. C\'est une opale de silence et de calme, pas de feu et d\'éclat.',
                'traditions' => 'The Incas believed that pink opal could only form where Pachamama had shed tears of joy.',
                'traditionsFr' => 'Les Incas croyaient que l\'opale rose ne pouvait se former qu\'aux endroits où la Pachamama avait versé des larmes de joie.',
                'care' => 'Delicate stone containing water. Dehydration causes crazing. Clean briefly with warm soapy water. Never soak for long. Avoid sun and heat.',
                'careFr' => 'Pierre délicate contenant de l\'eau. La déshydratation provoque des craquelures. Nettoyer brièvement à l\'eau tiède savonneuse. Ne jamais tremper longtemps. Éviter le soleil et la chaleur.',
            ],
            [
                'name' => 'Green Quartz', 'nameFr' => 'Quartz Vert',
                'short' => 'The delicate green of quartz. A natural rarity.',
                'shortFr' => 'Le vert délicat du quartz. Une rareté naturelle, un trésor de laboratoire.',
                'color' => 'Vert pâle', 'chakra' => 'Cœur', 'lustre' => 'Vitreux', 'origin' => 'Brésil, Pologne, Canada, Namibie',
                'desc' => 'Prasiolite (from Greek prason, leek) is the green variety of quartz. It is one of the rarest stones in the quartz family in its natural state. Most commercial prasiolite is obtained by heat-treating amethyst at 400–500°C. Fascinatingly, this treatment only works with amethyst from one very specific deposit: the Montezuma mine in Brazil.',
                'descFr' => 'La prasiolite (du grec prason, poireau) est la variété verte du quartz. C\'est l\'une des pierres les plus rares de la famille du quartz à l\'état naturel. La quasi-totalité de la prasiolite commerciale est obtenue par traitement thermique de l\'améthyste à 400–500°C. Fait fascinant : ce traitement ne fonctionne qu\'avec l\'améthyste d\'un gisement très spécifique : la mine de Montezuma au Brésil.',
                'virtues' => 'Green quartz is a breath. It opens the heart to sincere expression of emotions and reconnects to nature, to the slow rhythm of seasons, to the patience of growing things. A stone of gentle transformation, it transmutes negative into positive and invites inner prosperity: not material abundance, but the richness of feeling at peace with oneself.',
                'virtuesFr' => 'Le quartz vert est une respiration. Il ouvre le cœur à l\'expression sincère des émotions et reconnecte à la nature, au rythme lent des saisons, à la patience de ce qui pousse. Pierre de transformation douce, il transmute le négatif en positif et invite à une prospérité intérieure : non pas l\'abondance matérielle, mais la richesse de se sentir en paix avec soi-même.',
                'funFact' => null, 'funFactFr' => null, 'traditions' => null, 'traditionsFr' => null,
                'care' => 'Resistant stone. Clean with warm soapy water. Avoid prolonged sun exposure (treated stones may fade).',
                'careFr' => 'Pierre résistante. Nettoyer à l\'eau tiède savonneuse. Éviter l\'exposition prolongée au soleil intense (les pierres traitées peuvent se décolorer).',
            ],
            [
                'name' => 'Mother of Pearl', 'nameFr' => 'Nacre',
                'short' => 'The ocean\'s rainbow. A masterpiece of natural engineering.',
                'shortFr' => 'L\'arc-en-ciel de l\'océan. Un chef-d\'œuvre d\'ingénierie naturelle.',
                'color' => 'Iridescent blanc', 'chakra' => 'Plexus solaire, Cœur', 'lustre' => 'Nacré', 'origin' => 'Australie, Polynésie, Japon, Chine',
                'desc' => 'Mother of pearl is an organic material secreted by mollusks, composed of about 95% aragonite arranged in superimposed microscopic tablets. This "bricks and mortar" architecture at the nanometric scale produces its characteristic iridescence. Used in decoration since at least 2500 BC, it adorns the Standard of Ur (circa 2600 BC, British Museum).',
                'descFr' => 'La nacre est un matériau organique sécrété par les mollusques, composé d\'environ 95 % d\'aragonite disposée en tablettes microscopiques superposées. C\'est cette architecture en « briques et mortier » à l\'échelle nanométrique qui produit son iridescence caractéristique. Utilisée en décoration depuis au moins 2500 av. J.-C., elle orne l\'Étendard d\'Ur (vers 2600 av. J.-C., British Museum).',
                'virtues' => 'Mother of pearl envelops like a mother. It carries the gentle, protective nature of the ocean — a cocoon of iridescent light that soothes restless temperaments and calms tumultuous emotions. A stone of innocence and purity, it fosters sincerity in relationships and stimulates creative imagination.',
                'virtuesFr' => 'La nacre enveloppe comme une mère. Elle porte en elle la douceur protectrice de l\'océan, un cocon de lumière irisée qui apaise les tempéraments agités et calme les émotions tumultueuses. Pierre d\'innocence et de pureté, elle favorise la sincérité dans les relations et stimule l\'imagination créative.',
                'funFact' => 'Engineers study mother of pearl as a model for future materials. Though made from the same fragile calcium carbonate as ordinary chalk, its nanoarchitecture makes it 3,000 times stronger.',
                'funFactFr' => 'Les ingénieurs étudient la nacre comme modèle pour les matériaux du futur. Bien qu\'elle soit faite du même carbonate de calcium fragile que la craie ordinaire, sa nanoarchitecture la rend 3 000 fois plus résistante.',
                'traditions' => 'Symbol of purity and protection across maritime cultures. A prestige material in decorative arts across East and West for millennia.',
                'traditionsFr' => 'Symbole de pureté et de protection à travers les cultures maritimes. Matériau de prestige dans les arts décoratifs d\'Orient et d\'Occident depuis des millénaires.',
                'care' => 'Wipe with a slightly damp soft cloth after each wear. Never soak. Absolutely avoid all chemicals. Store separately in a soft pouch.',
                'careFr' => 'Essuyer avec un chiffon doux légèrement humide après chaque port. Ne jamais tremper. Éviter absolument tous les produits chimiques. Ranger séparément dans une pochette souple.',
            ],
            [
                'name' => 'Pearl', 'nameFr' => 'Perle',
                'short' => 'The living jewel. The only gem created by a living being.',
                'shortFr' => 'Le joyau vivant. La seule gemme créée par un être vivant.',
                'color' => 'Blanc lustré', 'chakra' => 'Troisième œil, Couronne', 'lustre' => 'Nacré, lustré', 'origin' => 'Chine, Japon, Australie, Polynésie',
                'desc' => 'The pearl is the only gem in the world produced by a living organism. Formed of thousands of concentric layers of nacre deposited by a mollusk around a nucleus, it is one of the oldest known jewels. The oldest pearl ever found (Umm al Quwain, UAE) dates from about 5500 BC. In ancient Rome, Julius Caesar passed a law reserving the wearing of pearls to the ruling classes.',
                'descFr' => 'La perle est la seule gemme au monde produite par un organisme vivant. Formée de milliers de couches concentriques de nacre déposées par un mollusque autour d\'un noyau, elle est l\'un des plus anciens joyaux connus. La plus vieille perle jamais trouvée (Umm al Quwain, Émirats) date d\'environ 5500 av. J.-C. Dans la Rome antique, Jules César fit voter une loi réservant le port des perles aux classes dirigeantes.',
                'virtues' => 'The pearl is a silent wisdom. Born of the patience of a living being, it carries the memory of water and time. It soothes excessive emotions, tempers excess and invites deep recentering: finding balance between inner and outer worlds. A stone of sacred femininity, it honors cycles, intuition and nobility of soul.',
                'virtuesFr' => 'La perle est une sagesse silencieuse. Née de la patience d\'un être vivant, elle porte en elle la mémoire de l\'eau et du temps. Elle apaise les émotions excessives, tempère les excès et invite à un recentrage profond : retrouver l\'équilibre entre monde intérieur et monde extérieur. Pierre de féminité sacrée, elle honore les cycles, l\'intuition et la noblesse d\'âme.',
                'funFact' => '"La Peregrina," a 55-carat pearl discovered off Panama in the 16th century, passed from Spanish royalty to the British crown, to Empress Eugénie, then to Elizabeth Taylor. In 2011, the necklace containing La Peregrina sold at Christie\'s for $11.8 million.',
                'funFactFr' => '« La Peregrina », une perle de 55 carats découverte au large du Panama au XVIᵉ siècle, est passée de la royauté espagnole à la couronne britannique, à l\'impératrice Eugénie, puis à Elizabeth Taylor. En 2011, le collier contenant La Peregrina fut vendu chez Christie\'s pour 11,8 millions de dollars.',
                'traditions' => 'In India, Krishna supposedly plucked the first pearl from the sea as a wedding gift. Universally associated with purity and wisdom.',
                'traditionsFr' => 'En Inde, Krishna aurait cueilli la première perle de la mer comme cadeau de mariage. Universellement associée à la pureté et à la sagesse.',
                'care' => 'Golden rule: "last on, first off." Put pearls on after perfume, hairspray and makeup; remove them first. Wipe with a soft cloth after each wear. Never use ultrasonic cleaners or bleach.',
                'careFr' => 'Règle d\'or : « dernière mise, première retirée ». Mettre les perles après le parfum, la laque et le maquillage ; les retirer en premier. Essuyer avec un chiffon doux après chaque port. Ne jamais utiliser de nettoyeur à ultrasons ou d\'eau de Javel.',
            ],
            [
                'name' => 'Abalone', 'nameFr' => 'Abalone',
                'short' => 'The ocean\'s iris. The most vivid colors of the marine world.',
                'shortFr' => 'L\'iris de l\'océan. Les couleurs les plus vives du monde marin.',
                'color' => 'Iridescent multicolore', 'chakra' => 'Cœur, Gorge, Troisième œil', 'lustre' => 'Nacré intense', 'origin' => 'Nouvelle-Zélande, Californie, Afrique du Sud',
                'desc' => 'Abalone (or haliotis) is a marine gastropod whose inner shell offers the most colorful nacre of all mollusks. Electric blues, deep greens, violets and pinks mingle in spectacular iridescence. The most prized variety is the New Zealand paua (Haliotis iris). For the Maori, paua is a taonga (treasure): its iridescent nacre is inlaid in the eyes of ancestral sculptures.',
                'descFr' => 'L\'abalone (ou haliotide) est un gastéropode marin dont la coquille intérieure offre la nacre la plus colorée de tous les mollusques. Des bleus électriques, des verts profonds, des violets et des roses se mêlent en une iridescence spectaculaire. La variété la plus prisée est le paua de Nouvelle-Zélande (Haliotis iris). Pour les Maoris, le paua est un taonga (trésor) : sa nacre iridescente est incrustée dans les yeux des sculptures ancestrales.',
                'virtues' => 'Abalone carries all the colors of the ocean, and all its lessons. It helps navigate emotional waves with grace, to let go when the current is too strong, and to find inner harmony. It stimulates imagination, nourishes creativity and reconciles contradictions: with it, you accept the beauty of your own complexity.',
                'virtuesFr' => 'L\'abalone porte toutes les couleurs de l\'océan, et toutes ses leçons. Elle aide à naviguer les vagues émotionnelles avec grâce, à lâcher prise quand le courant est trop fort et à retrouver son harmonie intérieure. Elle stimule l\'imagination, nourrit la créativité et réconcilie les contradictions : avec elle, on accepte la beauté de sa propre complexité.',
                'funFact' => 'In Maori tradition, paua embodies the collaboration between two gods: Tangaroa (god of the sea) offered the blues and greens of the ocean, while his brother Tane (god of the forest) added the greens of trees and the violets of flowers.',
                'funFactFr' => 'Dans la tradition maorie, le paua incarne la collaboration entre deux dieux : Tangaroa (dieu de la mer) offrit les bleus et verts de l\'océan, tandis que son frère Tane (dieu de la forêt) ajouta les verts des arbres et les violets des fleurs.',
                'traditions' => 'Native American tribes of the Pacific coast (Chumash, Tongva) used abalone as currency and in ceremonies for thousands of years.',
                'traditionsFr' => 'Les tribus amérindiennes de la côte Pacifique (Chumash, Tongva) utilisaient l\'abalone comme monnaie d\'échange et dans les cérémonies depuis des milliers d\'années.',
                'care' => 'Wipe with a soft dry or very slightly damp cloth. Never soak in bleach or acidic cleaners. Avoid perfume, hairspray, cream. Remove before bathing. Store separately in a soft pouch.',
                'careFr' => 'Essuyer avec un chiffon doux sec ou très légèrement humide. Ne jamais tremper dans de l\'eau de Javel ou des nettoyants acides. Éviter parfum, laque, crème. Retirer avant le bain ou la douche. Ranger séparément dans une pochette souple.',
            ],
            [
                'name' => 'White Agate', 'nameFr' => 'Agate blanche',
                'short' => 'The stone of serenity. A pure white that soothes since the dawn of civilizations.',
                'shortFr' => 'La pierre de sérénité. Un blanc pur qui apaise depuis l\'aube des civilisations.',
                'color' => 'Blanc laiteux', 'chakra' => 'Couronne', 'lustre' => 'Vitreux à cireux', 'origin' => 'Brésil, Inde, Madagascar, Uruguay, Allemagne',
                'desc' => 'White agate is a chalcedony — microcrystalline quartz — with a milky white to translucent appearance, sometimes crossed by fine, nearly imperceptible pearly bands. It is the most sober and soothing of the agates, a family of stones named by the Greek philosopher Theophrastus, Aristotle\'s disciple, who described it around 350 BC after finding it on the shores of the River Achates (now the Dirillo) in southern Sicily. Agate is thus one of the first stones to have received a scientific name in the history of mineralogy.',
                'descFr' => 'L\'agate blanche est une calcédoine — quartz microcristallin — d\'un blanc laiteux à translucide, parfois traversée de fines bandes nacrées presque imperceptibles. C\'est la plus sobre et la plus apaisante des agates, une famille de pierres qui doit son nom au philosophe grec Théophraste, disciple d\'Aristote, qui la décrivit vers 350 av. J.-C. après l\'avoir trouvée sur les rivages du fleuve Achates (aujourd\'hui le Dirillo), au sud de la Sicile. L\'agate est ainsi l\'une des premières pierres à avoir reçu un nom scientifique dans l\'histoire de la minéralogie.',
                'virtues' => 'White agate is a silence. Where other stones stimulate, awaken or ignite, it soothes. It is the stone for those who need calm in the noise, clarity in confusion, a pause in the race. It stabilizes emotions, harmonizes body and mind, and envelops in a gentle, weightless serenity. A stone of purity, it invites you to return to the essential, to shed the superfluous, to find a luminous inner peace.',
                'virtuesFr' => 'L\'agate blanche est un silence. Là où d\'autres pierres stimulent, éveillent ou enflamment, elle apaise. C\'est la pierre de celles qui ont besoin de calme dans le bruit, de clarté dans la confusion, d\'une pause dans la course. Elle stabilise les émotions, harmonise le corps et l\'esprit et enveloppe d\'une sérénité douce, sans pesanteur. Pierre de pureté, elle invite à revenir à l\'essentiel, à se défaire du superflu, à retrouver une paix intérieure lumineuse.',
                'funFact' => 'It is agate that made Idar-Oberstein, a small German town in Rhineland-Palatinate, the world capital of stone cutting. From the 15th century, craftsmen used giant grinding wheels powered by the waters of the Nahe River. The cutters worked lying face down on wooden planks, pressing stones against the rotating wheels with their body weight — a spectacular technique practiced for four centuries.',
                'funFactFr' => 'C\'est l\'agate qui a fait d\'Idar-Oberstein, petite ville allemande de Rhénanie-Palatinat, la capitale mondiale de la taille de pierres. Dès le XVᵉ siècle, les artisans utilisaient des meules géantes actionnées par les eaux de la rivière Nahe. Les tailleurs travaillaient allongés face contre terre sur des planches de bois, pressant les pierres contre les roues en rotation avec le poids de leur corps — une technique spectaculaire pratiquée pendant quatre siècles.',
                'traditions' => 'A universal protection stone, worn as an amulet by Egyptians, Greeks and Romans. The Romans ground it into powder mixed with water, believing it could neutralize snake venom. In Celtic tradition, white agate was linked to Ceridwen, goddess of wisdom and transformation.',
                'traditionsFr' => 'Pierre de protection universelle, portée en amulette par les Égyptiens, les Grecs et les Romains. Les Romains la réduisaient en poudre qu\'ils mêlaient à l\'eau, croyant pouvoir neutraliser le venin de serpent. Dans la tradition celtique, l\'agate blanche était liée à Ceridwen, déesse de la sagesse et de la transformation.',
                'care' => 'Resilient stone (quartz family). Clean with warm soapy water and a soft brush. Avoid prolonged direct sunlight (risk of yellowing). Ultrasonic cleaner generally safe unless the stone has visible cracks. Good durability for daily wear.',
                'careFr' => 'Pierre résistante (famille du quartz). Nettoyer à l\'eau tiède savonneuse avec une brosse douce. Éviter l\'exposition prolongée au soleil direct (risque de jaunissement). Nettoyeur à ultrasons généralement sûr, sauf si la pierre présente des fissures visibles. Bonne durabilité pour un usage quotidien.',
            ],
        ];
    }

    /**
     * @param array<string, ProductCategory> $categories
     * @param array<string, Stone>           $stones
     *
     * @return array<string, Product>
     */
    private function loadProducts(ObjectManager $manager, array $categories, array $stones): array
    {
        $data = [
            [
                'Gold Star Pendant Necklace',
                'Pendentif Étoile Doré',
                'Delicate gold star pendant on a fine chain, inspired by the night sky over the Mexican desert. Made from water-resistant stainless steel.',
                'Délicat pendentif étoile en acier doré, inspiré du ciel nocturne au-dessus du désert mexicain. Acier inoxydable résistant à l\'eau.',
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

        // Associate stones to hand-crafted products
        $stoneMap = [
            'Turquoise Stone Ring' => ['Turquoise'],
            'Black Onyx Drop Earrings' => ['Black Onyx'],
            'Mother of Pearl Choker' => ['Mother of Pearl'],
            'Lapis Lazuli Stud Earrings' => ['Lapis Lazuli'],
            'Moonstone Solitaire Ring' => ['Moonstone'],
            'Beaded Stone Bracelet' => ['Rose Quartz'],
            'Pearl & Gold Hoops' => ['Pearl'],
        ];

        foreach ($stoneMap as $productName => $stoneNames) {
            if (!isset($products[$productName])) {
                continue;
            }
            foreach ($stoneNames as $stoneName) {
                if (isset($stones[$stoneName])) {
                    $products[$productName]->addStone($stones[$stoneName]);
                }
            }
        }

        return $products;
    }

    /**
     * Generate 288 additional products (300 total with the 12 hand-crafted ones).
     *
     * @param array<string, ProductCategory> $categories
     * @param array<string, Stone>           $stoneEntities
     */
    private function generateBulkProducts(ObjectManager $manager, array $categories, array $stoneEntities): void
    {
        $stones = [
            ['Agate', 'Agate'], ['Alexandrite', 'Alexandrite'], ['Amazonite', 'Amazonite'],
            ['Amber', 'Ambre'], ['Amethyst', 'Améthyste'], ['Aquamarine', 'Aigue-marine'],
            ['Aventurine', 'Aventurine'], ['Carnelian', 'Cornaline'], ['Chalcedony', 'Calcédoine'],
            ['Chrysoprase', 'Chrysoprase'], ['Citrine', 'Citrine'], ['Coral', 'Corail'],
            ['Emerald', 'Émeraude'], ['Fluorite', 'Fluorite'], ['Garnet', 'Grenat'],
            ['Hematite', 'Hématite'], ['Howlite', 'Howlite'], ['Iolite', 'Iolite'],
            ['Jade', 'Jade'], ['Jasper', 'Jaspe'], ['Kyanite', 'Cyanite'],
            ['Labradorite', 'Labradorite'], ['Larimar', 'Larimar'], ['Malachite', 'Malachite'],
            ['Obsidian', 'Obsidienne'], ['Opal', 'Opale'], ['Pearl', 'Perle'],
            ['Peridot', 'Péridot'], ['Prehnite', 'Préhnite'], ['Pyrite', 'Pyrite'],
            ['Rhodonite', 'Rhodonite'], ['Rose Quartz', 'Quartz Rose'], ['Ruby', 'Rubis'],
            ['Sapphire', 'Saphir'], ['Serpentine', 'Serpentine'], ['Smoky Quartz', 'Quartz Fumé'],
            ['Sodalite', 'Sodalite'], ['Sunstone', 'Pierre de Soleil'], ['Tanzanite', 'Tanzanite'],
            ['Tiger Eye', 'Œil de Tigre'], ['Topaz', 'Topaze'], ['Turquoise', 'Turquoise'],
            ['Unakite', 'Unakite'], ['Zoisite', 'Zoïsite'],
        ];

        // [nameTemplateEN, nameTemplateFR, count] — total = 288
        $categoryConfig = [
            'Pendants' => ['%s Pendant Necklace',  'Collier Pendentif %s',   23],
            'Chokers' => ['%s Choker',            'Ras-de-Cou %s',          22],
            'Long & Chain' => ['%s Chain Necklace',    'Sautoir %s',             22],
            'Hoops' => ['%s Hoop Earrings',     'Créoles %s',             22],
            'Studs' => ['%s Stud Earrings',     'Puces %s',               22],
            'Drops' => ['%s Drop Earrings',     'Pendantes %s',           22],
            'Chain' => ['%s Chain Bracelet',    'Bracelet Chaîne %s',     22],
            'Cuffs' => ['%s Cuff Bracelet',     'Manchette %s',           22],
            'Beaded' => ['%s Bead Bracelet',     'Bracelet Perles %s',     22],
            'Stone Rings' => ['%s Statement Ring',    'Bague %s',               21],
            'Plain & Stackable' => ['%s Stacking Ring',     'Anneau %s',              23],
            'Anklets' => ['%s Anklet',            'Chaîne de Cheville %s',  22],
            'Sets' => ['%s Jewelry Set',       'Coffret %s',             23],
        ];

        $descriptionsEn = [
            'Handcrafted piece featuring genuine %s. Water-resistant stainless steel. Unique piece curated between Paris and Mexico.',
            'A beautiful %s jewelry piece, hand-selected for its unique character and natural beauty. Durable, water-resistant, perfect for everyday wear.',
            'Elegant design showcasing natural %s. Part of our exclusive collection bridging Parisian sophistication and Mexican bohemian spirit. Water-resistant stainless steel.',
        ];

        $descriptionsFr = [
            'Pièce unique en %s véritable. Acier inoxydable résistant à l\'eau. Sélectionnée avec soin entre Paris et le Mexique.',
            'Bijou en %s naturel, choisi pour son caractère unique et sa beauté. Résistant à l\'eau, parfait pour le quotidien.',
            'Bijou élégant en %s d\'exception. Collection exclusive entre Paris et le Mexique. Acier inoxydable résistant à l\'eau.',
        ];

        $prices = [18.00, 22.00, 24.00, 26.00, 28.00, 29.00, 31.00, 33.00, 35.00, 37.00, 39.00, 42.00, 44.00, 46.00, 48.00, 52.00];

        $stoneCount = \count($stones);
        $priceCount = \count($prices);
        $stoneIndex = 0;
        $globalIndex = 0;

        foreach ($categoryConfig as $catKey => [$tplEn, $tplFr, $count]) {
            for ($i = 0; $i < $count; ++$i) {
                [$stoneEn, $stoneFr] = $stones[$stoneIndex % $stoneCount];

                $price = $prices[$globalIndex % $priceCount];
                $descIndex = $globalIndex % 3;

                $product = new Product();
                $product->setName(\sprintf($tplEn, $stoneEn));
                $product->setNameFr(\sprintf($tplFr, $stoneFr));
                $product->setDescription(\sprintf($descriptionsEn[$descIndex], \strtolower($stoneEn)));
                $product->setDescriptionFr(\sprintf($descriptionsFr[$descIndex], \mb_strtolower($stoneFr)));
                $product->setBasePrice($price);
                $product->setCompareAtPrice($globalIndex % 5 === 0 ? $price + 12.00 : null);
                $product->setShippingTier($catKey === 'Sets' ? ShippingTier::Set : ($price >= 44.00 ? ShippingTier::Heavy : ShippingTier::Standard));
                $product->setCategory($categories[$catKey]);
                $product->setIsPublished(true);
                $product->setIsFeatured($globalIndex % 15 === 0);
                $product->setIsSoldOut($globalIndex % 20 === 0);
                $product->setAvailableIn(match ($globalIndex % 3) {
                    0 => [Product::COUNTRY_FRANCE, Product::COUNTRY_MEXICO],
                    1 => [Product::COUNTRY_FRANCE],
                    default => [Product::COUNTRY_MEXICO],
                });

                // Associate stone entity if it exists
                $stoneEntityMap = [
                    'Agate' => null, 'Alexandrite' => null, 'Amazonite' => 'Amazonite',
                    'Amber' => null, 'Amethyst' => null, 'Aquamarine' => null,
                    'Aventurine' => 'Green Aventurine', 'Carnelian' => 'Carnelian',
                    'Chalcedony' => null, 'Chrysoprase' => null, 'Citrine' => null,
                    'Coral' => null, 'Emerald' => null, 'Fluorite' => null,
                    'Garnet' => 'Garnet', 'Hematite' => null, 'Howlite' => null,
                    'Iolite' => null, 'Jade' => null, 'Jasper' => 'Dalmatian Jasper',
                    'Kyanite' => null, 'Labradorite' => 'Labradorite', 'Larimar' => null,
                    'Malachite' => 'Malachite', 'Obsidian' => null, 'Opal' => 'Pink Opal',
                    'Pearl' => 'Pearl', 'Peridot' => 'Peridot', 'Prehnite' => null,
                    'Pyrite' => null, 'Rhodonite' => 'Rhodonite', 'Rose Quartz' => 'Rose Quartz',
                    'Ruby' => null, 'Sapphire' => null, 'Serpentine' => null,
                    'Smoky Quartz' => 'Smoky Quartz', 'Sodalite' => 'Sodalite',
                    'Sunstone' => null, 'Tanzanite' => null, 'Tiger Eye' => null,
                    'Topaz' => null, 'Turquoise' => 'Turquoise', 'Unakite' => null,
                    'Zoisite' => null,
                ];

                $mappedStone = $stoneEntityMap[$stoneEn] ?? null;
                if ($mappedStone !== null && isset($stoneEntities[$mappedStone])) {
                    $product->addStone($stoneEntities[$mappedStone]);
                }

                $manager->persist($product);

                ++$stoneIndex;
                ++$globalIndex;
            }
        }
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
