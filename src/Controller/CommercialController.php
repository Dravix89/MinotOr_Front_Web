<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// _________________________________________CSRF (OK)_____________________________________________

class CommercialController extends AbstractController
{

    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/dashboard/commercial', name: 'app_commercial_dashboard')]
    public function index(): Response
    {
        return $this->render('commercial/index.html.twig');
    }
    // _______________________________________________________________________________


    #[Route('/commercial/fournisseurs', name: 'commercial_fournisseurs')]
    public function fournisseurs(Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $fournisseurs = [];

        try {
            $response = $this->client->request('GET', 'http://api:8000/api/fournisseurs', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $fournisseurs = $response->toArray();
            }
        } catch (\Exception $e) {
            // gérer l'erreur si besoin
        }

        return $this->render('commercial/fournisseurs.html.twig', [
            'fournisseurs' => $fournisseurs,
        ]);
    }


    //  ____________Créer un fournisseur (POST /commercial/fournisseurs/create)
    #[Route('/commercial/fournisseurs/create', name: 'commercial_fournisseurs_create', methods: ['POST'])]
    public function createFournisseur(Request $request): RedirectResponse
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $submittedToken = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('fournisseur_form', $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $data = [
            'nomEntreprise' => $request->request->get('nomEntreprise'),
            'nom' => $request->request->get('nom'),
            'prenom' => $request->request->get('prenom'),
            'email' => $request->request->get('email'),
            'telephone' => $request->request->get('telephone'),
            'siret' => $request->request->get('siret'),
            // Tu peux ajouter ici l’adresse si tu veux
        ];

        try {
            $this->client->request('POST', 'http://api:8000/api/fournisseurs', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($data),
            ]);
        } catch (\Exception $e) {
            // log erreur si tu veux
        }

        return $this->redirectToRoute('commercial_fournisseurs');
    }


    // ___________Modifier un fournisseur (PATCH /commercial/fournisseurs/edit/{id})
    #[Route('/commercial/fournisseurs/edit/{id}', name: 'commercial_fournisseurs_edit', methods: ['POST'])]
    public function editFournisseur(int $id, Request $request): RedirectResponse
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $submittedToken = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('fournisseur_form', $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $data = [
            'nomEntreprise' => $request->request->get('nomEntreprise'),
            'nom' => $request->request->get('nom'),
            'prenom' => $request->request->get('prenom'),
            'email' => $request->request->get('email'),
            'telephone' => $request->request->get('telephone'),
            'siret' => $request->request->get('siret'),
            // Adresse ici si modifiable
        ];

        try {
            $this->client->request('PATCH', 'http://api:8000/api/fournisseurs/' . $id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($data),
            ]);
        } catch (\Exception $e) {
            // gérer l'erreur si nécessaire
        }

        return $this->redirectToRoute('commercial_fournisseurs');
    }



    // _______________________________________________________________________________

    // _______________________________________________________________________________

    #[Route('/commercial/produits', name: 'commercial_produits')]
    public function produits(Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $produits = [];
        $stocks = [];


        try {
            $responseProduits = $this->client->request('GET', 'http://api:8000/api/produits', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            if ($responseProduits->getStatusCode() === 200) {
                $produits = $responseProduits->toArray();
                // dump($produits); // Pour voir ce que je recup !
            }
        } catch (\Exception $e) {
        }

        try {
            $responseStocks = $this->client->request('GET', 'http://api:8000/api/stocks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            if ($responseStocks->getStatusCode() === 200) {
                $stocks = $responseStocks->toArray();
                // dump($stocks); // Pour voir ce que je recup !
            }
        } catch (\Exception $e) {
        }

        $stocksMap = [];
        foreach ($stocks as $stock) {
            if (isset($stock['id'], $stock['quantiteDisponible'])) {
                $stocksMap[$stock['id']] = $stock['quantiteDisponible'];
            }
        }

        $finalProduits = [];
        foreach ($produits as $produit) {
            if (
                isset($produit['id'], $produit['nomProduit'], $produit['categorieProduit'], $produit['prixProduit'], $produit['typeProduit']['nomTypeProduit'])
            ) {
                $stock = $stocksMap[$produit['id']] ?? 0;
                $prixAchat = $produit['prixProduit'] / 1.2;
                $marge = 20;

                $finalProduits[] = [
                    'id' => $produit['id'],
                    'reference' => $produit['id'],
                    'nom' => $produit['nomProduit'],
                    'description' => $produit['nomProduit'] . ' - (' . $produit['categorieProduit'] . ')',
                    'prixAchat' => round($produit['prixProduit'] ?? 0, 2),
                    'marge' => 20,
                    'prixVente' => round(($produit['prixProduit'] ?? 0) * 1.2, 2),
                    'stock' => $stock, // nombre ou autre info
                    'stock_status' => ($stock > 0) ? 'disponible' : 'attente', // exemple
                ];
            }
        }

        // dump($finalProduits); // Pour voir ce que je recup !

        return $this->render('commercial/produits.html.twig', [
            'produits' => $finalProduits,
        ]);
    }


    // _______________________________________________________________________________
    #[Route('/commercial/produits/save', name: 'product_save', methods: ['POST'])]
    public function saveProduit(Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $submittedToken = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('fournisseur_form', $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $data = [
            'reference' => $request->request->get('reference'),
            'nomProduit' => $request->request->get('nom'),
            'categorieProduit' => '',
            'prixProduit' => $request->request->get('prixVente'),
            'typeProduit' => [
                'nomTypeProduit' => $request->request->get('nom'),
            ],
            'stock' => [
                'quantiteDisponible' => (float) $request->request->get('stock'),
            ]
        ];

        try {
            $this->client->request('POST', 'http://api:8000/api/produits', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);
        } catch (\Exception $e) {
        }

        return $this->redirectToRoute('commercial_produits');
    }


    // _______________________________________________________________________________


    // _______________________________________________________________________________

    #[Route('/commercial/devis', name: 'commercial_devis')]
    public function devis(Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        // Récupérer l'ID du commercial connecté (adapter selon ta gestion de session/token)
        $commercialId = $session->get('user_id'); // <-- à adapter si nécessaire

        $transactions = [];
        $livraisons = [];

        try {
            // Récupération transactions
            $response = $this->client->request('GET', 'http://api:8000/api/transactions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            if ($response->getStatusCode() === 200) {
                $transactions = $response->toArray();
            }

            // Récupération livraisons
            $responseLivraison = $this->client->request('GET', 'http://api:8000/api/livraisons', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            if ($responseLivraison->getStatusCode() === 200) {
                $livraisons = $responseLivraison->toArray();
            }
        } catch (\Exception $e) {
            // Log ou gestion d'erreur ici si besoin
        }

        // Indexer livraisons par transaction_id
        $livraisonsByTransactionId = [];
        foreach ($livraisons as $livraison) {
            if (isset($livraison['transaction']['id'])) {
                $livraisonsByTransactionId[$livraison['transaction']['id']] = $livraison;
            }
        }

        // Filtrer les transactions de type "Devis"
        $devisListRaw = array_filter($transactions, fn($t) => ($t['typeTransaction'] ?? '') === 'Devis');

        // Construire liste devis avec infos complémentaires
        $devisList = array_map(function ($devis) use ($livraisonsByTransactionId) {
            $id = $devis['id'];
            $livraison = $livraisonsByTransactionId[$id] ?? null;

            $fraisLivraison = 0;
            if ($livraison && isset($livraison['vehicule'])) {
                $fraisLivraison = $livraison['vehicule']['frais'] ?? 0;
            }

            $totalProduits = 0;
            if (isset($devis['estContenus'])) {
                foreach ($devis['estContenus'] as $contenu) {
                    $prix = $contenu['produit']['prixProduit'] ?? 0;
                    $qte = $contenu['quantiteProduit'] ?? 0;
                    $totalProduits += $prix * $qte;
                }
            }

            $ristourneGlobale = $devis['montantTotal'] - $totalProduits - $fraisLivraison;

            $dateCreation = $devis['dateCreation'] ?? null;
            if ($dateCreation) {
                $dateCreation = (new \DateTime($dateCreation))->format('d/m/Y');
            } else {
                $dateCreation = '—';
            }

            return [
                'id'             => $id,
                'client_id'      => $devis['client']['id'] ?? '—',
                'commercial_id'  => $devis['commercial']['id'] ?? '—',
                'date_creation'  => $dateCreation,
                'montant_total'  => $devis['montantTotal'] ?? 0,
                'delivery_fee'   => $fraisLivraison,
                'global_discount' => $ristourneGlobale,
                'delivery_date'  => $livraison['dateLivraison'] ?? '—',
            ];
        }, $devisListRaw);

        // Séparer les devis : ceux du commercial connecté et les autres
        $mesDevis = array_filter($devisList, fn($devis) => $devis['commercial_id'] == $commercialId);
        $autresDevis = array_filter($devisList, fn($devis) => $devis['commercial_id'] != $commercialId);

        $devisByStatus = [
            'a_traiter' => $autresDevis,
            'mes_devis' => $mesDevis,
            'termine'   => [],
        ];

        return $this->render('commercial/devis.html.twig', [
            'devisByStatus' => $devisByStatus,
        ]);
    }



    //     _______Notes = Tables utilisées :

    // - transactions
    // - livraisons = livrer
    // - vehicules (pour frais livraison) / type_vehicule
    // - clients (pour infos client)
    // - commerciaux (pour infos commercial)

    // Relations importantes :
    // - livraison.transaction_id -> transaction.id
    // - transaction.client_id -> client.id
    // - livraison.vehicule_id -> vehicule.id
    // - transaction.commercial_id -> commercial.id



    // _______________________________________________________________________________

    #[Route('/commercial/devis/take-in-charge/{id}', name: 'commercial_devis_take_in_charge', methods: ['POST'])]
    public function takeInCharge(int $id, Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $userId = $session->get('user_id');

        if (!$token || !$userId) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        try {
            $this->client->request('PATCH', "http://api:8000/api/transactions/$id", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/merge-patch+json',
                ],
                'json' => [
                    'commercial' => "/api/users/$userId",
                ],
            ]);
        } catch (\Exception $e) {
            // Optionnel : log
        }

        return $this->redirectToRoute('commercial_devis');
    }


    // -------------------------------------------------

    #[Route('/commercial/devis/finish/{id}', name: 'commercial_devis_finish', methods: ['POST'])]
    public function finishDevis(int $id, Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        try {
            $this->client->request('PATCH', "http://api:8000/api/transactions/$id", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/merge-patch+json',
                ],
                'json' => [
                    'statut' => 'Terminé',
                ],
            ]);
        } catch (\Exception $e) {
        }

        return $this->redirectToRoute('commercial_devis');
    }


    // -------------------------------------------------

    #[Route('/commercial/devis/send-email/{id}', name: 'commercial_devis_send_email', methods: ['POST'])]
    public function sendEmail(int $id, Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        try {
            $this->client->request('POST', "http://api:8000/api/transactions/$id/send-email", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
        } catch (\Exception $e) {
            // Optionnel : log
        }

        return $this->redirectToRoute('commercial_devis');
    }

    // -------------------------------------------------
    #[Route('/commercial/devis/edit/{id}', name: 'commercial_devis_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        // GET : récupérer les infos actuelles
        try {
            $response = $this->client->request('GET', "http://api:8000/api/transactions/$id", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            $devis = $response->toArray();
        } catch (\Exception $e) {
            throw $this->createNotFoundException("Devis non trouvé.");
        }

        // POST : traitement du formulaire
        if ($request->isMethod('POST')) {
            $newDateLivraison = $request->request->get('delivery_date'); // ex: champ <input name="delivery_date" />

            try {
                $this->client->request('PATCH', "http://api:8000/api/transactions/$id", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/merge-patch+json',
                    ],
                    'json' => [
                        'dateLivraison' => $newDateLivraison,
                    ],
                ]);
            } catch (\Exception $e) {
                // Optionnel : log
            }

            return $this->redirectToRoute('commercial_devis');
        }

        return $this->render('commercial/edit_devis.html.twig', [
            'devis' => $devis,
        ]);
    }



    //     Prendre en charge	/commercial/devis/take-in-charge/{id}	POST
    //     Terminer	/commercial/devis/finish/{id}	POST
    //     Envoyer l'email	/commercial/devis/send-email/{id}	POST
    //     Modifier	/commercial/devis/edit/{id}	GET+POST

    // -------------------------------------------------
    #[Route('/commercial/devis/new', name: 'commercial_devis_new')]
    public function newDevis(Request $request, SessionInterface $session): Response
    {

        // Paramètres de livraison transmis à la vue
        $params = [
            'coutKilometreVrac' => 2.50,
            'fraisFixeVrac' => 175.00,
            'coutKilometrePaletteMinoterie' => 1.25,
            'fraisFixePaletteMinoterie' => 75.00,
            'coutKilometrePaletteEntrepot' => 1.25,
            'baseFraisFixeEntrepot' => 50.00,
            'prixPreparationKg' => 0.05,
        ];

        // Vérifie si la requête est en POST et valide le token CSRF pour protéger contre les attaques CSRF, sinon déclenche une exception d'accès refusé.
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('commercial_devis_new', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide');
            }

            $devisList = $session->get('devis', []);
            // Récupération des données du formulaire
            $clientName = $request->request->get('client');
            $deliveryDate = new \DateTime($request->request->get('dateLivraison'));
            $distanceKm = (float) $request->request->get('distance_km');
            $typeTransport = $request->request->get('type_transport');
            $ristourneGlobale = (float) $request->request->get('ristourne_globale');
            $status = $request->request->get('status');
            $articles = $request->request->get('articles', []);

            // Calcul frais livraison
            switch ($typeTransport) {
                case 'vrac':
                    $fraisLivraison = $params['fraisFixeVrac'] + ($params['coutKilometreVrac'] * $distanceKm);
                    break;
                case 'palette_minoterie':
                    $fraisLivraison = $params['fraisFixePaletteMinoterie'] + ($params['coutKilometrePaletteMinoterie'] * $distanceKm);
                    break;
                case 'palette_entrepot':
                    $fraisLivraison = $params['baseFraisFixeEntrepot'] + ($params['coutKilometrePaletteEntrepot'] * $distanceKm);
                    break;
                default:
                    $fraisLivraison = 0;
            }

            $articles = $request->request->get('articles', []);
            if (!is_array($articles)) {
                $articles = [];
            }

            // Calcul total_ht des articles
            $totalHt = 0;
            foreach ($articles as &$article) {
                // Pour chaque article, calcule prix total sans ristourne
                $qty = (float) $article['quantity'];
                $priceUnit = (float) $article['price_unit'];
                $totalHt += $qty * $priceUnit;
                // Ajoute marge par défaut si absente
                if (!isset($article['marge'])) {
                    $article['marge'] = 20;
                }
                // Normalise ristourne à 0 si vide
                if (!isset($article['ristourne']) || $article['ristourne'] === '') {
                    $article['ristourne'] = 0;
                }
            }
            unset($article);

            // Générer une référence unique simple (ex: DEV- + timestamp)
            $reference = 'DEV-' . (new \DateTime())->format('YmdHis');

            $newDevis = [
                'reference' => $reference,
                'client_name' => $clientName,
                'status' => $status,
                // 'assigned_to' => $this->getUser()->getUserIdentifier(),
                'assigned_to' => $this->getUser() ? $this->getUser()->getUserIdentifier() : 'commercial1',
                'delivery_date' => $deliveryDate,
                'ristourne_globale' => $ristourneGlobale,
                'distance_km' => $distanceKm,
                'type_transport' => $typeTransport,
                'total_ht' => $totalHt,
                'articles' => $articles,
                'delivery_fee' => round($fraisLivraison, 2),
                'email_sent' => false,
            ];

            $devisList[] = $newDevis;
            $session->set('devis', $devisList);

            return $this->redirectToRoute('commercial_devis');
        }

        return $this->render('commercial/new_devis.html.twig', [
            'params' => $params,
        ]);
    }


    // _______________________________________________________________________________

    #[Route('/commercial/livraison', name: 'commercial_livraison')]
    public function livraison(Request $request, SessionInterface $session): Response
    {
        $token = $session->get('backoffice_token');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        // $params = [
        //     'coutKilometreVrac' => null,
        //     'fraisFixeVrac' => null,
        //     'coutKilometrePaletteMinoterie' => null,
        //     'fraisFixePaletteMinoterie' => null,
        //     'coutKilometrePaletteEntrepot' => null,
        //     'baseFraisFixeEntrepot' => null,
        //     'prixPreparationKg' => null,
        // ];

        $params = [
            'coutKilometreVrac' => 2.50,
            'fraisFixeVrac' => 175.00,
            'coutKilometrePaletteMinoterie' => 1.25,
            'fraisFixePaletteMinoterie' => 75.00,
            'coutKilometrePaletteEntrepot' => 1.25,
            'baseFraisFixeEntrepot' => 50.00,
            'prixPreparationKg' => 0.05,
        ];

        try {
            // 🔹 Tarifs depuis l’API (requiert des `code` non NULL en BDD)
            $tarifResponse = $this->client->request('GET', 'http://api:8000/api/tarifs-livraison', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            $tarifs = $tarifResponse->toArray();

            foreach ($tarifs as $tarif) {
                if (!isset($tarif['code']) || !($tarif['actif'] ?? false)) {
                    continue;
                }

                $code = $tarif['code'];
                $valeur = isset($tarif['preparation_kg']) ? (float) $tarif['preparation_kg']
                    : (isset($tarif['base']) ? (float) $tarif['base']
                        : (isset($tarif['frais_fixe']) ? (float) $tarif['frais_fixe']
                            : null));

                switch ($code) {
                    case 'FRAIS_FIXE_MONOCUVE':
                        $params['fraisFixeVrac'] = $valeur;
                        break;
                    case 'FRAIS_FIXE_PALETTE_FOURNISSEUR':
                        $params['fraisFixePaletteMinoterie'] = $valeur;
                        break;
                    case 'FRAIS_FIXE_PALETTE_ENTREPOT':
                        $params['baseFraisFixeEntrepot'] = $valeur;
                        break;
                    case 'PRIX_PREPARATION_PAR_KG':
                        $params['prixPreparationKg'] = $valeur;
                        break;
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', '⚠️ Erreur récupération tarifs livraison.');
        }

        try {
            // 🔹 Véhicules (cuve / palette)
            $response = $this->client->request('GET', 'http://api:8000/api/vehicules', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            $vehicules = $response->toArray();

            foreach ($vehicules as $vehicule) {
                if (!isset($vehicule['type_vehicule'], $vehicule['cout_kilometrique'])) {
                    continue;
                }

                $type = $vehicule['type_vehicule'];
                $cout = (float) $vehicule['cout_kilometrique'];

                if ($type === 'cuve') {
                    $params['coutKilometreVrac'] = $cout;
                }

                if ($type === 'palette') {
                    $params['coutKilometrePaletteMinoterie'] = $cout;
                    $params['coutKilometrePaletteEntrepot'] = $cout;
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', '⚠️ Erreur récupération véhicules.');
        }

        // 🔹 Enregistrement
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('livraison_save', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF invalide');
            }

            $params = array_map('floatval', [
                'coutKilometreVrac' => $request->request->get('coutKilometreVrac'),
                'fraisFixeVrac' => $request->request->get('fraisFixeVrac'),
                'coutKilometrePaletteMinoterie' => $request->request->get('coutKilometrePaletteMinoterie'),
                'fraisFixePaletteMinoterie' => $request->request->get('fraisFixePaletteMinoterie'),
                'coutKilometrePaletteEntrepot' => $request->request->get('coutKilometrePaletteEntrepot'),
                'baseFraisFixeEntrepot' => $request->request->get('baseFraisFixeEntrepot'),
                'prixPreparationKg' => $request->request->get('prixPreparationKg'),
            ]);

            // TODO: Sauvegarde à faire ici via API
            $this->addFlash('success', '✅ Paramètres enregistrés.');
            return $this->redirectToRoute('commercial_livraison');
        }

        return $this->render('commercial/livraison.html.twig', [
            'params' => $params,
        ]);
    }
}
