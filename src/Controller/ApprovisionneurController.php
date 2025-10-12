<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;





// _________________________________________CSRF (OK)_____________________________________________




class ApprovisionneurController extends AbstractController
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }



    // =========================
    // DASHBOARD
    // =========================

    #[Route('/dashboard/approvisionneur', name: 'dashboard_approvisionneur')]
    public function dashboard(Request $request): Response
    {
        $lastUpdate = new \DateTime();

        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $idApprov = $session->get('approvisionneur_id');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $stocks = [];
        $produits = [];
        $receptions = [];
        $commandes = [];
        $livraisons = [];
        $alerts = [];
        $preparations = [];

        try {
            $responseStocks = $this->client->request('GET', 'http://api:8000/api/stocks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($responseStocks->getStatusCode() === 200) {
                $stocks = $responseStocks->toArray();

                // Debug entrepots (ids)
                foreach ($stocks as $stock) {
                    $idEntrepot = $stock['gerers'][0]['entrepot']['id'] ?? null;
                    // Ici tu changes 'nomEntrepot' par un autre champ que tu as dans la data, par exemple 'nom'
                    $nomEntrepot = $stock['gerers'][0]['entrepot']['nomEntrepot']
                        ?? $stock['gerers'][0]['entrepot']['nom']
                        ?? '';
                    if ($idEntrepot) {
                        $entrepotNames[$idEntrepot] = $nomEntrepot;
                    }
                }
            }
        } catch (\Exception $e) {
            // gérer exception stocks si besoin
        }

        try {
            $responseProduits = $this->client->request('GET', 'http://api:8000/api/produits');
            if ($responseProduits->getStatusCode() === 200) {
                $produits = $responseProduits->toArray();
            }
        } catch (\Exception $e) {
            // gérer exception produits si besoin
        }

        foreach ($stocks as $stock) {
            $entrepotRef = $stock['gerers'][0]['entrepot']['id'] ?? null;
            $produitRef = $stock['entreposers'][0]['produit']['nomProduit'] ?? null;
            $kg = $stock['quantiteDisponible'] ?? 0;

            if ($entrepotRef !== null && $produitRef !== null) {
                $receptions[] = [
                    'entrepotRef' => $entrepotRef,
                    'produitRef' => $produitRef,
                    'kg' => $kg,
                ];
            }
        }

        try {
            $responseTransactions = $this->client->request('GET', 'http://api:8000/api/transactions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($responseTransactions->getStatusCode() === 200) {
                $dataTransactions = $responseTransactions->toArray();

                // Construire un tableau idEntrepot => nomEntrepot pour remplacer les IDs par noms
                $entrepotNames = [];
                foreach ($stocks as $stock) {
                    $idEntrepot = $stock['gerers'][0]['entrepot']['id'] ?? null;
                    $nomEntrepot = $stock['gerers'][0]['entrepot']['nomEntrepot']
                        ?? $stock['gerers'][0]['entrepot']['nom']
                        ?? '';
                    if ($idEntrepot) {
                        $entrepotNames[$idEntrepot] = $nomEntrepot;
                    }
                }


                foreach ($dataTransactions as $transaction) {
                    if ($transaction['typeTransaction'] === 'Commande') {
                        try {
                            $responseDetail = $this->client->request('GET', 'http://api:8000/api/transactions/' . $transaction['id'], [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $token,
                                ],
                            ]);

                            if ($responseDetail->getStatusCode() === 200) {
                                $transactionDetail = $responseDetail->toArray();


                                $entrepotId = $transactionDetail['entrepot']['id'] ?? null;
                                $nomEntrepot = ($entrepotId && isset($entrepotNames[$entrepotId])) ? $entrepotNames[$entrepotId] : '';

                                foreach ($transactionDetail['estContenus'] as $item) {
                                    $commandes[] = [
                                        'entrepotRef' => $nomEntrepot,
                                        'produitRef' => $item['produit']['nomProduit'] ?? '',
                                        'kg' => $item['quantiteProduit'] ?? 0,
                                    ];
                                }
                            }
                        } catch (\Exception $e) {
                            // gérer erreur détail transaction si besoin
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // gérer exception transactions si besoin
        }

        // Récupération des préparations avec nom d'entrepôt
        try {
            $responsePreparations = $this->client->request('GET', 'http://api:8000/api/preparations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($responsePreparations->getStatusCode() === 200) {
                $dataPreparations = $responsePreparations->toArray();

                $preparations = []; // vide avant boucle
                foreach ($dataPreparations as $prepa) {
                    $preparations[] = [
                        'id' => $prepa['id'],
                        'transaction_id' => $prepa['transaction']['id'] ?? null,
                        'statut_prepa' => $prepa['statutPrepa'] ?? 'N/A',
                        'date_preparation' => $prepa['datePreparation'] ?? null,
                        'nom_entrepot' => $prepa['entrepot']['nomEntrepot'] ?? '',
                    ];
                }
            } else {
                throw new \Exception('Préparations non trouvées');
            }
        } catch (\Exception $e) {
            // gérer exception préparations si besoin
        }


        try {
            $responseLivraisons = $this->client->request('GET', 'http://api:8000/api/livraisons', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($responseLivraisons->getStatusCode() === 200) {
                $livraisons = $responseLivraisons->toArray();
            }
        } catch (\Exception $e) {
            // gérer exception livraisons si besoin
        }

        $seuil = 100;
        foreach ($stocks as $stock) {
            $quantite = $stock['quantiteDisponible'] ?? 0;
            if ($quantite < $seuil) {
                $alerts[] = [
                    'produitRef' => $stock['entreposers'][0]['produit']['nomProduit'] ?? 'Inconnu',
                    'entrepotRef' => $stock['gerers'][0]['entrepot']['id'] ?? 'N/A',
                    'quantiteDisponible' => $quantite,
                ];
            }
        }

        return $this->render('approvisionneur/dashboard.html.twig', [
            'lastUpdate' => $lastUpdate,
            'receptions' => $receptions,
            'commandes' => $commandes,
            'alerts' => $alerts,
            'livraisons' => $livraisons,
            'preparations' => $preparations,  // <-- ici l'ajout
        ]);
    }


    // =========================
    // COMMANDE
    // =========================
    #[Route('/commande', name: 'commande')]
    public function commande(Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $idApprov = $session->get('approvisionneur_id');

        dump([
            'token' => $token,
            'idApprov' => $idApprov,
        ]);

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }
        if (!$idApprov) {
            throw new \Exception('ID approvisionneur manquant.');
        }

        try {
            // 1. Récupérer les intentions de l'approvisionneur
            $responseIntentions = $httpClient->request('GET', 'http://api:8000/api/intentions/approvisionneur/' . $idApprov, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $intentions = $responseIntentions->toArray();
            dump($intentions);

            $demandes = [];
            dump($demandes);

            foreach ($intentions as $intention) {
                $idIntention = $intention['id'];

                // 2. Récupérer les produits liés à l'intention
                $produitsIntention = [];
                try {
                    $responseProduits = $httpClient->request('GET', "http://api:8000/api/intentions/$idIntention/produits", [
                        'headers' => ['Authorization' => 'Bearer ' . $token],
                    ]);
                    $produitsIntention = array_map(fn($p) => $p['nomProduit'] ?? $p['nom_produit'] ?? '', $responseProduits->toArray());
                } catch (\Exception) {
                    $produitsIntention = [];
                }

                // 3. Récupérer les transactions liées à l'intention
                $transactions = [];
                try {
                    $responseTransactions = $httpClient->request('GET', "http://api:8000/api/intentions/$idIntention/transactions", [
                        'headers' => ['Authorization' => 'Bearer ' . $token],
                    ]);
                    $transactions = $responseTransactions->toArray();

                    dump($transactions);
                } catch (\Exception) {
                    $transactions = [];
                }

                $dateReponse = null;
                $statutsPaiement = [];
                $commandePasse = false;
                $commentairesClient = [];
                $commentairesApprov = [];
                $statutsLivraison = [];
                $dateLivraison = null;

                foreach ($transactions as $t) {
                    // ✅ récupère la date la plus récente
                    $currentDate = $t['dateCreation'] ?? $t['date_creation'] ?? null;
                    if ($currentDate && (!$dateReponse || strtotime($currentDate) > strtotime($dateReponse))) {
                        $dateReponse = $currentDate;
                    }


                    if (!empty($t['statutPaiement'] ?? $t['statut_paiement'])) {
                        $statutsPaiement[] = $t['statutPaiement'] ?? $t['statut_paiement'];
                    }
                    if (($t['typeTransaction'] ?? $t['type_transaction'] ?? '') === 'Commande') {
                        $commandePasse = true;
                    }
                    if (!empty($t['commentaireClient'] ?? $t['commentaire_client'])) {
                        $commentairesClient[] = $t['commentaireClient'] ?? $t['commentaire_client'];
                    }

                    // 4. Récupérer les livraisons pour chaque transaction
                    try {
                        $responseLivraisons = $httpClient->request('GET', "http://api:8000/api/transactions/{$t['id']}/livraisons", [
                            'headers' => ['Authorization' => 'Bearer ' . $token],
                        ]);
                        $livraisons = $responseLivraisons->toArray();

                        foreach ($livraisons as $livraison) {
                            if (!empty($livraison['commentaireApprovisionneur'] ?? $livraison['commentaire_approvisionneur'])) {
                                $commentairesApprov[] = $livraison['commentaireApprovisionneur'] ?? $livraison['commentaire_approvisionneur'];
                            }
                            if (!empty($livraison['dateLivraison'] ?? $livraison['date_livraison'])) {
                                $dateLivraison = $livraison['dateLivraison'] ?? $livraison['date_livraison'];
                            }
                            if (!empty($livraison['statutLivraison'] ?? $livraison['statut_livraison'])) {
                                $statutsLivraison[] = $livraison['statutLivraison'] ?? $livraison['statut_livraison'];
                            }
                        }
                    } catch (\Exception) {
                        // pas de livraison trouvée, on ignore
                    }
                }

                $demandes[] = [
                    'id' => $idIntention,
                    'date_demande' => (new \DateTime($intention['date']))->format('Y-m-d'),
                    'date_reponse' => $dateReponse ? (new \DateTime($dateReponse))->format('Y-m-d') : '',
                    'produits' => implode(', ', $produitsIntention),
                    'minoterie' => $intention['fournisseur']['nomEntreprise'] ?? $intention['fournisseur']['nom_entreprise'] ?? '',
                    'demande_acceptee' => count($statutsPaiement) > 0,
                    'commande_passee' => $commandePasse,
                    'commentaire' => implode(' | ', array_merge($commentairesClient, $commentairesApprov)),
                    'statut_livraison' => implode(', ', $statutsLivraison),
                    'date_livraison' => $dateLivraison ? (new \DateTime($dateLivraison))->format('Y-m-d') : '',
                ];
            }
        } catch (\Exception) {
            $demandes = [];
        }

        // Récupération commerciaux et produits
        try {
            $responseCommerciaux = $httpClient->request('GET', 'http://api:8000/api/commerciaux', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            $commerciaux = $responseCommerciaux->toArray();
            $commercialId = $commerciaux[0]['id'] ?? null;
        } catch (\Exception) {
            $commerciaux = [];
            $commercialId = null;
        }

        try {
            $responseProduits = $httpClient->request('GET', 'http://api:8000/api/produits', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            $produits = $responseProduits->toArray();
        } catch (\Exception) {
            $produits = [];
        }

        return $this->render('approvisionneur/commande.html.twig', [
            'demandes' => $demandes,
            'commerciaux' => $commerciaux,
            'produits' => $produits,
            'commercial_id' => $commercialId,
        ]);
    }



    #[Route('/commande/create', name: 'commande_create', methods: ['POST'])]
    public function commandeCreate(Request $request, CsrfTokenManagerInterface $csrfTokenManager, HttpClientInterface $httpClient): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('commande_create', $submittedToken))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }


        $commercialId = $request->request->get('commercial_id');
        $produitId = $request->request->get('produit_id');
        $message = trim($request->request->get('message'));

        $errors = [];


        if (!$commercialId) {
            $errors[] = 'Commercial non renseigné.';
        }
        if (!$produitId) {
            $errors[] = 'Produit non renseigné.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            $clientsResponse = $httpClient->request('GET', 'http://api:8000/api/clients');
            $commerciauxResponse = $httpClient->request('GET', 'http://api:8000/api/commerciaux');
            $produitsResponse = $httpClient->request('GET', 'http://api:8000/api/produits');

            $clients = $clientsResponse->toArray();
            $commerciaux = $commerciauxResponse->toArray();
            $produits = $produitsResponse->toArray();

            return $this->render('commande/form.html.twig', [
                'clients' => $clients,
                'commerciaux' => $commerciaux,
                'produits' => $produits,
                'commercial_id' => $commercialId,
            ]);
        }

        try {
            $payload = [
                'commercial_id' => (int) $commercialId,
                'produit_id' => (int) $produitId,
                'message' => $message,
            ];

            $response = $httpClient->request('POST', 'http://api:8000/api/transactions', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $this->addFlash('success', 'Transaction envoyée avec succès à l’API !');
            } else {
                $this->addFlash('error', 'Échec de l’envoi à l’API. Code : ' . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l’envoi de la transaction : ' . $e->getMessage());
        }

        return $this->redirectToRoute('commande');
    }




    // =========================
    // ENTREPOT
    // =========================

    #[Route('/entrepot', name: 'entrepot')]
    public function entrepotIndex(Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $idApprov = $session->get('approvisionneur_id');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $entrepots = [];
        $transactions = [];

        try {
            // 1. Récupération des entrepôts
            $response = $this->client->request('GET', 'http://api:8000/api/entrepots', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $entrepots = $response->toArray();
            }

            // 2. Récupération de toutes les préparations (qui lient entrepôts et transactions)
            try {
                $response = $this->client->request('GET', 'http://api:8000/api/preparations/enCours', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ]);

                if ($response->getStatusCode() === 200) {
                    $preparations = $response->toArray();
                    $transactions = [];

                    foreach ($preparations as $prepa) {
                        if (isset($prepa['transaction']['typeTransaction']) && $prepa['transaction']['typeTransaction'] === 'Commande') {
                            $transactions[] = $prepa['transaction'];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Gérer erreur
                $transactions = [];
            }
        } catch (\Exception $e) {
            // dump('Erreur API : ' . $e->getMessage());
            // die();
        }
        return $this->render('approvisionneur/entrepot.html.twig', [
            'entrepots' => $entrepots,
            'transactions' => $transactions,
        ]);
    }







    #[Route('/entrepot/{id}', name: 'entrepot_show', methods: ['GET', 'POST'])]
    public function entrepotShow(string $id, Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $idApprov = $session->get('approvisionneur_id');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        // 1. Récupération de l'entrepôt
        try {
            $response = $this->client->request('GET', "http://api:8000/api/entrepots/$id", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            if ($response->getStatusCode() !== 200) {
                throw $this->createNotFoundException("Entrepôt non trouvé.");
            }
            $entrepot = $response->toArray();
        } catch (\Exception $e) {
            throw $this->createNotFoundException("Entrepôt non trouvé.");
        }

        // 2. Récupération des produits
        $produits = [];
        try {
            $responseProduits = $this->client->request('GET', "http://api:8000/api/entrepots/$id/produits", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            if ($responseProduits->getStatusCode() === 200) {
                $produits = $responseProduits->toArray();

                if (!is_array($produits) || empty($produits)) {
                    $produits = [];
                } elseif (isset($produits[0]) && !is_array($produits[0])) {
                    // Si on a un tableau de chaînes, on les convertit en tableau associatif avec la clé correcte
                    $produits = array_map(fn($nom) => ['nom_produit' => $nom], $produits);
                }

                // Log produits ici
                error_log("Produits: " . print_r($produits, true));
            }
        } catch (\Exception $e) {
            $produits = [];
        }

        // 3. Récupération des stocks
        $stocks = [];
        try {
            $responseStocks = $this->client->request('GET', "http://api:8000/api/entrepots/$id/stocks", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            if ($responseStocks->getStatusCode() === 200) {
                $stocks = $responseStocks->toArray();

                // Transformation pour simplifier l'accès dans Twig
                foreach ($stocks as &$stock) {
                    $stock['nom_produit'] = $stock['entreposers'][0]['produit']['nomProduit'] ?? 'N/A';
                    $stock['quantite_disponible'] = $stock['quantiteDisponible'] ?? 0;
                    $stock['quantite_reserve'] = $stock['quantiteReserve'] ?? 0;
                }
                unset($stock);

                // Log stocks ici
                error_log("Stocks transformés: " . print_r($stocks, true));
            }
        } catch (\Exception $e) {
            $stocks = [];
        }

        // 4. Récupération des livraisons (pour date dernière commande)
        $livraisons = [];
        try {
            $responseLivraisons = $this->client->request('GET', "http://api:8000/api/entrepots/$id/livraisons", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            if ($responseLivraisons->getStatusCode() === 200) {
                $livraisons = $responseLivraisons->toArray();
            }
        } catch (\Exception $e) {
            $livraisons = [];
        }

        // 5. Organiser stocks par produit_id
        $stocksByProduitId = [];
        foreach ($stocks as $stock) {
            if (isset($stock['produit_id'])) {
                $stocksByProduitId[$stock['produit_id']] = $stock;
            }
        }

        // 6. Dernière date de commande par nom produit (en minuscules)
        $dateDerniereCommandeParProduit = [];
        foreach ($livraisons as $livraison) {
            $produitNom = strtolower($livraison['type_marchandise'] ?? '');
            $dateLivraison = isset($livraison['date_livraison']) ? new \DateTime($livraison['date_livraison']) : null;
            if ($dateLivraison) {
                if (!isset($dateDerniereCommandeParProduit[$produitNom]) || $dateLivraison > $dateDerniereCommandeParProduit[$produitNom]) {
                    $dateDerniereCommandeParProduit[$produitNom] = $dateLivraison;
                }
            }
        }

        // 7. Fusionner données dans produits, avec vérification que $produit est bien un tableau
        foreach ($produits as &$produit) {
            if (!is_array($produit)) {
                continue;
            }

            $produitId = $produit['id'] ?? null;
            $nomProduitLower = strtolower($produit['nom_produit'] ?? '');

            // Stock
            $produit['stock'] = ($produitId && isset($stocksByProduitId[$produitId]))
                ? $stocksByProduitId[$produitId]['quantite_disponible']
                : 0;

            // Catégorie (déjà dispo normalement)
            $produit['categorie_produit'] = $produit['categorie_produit'] ?? 'N/A';

            // Date dernière commande
            if (isset($dateDerniereCommandeParProduit[$nomProduitLower])) {
                $produit['date_derniere_commande'] = $dateDerniereCommandeParProduit[$nomProduitLower]->format('Y-m-d');
            } else {
                $produit['date_derniere_commande'] = 'N/A';
            }
        }
        unset($produit);
        // dump($produits);

        // --- Gestion du formulaire POST ---
        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('entrepot_show', $submittedToken)) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $produitNom = trim($request->request->get('produit'));
            $date = $request->request->get('date_derniere_commande');
            $categorie = trim($request->request->get('categorie'));
            $stock = (int) $request->request->get('stock');

            $errors = [];

            if (!$produitNom) {
                $errors[] = 'Le nom du produit est obligatoire.';
            }
            if (!$date || !\DateTime::createFromFormat('Y-m-d', $date)) {
                $errors[] = 'La date de dernière commande est invalide ou manquante.';
            }
            if (!$categorie) {
                $errors[] = 'La catégorie est obligatoire.';
            }
            if ($stock <= 0) {
                $errors[] = 'Le stock doit être un nombre positif.';
            }

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                try {
                    $responseCreate = $this->client->request('POST', "http://api:8000/api/produits", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'type_produit_id' => 1,
                            'nom_produit' => $produitNom,
                            'prix_produit' => 0,
                            'categorie_produit' => $categorie,
                            'stock' => $stock,
                        ],
                    ]);
                    if ($responseCreate->getStatusCode() === 201) {
                        $this->addFlash('success', 'Produit ajouté avec succès.');
                        return $this->redirectToRoute('entrepot_show', ['id' => $id]);
                    } else {
                        $this->addFlash('error', 'Erreur lors de l\'ajout du produit.');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'appel API : ' . $e->getMessage());
                }
            }
        }

        return $this->render('approvisionneur/entrepot_show.html.twig', [
            'entrepot' => $entrepot,
            'produits' => $produits,
            'stocks' => $stocks,
        ]);
    }






    // =========================
    // FOURNISSEUR / COLLECTE
    // =========================

    #[Route('/collecte/fournisseur', name: 'collecte_fournisseur', methods: ['GET', 'POST'])]
    public function collecteFournisseur(Request $request, HttpClientInterface $httpClient): Response
    {
        $message = null;

        $token = $request->getSession()->get('backoffice_token');
        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('livraison_form', $submittedToken)) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $entrepot = $request->request->get('entrepot');
            $camion = $request->request->get('camion');
            $dateCommande = $request->request->get('dateCommande');
            $dateLivraison = $request->request->get('dateLivraison');
            $factureFile = $request->files->get('facture');

            $factureFilename = null;
            if ($factureFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/factures';
                $factureFilename = uniqid() . '.' . $factureFile->guessExtension();
                try {
                    $factureFile->move($uploadsDir, $factureFilename);
                } catch (FileException $e) {
                    $message = "Erreur lors de l'upload de la facture : " . $e->getMessage();
                }
            }

            if (!$message) {
                $data = [
                    'entrepot' => $entrepot,
                    'camion' => $camion,
                    'dateCommande' => $dateCommande,
                    'dateLivraison' => $dateLivraison,
                    'facture' => $factureFilename,
                ];

                try {
                    $response = $httpClient->request('POST', 'http://api:8000/api/livraisons', [
                        'json' => $data,
                    ]);

                    if (in_array($response->getStatusCode(), [200, 201])) {
                        $message = "Livraison enregistrée avec succès via l'API.";
                    } else {
                        $message = "Erreur API : " . $response->getContent(false);
                    }
                } catch (\Exception $e) {
                    $message = "Erreur lors de la communication avec l'API : " . $e->getMessage();
                }
            }
        }

        $livraisons = [];
        $transactions = [];

        // Récup livraisons
        try {
            $response = $httpClient->request('GET', 'http://api:8000/api/livraisons', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $livraisons = $response->toArray();
            } else {
                $message = "Erreur lors de la récupération des livraisons : code " . $response->getStatusCode();
            }
        } catch (\Exception $e) {
            $message = "Erreur lors de la récupération des livraisons : " . $e->getMessage();
        }

        // Récup transactions
        try {
            $response = $httpClient->request('GET', 'http://api:8000/api/transactions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $transactions = $response->toArray();
            } else {
                $message = "Erreur lors de la récupération des transactions : code " . $response->getStatusCode();
            }
        } catch (\Exception $e) {
            $message = "Erreur lors de la récupération des transactions : " . $e->getMessage();
        }

        // Index transactions par id
        $transactionsIndexed = [];
        foreach ($transactions as $t) {
            $transactionsIndexed[$t['id']] = $t;
        }

        // Injecter date_creation dans chaque livraison si possible
        foreach ($livraisons as &$livraison) {
            if (isset($livraison['transaction']['id']) && isset($transactionsIndexed[$livraison['transaction']['id']])) {
                $livraison['transaction']['date_creation'] = $transactionsIndexed[$livraison['transaction']['id']]['date_creation'];
            } else {
                $livraison['transaction']['date_creation'] = null;
            }
        }
        unset($livraison);



        // Récup fournisseurs

        // Pour chaque transaction, récupérer ses fournisseurs via /api/transactions/{id}
        foreach ($transactions as &$transaction) {
            try {
                $responseFournisseurs = $httpClient->request('GET', 'http://api:8000/api/transactions/' . $transaction['id'], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                ]);

                if ($responseFournisseurs->getStatusCode() === 200) {
                    $data = $responseFournisseurs->toArray();
                    $transaction['fournisseurs'] = $data['fournisseurs'] ?? [];
                } else {
                    $transaction['fournisseurs'] = [];
                }
            } catch (\Exception $e) {
                $transaction['fournisseurs'] = [];
            }
        }
        unset($transaction);

        // Indexer à nouveau pour injection dans livraisons
        $transactionsIndexed = [];
        foreach ($transactions as $t) {
            $transactionsIndexed[$t['id']] = $t;
        }

        // Injecter date_creation et fournisseurs dans chaque livraison
        foreach ($livraisons as &$livraison) {
            if (isset($livraison['transaction']['id']) && isset($transactionsIndexed[$livraison['transaction']['id']])) {
                $livraison['transaction']['date_creation'] = $transactionsIndexed[$livraison['transaction']['id']]['date_creation'];
                $livraison['transaction']['fournisseurs'] = $transactionsIndexed[$livraison['transaction']['id']]['fournisseurs'] ?? [];
            } else {
                $livraison['transaction']['date_creation'] = null;
                $livraison['transaction']['fournisseurs'] = [];
            }
        }
        unset($livraison);



        // Index transactions par id (tu peux adapter cette partie si besoin)
        $transactionsIndexed = [];
        foreach ($transactions as $t) {
            $transactionsIndexed[$t['id']] = $t;
        }

        // Injecter date_creation dans chaque livraison si possible
        foreach ($livraisons as &$livraison) {
            if (isset($livraison['transaction']['id']) && isset($transactionsIndexed[$livraison['transaction']['id']])) {
                $livraison['transaction']['date_creation'] = $transactionsIndexed[$livraison['transaction']['id']]['date_creation'];
                // Injecter aussi les fournisseurs dans la livraison (si tu veux)
                $livraison['transaction']['fournisseurs'] = $transactionsIndexed[$livraison['transaction']['id']]['fournisseurs'] ?? [];
            } else {
                $livraison['transaction']['date_creation'] = null;
                $livraison['transaction']['fournisseurs'] = [];
            }
        }
        unset($livraison);




        // Récupérer les types de camion depuis l'API
        $typesCamion = [];
        try {
            $response = $httpClient->request('GET', 'http://api:8000/api/vehicules/disponible
s', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $typesCamion = $response->toArray();
            }
        } catch (\Exception $e) {
            // Gérer l'erreur
            $typesCamion = [];
        }



        $entrepot = ['id' => 1, 'nom' => 'Entrepôt Principal', 'localisation' => 'Paris'];
        $typesCamion = [
            ['id' => 1, 'nom' => 'cuve'],
            ['id' => 2, 'nom' => 'palette'],
        ];


        // RÉCUPÉRATION DES ENTREPÔTS

        $entrepots = [];
        try {
            $response = $httpClient->request('GET', 'http://api:8000/api/entrepots', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $entrepots = $response->toArray();
            }
        } catch (\Exception $e) {
            $entrepots = [];
        }


        return $this->render('approvisionneur/fournisseur_collecte.html.twig', [
            'entrepot' => $entrepot,
            'typesCamion' => $typesCamion,
            'livraisons' => $livraisons,
            'message' => $message,
            'entrepots' => $entrepots,

        ]);
    }



    // =========================
    // CLIENT / COLLECTE
    // =========================
    #[Route('/collecte/client', name: 'collecte_client')]
    public function collecteClient(Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $idApprov = $session->get('approvisionneur_id');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $livraisons = [];

        try {
            $response = $httpClient->request('GET', 'http://api:8000/api/livraisons', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $livraisons = $response->toArray();

                // Filtrer uniquement les livraisons PAIN
                $livraisons = array_filter($livraisons, function ($livraison) {
                    return isset($livraison['typeMarchandise']) && $livraison['typeMarchandise'] === 'PAIN';
                });
            } else {
                throw new \Exception("Erreur HTTP, code : " . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            exit('Erreur récupération livraisons : ' . $e->getMessage());
        }

        // **Ajouter cette partie pour récupérer clients et produits**
        try {
            $clientsResponse = $httpClient->request('GET', 'http://api:8000/api/clients', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $clients = $clientsResponse->toArray();

            $produitsResponse = $httpClient->request('GET', 'http://api:8000/api/produits', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $produits = $produitsResponse->toArray();
        } catch (\Exception $e) {
            $clients = [];
            $produits = [];
        }

        return $this->render('approvisionneur/collecte_client.html.twig', [
            'livraisons' => $livraisons,
            'clients' => $clients,
            'produits' => $produits,
        ]);
    }







    #[Route('/collecte-client', name: 'collecte_client_form', methods: ['GET'])]
    public function collecteClientForm(Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('backoffice_token');
        $idApprov = $session->get('approvisionneur_id');

        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        try {
            $clientsResponse = $httpClient->request('GET', 'http://api:8000/api/clients', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $clients = $clientsResponse->toArray();

            $produitsResponse = $httpClient->request('GET', 'http://api:8000/api/produits', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $produits = $produitsResponse->toArray();
        } catch (\Exception $e) {
            // En cas d'erreur API, tu peux logguer ou afficher un message
            $clients = [];
            $produits = [];
        }

        return $this->render('approvisionneur/collecte_client.html.twig', [
            'clients' => $clients,
            'produits' => $produits,
        ]);
    }


    #[Route('/collecte-client/saisie', name: 'collecte_client_saisie', methods: ['POST'])]
    public function collecteClientSaisie(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        HttpClientInterface $httpClient
    ): Response {
        // Vérification du token CSRF
        $submittedToken = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('collecte_client_saisie', $submittedToken))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $session = $request->getSession();
        $token = $session->get('backoffice_token');

        // Récupérer clients et produits depuis l'API (pour re-afficher en cas d'erreur)
        try {
            $clientsResponse = $httpClient->request('GET', 'http://api:8000/api/clients', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $clients = $clientsResponse->toArray();

            $produitsResponse = $httpClient->request('GET', 'http://api:8000/api/produits', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
            $produits = $produitsResponse->toArray();
        } catch (\Exception $e) {
            $clients = [];
            $produits = [];
        }

        // Récupération des données du formulaire
        $client = trim($request->request->get('client'));
        $produit = trim($request->request->get('produit'));
        $dateCommande = $request->request->get('dateCommande');
        $dateLivraison = $request->request->get('dateLivraison');

        // Validation basique
        if (empty($client) || empty($produit) || empty($dateCommande) || empty($dateLivraison)) {
            $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');
            return $this->render('approvisionneur/collecte_client.html.twig', [
                'clients' => $clients,
                'produits' => $produits,
            ]);
        }

        try {
            $dateCommandeObj = new \DateTime($dateCommande);
            $dateLivraisonObj = new \DateTime($dateLivraison);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Les dates saisies ne sont pas valides.');
            return $this->render('approvisionneur/collecte_client.html.twig', [
                'clients' => $clients,
                'produits' => $produits,
            ]);
        }

        if ($dateLivraisonObj < $dateCommandeObj) {
            $this->addFlash('error', 'La date de livraison ne peut pas être antérieure à la date de commande.');
            return $this->render('approvisionneur/collecte_client.html.twig', [
                'clients' => $clients,
                'produits' => $produits,
            ]);
        }

        // Gestion du fichier facture (optionnel)
        $factureFile = $request->files->get('facture');
        $newFilename = null;
        if ($factureFile) {
            if ($factureFile->getClientOriginalExtension() !== 'pdf') {
                $this->addFlash('error', 'La facture doit être un fichier PDF.');
                return $this->render('approvisionneur/collecte_client.html.twig', [
                    'clients' => $clients,
                    'produits' => $produits,
                ]);
            }

            $newFilename = uniqid() . '.' . $factureFile->getClientOriginalExtension();
            try {
                $factureFile->move($this->getParameter('facture_directory'), $newFilename);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload de la facture.');
                return $this->render('approvisionneur/collecte_client.html.twig', [
                    'clients' => $clients,
                    'produits' => $produits,
                ]);
            }
        }

        // TODO: Enregistrement en base de données via un service ou repository
        // Exemple: $this->collecteClientRepository->save($client, $produit, $dateCommandeObj, $dateLivraisonObj, $newFilename);

        $this->addFlash('success', 'Livraison client enregistrée avec succès !');

        // Redirection vers la page du formulaire (GET) pour afficher le formulaire vide
        return $this->redirectToRoute('collecte_client_form');
    }
}
