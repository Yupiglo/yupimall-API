<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InitialCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Définition Complète des Catégories & Sous-catégories (Mapping i18n keys)
        $catalog = [
            'agriculture' => ['name' => 'Agriculture & Nourriture', 'subs' => ['Céréales', 'Graines Oléagineuses', 'Fruits Frais', 'Machinerie Agricole', 'Emballage Alimentaire']],
            'clothing' => ['name' => 'Vêtement & Accessoires', 'subs' => ['Vêtements Homme', 'Vêtements Femme', 'Accessoires de Mode', 'Chaussures', 'Sous-vêtements']],
            'bags' => ['name' => 'Sacs, Valises & Boîtes', 'subs' => ['Sacs à Dos', 'Sacs à Main', 'Valises', 'Portefeuilles', 'Boîtes Cosmétiques']],
            'chemicals' => ['name' => 'Produits Chimiques', 'subs' => ['Agrochimie', 'Colorants & Pigments', 'Pharmacie', 'Plastiques', 'Réactifs Chimiques']],
            'it' => ['name' => 'Produits Informatiques', 'subs' => ['Ordinateurs Portables', 'PC de Bureau', 'Composants PC', 'Moniteurs', 'Réseaux']],
            'construction' => ['name' => 'Construction & Décoration', 'subs' => ['Carrelage', 'Articles en Acier', 'Matériaux Déco', 'Bois de Construction', 'Raccords']],
            'electronics' => ['name' => 'Électroniques de Consommation', 'subs' => ['Accessoires Mobile', 'Audio & Vidéo', 'Électronique Intelligente', 'Appareils Photo', 'Gaming']],
            'electricity' => ['name' => 'Électricité & Électronique', 'subs' => ['Câbles', 'Outils Électriques', 'Interrupteurs', 'Transformateurs', 'Générateurs']],
            'furniture' => ['name' => 'Meuble', 'subs' => ['Meubles de Salon', 'Meubles de Chambre', 'Mobilier de Bureau', 'Mobilier d\'Extérieur']],
            'health' => ['name' => 'Santé & Hygiène', 'subs' => ['Instruments Médicaux', 'Équipement de Beauté', 'Fournitures d\'Hygiène', 'Massage & Fitness']],
            'instruments' => ['name' => 'Instruments & Compteurs', 'subs' => ['Instruments de Mesure', 'Équipement de Test', 'Instruments Optiques', 'Fournitures de Labo']],
            'lightIndustry' => ['name' => 'Industrie Légère & Articles Courants', 'subs' => ['Ustensiles de Cuisine', 'Produits de Salle de Bain', 'Fournitures de Nettoyage', 'Cadeaux & Artisanat']],
            'lighting' => ['name' => 'Luminaire & Éclairage', 'subs' => ['Éclairage LED', 'Éclairage Intérieur', 'Éclairage Extérieur', 'Ampoules & Supports']],
            'machinery' => ['name' => 'Machinerie de Fabrication', 'subs' => ['Remplissage', 'Façonnage du Métal', 'Travail du Bois', 'Conditionnement']],
            'office' => ['name' => 'Fournitures de Bureau', 'subs' => ['Papeterie', 'Équipement de Bureau', 'Calculatrices', 'Imprimantes & Consommables']],
            'packaging' => ['name' => 'Emballage & Impression', 'subs' => ['Sacs en Papier', 'Boîtes en Plastique', 'Étiquettes', 'Services d\'Impression']],
            'security' => ['name' => 'Sécurité & Protection', 'subs' => ['Vidéosurveillance', 'Protection Incendie', 'Sécurité Personnelle', 'Coffre-forts']],
            'sports' => ['name' => 'Sports & Loisirs', 'subs' => ['Équipement Fitness', 'Sports de Plein Air', 'Camping & Randonnée', 'Sports Nautiques']],
            'textile' => ['name' => 'Textile', 'subs' => ['Tissus', 'Fil', 'Fibres', 'Linge de Maison', 'Tissus d\'Habillement']],
            'tools' => ['name' => 'Outils & Quincaillerie', 'subs' => ['Outils à Main', 'Outils Électriques', 'Fixations', 'Abrasifs', 'Serrures']],
            'toys' => ['name' => 'Jouets', 'subs' => ['Jouets Éducatifs', 'Poupées', 'Jouets Télécommandés', 'Puzzles', 'Porteurs']],
            'transport' => ['name' => 'Transport', 'subs' => ['Vélos', 'Véhicules Électriques', 'Pièces Détachées Auto', 'Équipement Logistique', 'Bateaux']],
        ];

        foreach ($catalog as $id => $data) {
            $category = \App\Models\Category::updateOrCreate(
                ['name' => $data['name']],
                ['slug' => $id]
            );

            foreach ($data['subs'] as $subName) {
                \App\Models\Subcategory::updateOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($subName), 'category_id' => $category->id],
                    ['name' => $subName]
                );
            }
        }

        // 2. Migration des Produits Statiques (Mapping sur les nouvelles catégories + sous-catégories)
        $staticProducts = [
            [
                'title' => 'Alka Plus Multi Minerals Drops',
                'price' => 25,
                'discount' => 10,
                'description' => 'Alkaline Booster, Super Antioxidant, Immunity Booster.',
                'images' => ['/Product/alka-plus.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene',
                'is_new' => true
            ],
            [
                'title' => 'Dental Drop Multi Purpose Drop',
                'price' => 15,
                'discount' => 5,
                'description' => 'For All Dental Problems, Clean and Strong Teeth.',
                'images' => ['/Product/dental-drop-.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene',
                'is_sale' => true
            ],
            [
                'title' => 'Detox Health 30 Capsules',
                'price' => 22,
                'discount' => 0,
                'description' => 'Advanced Formulation, 1000 MG Per Serving.',
                'images' => ['/Product/detox-health30.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene'
            ],
            [
                'title' => 'Diabo Care 60 Capsules',
                'price' => 35,
                'discount' => 15,
                'description' => 'Skin Health, Natural Detoxifier, Promote Digestion.',
                'images' => ['/Product/DIABO-CARE-1.webp'],
                'category' => 'health',
                'subcategory' => 'instruments-medicaux',
                'is_sale' => true
            ],
            [
                'title' => 'Immuno Boost 30 Capsules',
                'price' => 28,
                'discount' => 10,
                'description' => 'Advanced Immune Defense, Balance, Rejuvenate, Strengthen.',
                'images' => ['/Product/immuno-boost30cap.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene',
                'is_new' => true
            ],
            [
                'title' => 'Men Power Malt 400g',
                'price' => 45,
                'discount' => 20,
                'description' => 'Strength, Stamina, Energy.',
                'images' => ['/Product/MEN-POWER-MALT.webp'],
                'category' => 'health',
                'subcategory' => 'massage-fitness'
            ],
            [
                'title' => 'Sea Buckthorn Juice - Himalayan Berry',
                'price' => 38,
                'discount' => 10,
                'description' => 'Premium Quality Juice, Super Anti Oxidant.',
                'images' => ['/Product/seabuckthorn-juice-.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene'
            ],
            [
                'title' => 'Golden Pain Oil 100ml',
                'price' => 18,
                'discount' => 5,
                'description' => 'Anti-Inflammatory, Supports Joint Health, Quick Relief.',
                'images' => ['/Product/golden-pail-oil-1-814x2048.webp'],
                'category' => 'health',
                'subcategory' => 'massage-fitness'
            ],
            [
                'title' => 'Sea Buckthorn Juice Premium',
                'price' => 42,
                'discount' => 10,
                'description' => 'Rich Source of Vitamin C, E & Omega 3, 6, 7, 9.',
                'images' => ['/Product/SEA-BUCKTHORN-1.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene'
            ],
            [
                'title' => 'Detox Health 60 Capsules',
                'price' => 40,
                'discount' => 15,
                'description' => 'Advanced Formulation, 1000 MG Per Serving.',
                'images' => ['/Product/detox-health60.webp'],
                'category' => 'health',
                'subcategory' => 'fournitures-dhygiene'
            ],
            [
                'title' => 'Men Power Oil 30ml',
                'price' => 20,
                'discount' => 0,
                'description' => 'Oil Only For Men.',
                'images' => ['/Product/men-power-oil-1.webp'],
                'category' => 'health',
                'subcategory' => 'massage-fitness'
            ],
            [
                'title' => 'Pain and Cold Balm 12g',
                'price' => 12,
                'discount' => 10,
                'description' => 'For Fast Relief.',
                'images' => ['/Product/seabuckthorn-juice-4.webp'],
                'category' => 'health',
                'subcategory' => 'massage-fitness'
            ],
            [
                'title' => 'Tracteur Agricole Massey Ferguson',
                'price' => 25000,
                'discount' => 5,
                'description' => 'Puissance et fiabilité pour vos champs.',
                'images' => ['/images/placeholder-pro.jpg'],
                'category' => 'agriculture',
                'subcategory' => 'machinerie-agricole',
                'is_new' => true
            ],
            [
                'title' => "Set d'outils de précision BOSCH",
                'price' => 120,
                'discount' => 15,
                'description' => "L'excellence allemande pour vos travaux.",
                'images' => ['/images/placeholder-pro.jpg'],
                'category' => 'tools',
                'subcategory' => 'outils-electriques'
            ],
            [
                'title' => 'Scanner Code Barre Pro Luxe',
                'price' => 150,
                'discount' => 10,
                'description' => "Rapidité d'encaissement inégalée.",
                'images' => ['/images/placeholder-pro.jpg'],
                'category' => 'it',
                'subcategory' => 'composants-pc'
            ],
            [
                'title' => 'Groupe Électrogène 5kVA',
                'price' => 800,
                'discount' => 10,
                'description' => 'Énergie continue en toute circonstance.',
                'images' => ['/images/placeholder-pro.jpg'],
                'category' => 'electricity',
                'subcategory' => 'generateurs'
            ],
            [
                'title' => 'Robot Tondeuse Intelligent',
                'price' => 600,
                'discount' => 20,
                'description' => 'Entretien automatique de vos espaces verts.',
                'images' => ['/images/placeholder-pro.jpg'],
                'category' => 'machinery',
                'subcategory' => 'faconnage-du-metal',
                'is_sale' => true
            ]
        ];

        foreach ($staticProducts as $p) {
            // Find category and subcategory IDs
            $category = \App\Models\Category::where('slug', $p['category'])->first();
            $subcategory = isset($p['subcategory'])
                ? \App\Models\Subcategory::where('slug', $p['subcategory'])->where('category_id', $category?->id)->first()
                : null;

            \App\Models\Product::updateOrCreate(
                ['title' => $p['title']],
                [
                    'price' => $p['price'],
                    'discount' => $p['discount'],
                    'description' => $p['description'],
                    'images' => $p['images'],
                    'category' => $p['category'],
                    'category_id' => $category?->id,
                    'subcategory' => $p['subcategory'] ?? null,
                    'subcategory_id' => $subcategory?->id,
                    'is_new' => $p['is_new'] ?? false,
                    'is_sale' => $p['is_sale'] ?? false,
                    'quantity' => 100,
                    'type' => 'Standard',
                    'brand' => 'YUPI GLOBAL'
                ]
            );
        }
    }
}
