<?php

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

// _________________________________________CSRF (OK)_____________________________________________

class ClientController extends AbstractController
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/client/accueil', name: 'client_accueil', methods: ['GET'])]
    public function accueil(): Response
    {
        $response = $this->client->request('GET', 'http://api/api/produits', [
            'timeout' => 10,
        ]);

        $produits = $response->toArray(); // Pas de conversion

        // dd($produits); // Afficher le contenu pour mes produits

        return $this->render('client/accueil.html.twig', [
            'produits' => $produits,
        ]);
    }


    #[Route('/client/produit/{id}', name: 'client_produit_detail', methods: ['GET'])]
    public function produit(int $id): Response
    {
        $response = $this->client->request('GET', 'http://api:8000/api/produits');
        if ($response->getStatusCode() !== 200) {
            throw $this->createNotFoundException('Impossible de récupérer les produits');
        }

        $produits = $response->toArray();

        $produit = null;
        foreach ($produits as $p) {
            if ($p['id'] == $id) {
                $produit = $p;
                break;
            }
        }

        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        return $this->render('client/produit.html.twig', [
            'produit' => $produit
        ]);
    }

    // _________________________________________________________________________________________


    #[Route('/client/compte', name: 'client_compte', methods: ['GET'])]
    public function compte(): Response
    {
        return $this->render('client/compte.html.twig');
    }

    // _________________________________________________________________________________________

    #[Route('/client/commandes', name: 'client_commandes', methods: ['GET'])]
    public function commandes(Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('client_token'); // ✔ cohérent avec le reste de tes contrôleurs
        $clientUser = $session->get('client_user');

        if (!$token || !$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        $idClient = $clientUser['id'];

        $response = $httpClient->request('GET', "http://api:8000/api/transactions/commandes/$idClient", [
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);

        $commandes = $response->toArray();

        return $this->render('client/commandes.html.twig', [
            'commandes' => $commandes
        ]);
    }

    // --------------------------------------------

    #[Route('/client/commande/{id}', name: 'client_commande_detail', methods: ['GET'])]
    public function commandeDetail(int $id, Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('client_token');
        $clientUser = $session->get('client_user');

        if (!$token || !$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        $response = $httpClient->request('GET', "http://api:8000/api/transactions/$id", [
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);

        $commandeAPI = $response->toArray();

        // dd($commandeAPI);


        // ✅ Vérifie que c’est bien une commande
        if (($commandeAPI['typeTransaction'] ?? '') !== 'Commande') {
            throw new NotFoundHttpException('Ce document n’est pas une commande.');
        }

        // ✅ Vérifie que la commande appartient au bon client
        if (($commandeAPI['client']['id'] ?? null) !== $clientUser['id']) {
            throw new AccessDeniedHttpException('Cette commande ne vous appartient pas.');
        }

        return $this->render('client/commande_detail.html.twig', [
            'commande' => $commandeAPI
        ]);
    }


    // ✅ Résumé rapide :
    // Route	             OK sécurité ?	 Token vérifié ?	Client checké ?	            Type checké ?
    // /client/commandes	 ✅	           ✅ client_token	✅ client_user	           ❌ (pas besoin)
    // /client/commande/{id} ✅ (corrigée)  ✅	            ✅ (vérifie t’appartient)   ✅ (type = Commande)


    // _________________________________________________________________________________________

    #[Route('/client/devis', name: 'client_devis', methods: ['GET'])]
    public function devis(SessionInterface $session): Response
    {
        $clientUser = $session->get('client_user');

        if (!$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        $clientId = $clientUser['id'];
        $tokenJWT = $session->get('client_token');

        if (!$tokenJWT) {
            return $this->redirectToRoute('app_login_client');
        }

        $response = $this->client->request('GET', 'http://api:8000/api/transactions/devis/' . $clientId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $tokenJWT,
            ],
        ]);

        $devisListRaw = $response->toArray();

        // Transformer les données API en format attendu par twig
        $devisList = [];
        foreach ($devisListRaw as $devis) {
            if (($devis['typeTransaction'] ?? '') !== 'Devis') {
                continue; // Ignore les autres types
            }
            $devisList[] = [
                'id' => $devis['id'],
                'numDevis' => 'D' . str_pad($devis['id'], 3, '0', STR_PAD_LEFT),
                'status' => match ($devis['choixClient'] ?? '') {
                    'Accepter' => 'validé',
                    'Refuser' => 'rejeté',
                    default => 'en_attente',
                },
                'choix_client' => $devis['choixClient'] ?? null,
                'date' => isset($devis['dateCreation']) && $devis['dateCreation'] ? new \DateTime($devis['dateCreation']) : null,
            ];
        }

        // Récupérer produits en cours depuis session (comme avant)
        $responseProduits = $this->client->request('GET', 'http://api:8000/api/produits');
        $produitsDisponiblesList = $responseProduits->toArray();
        $produitsDisponibles = [];
        foreach ($produitsDisponiblesList as $produit) {
            $produitsDisponibles[$produit['id']] = $produit;
        }
        $devisProduitsIds = $session->get('devis_produits', []);
        $produitsEnCours = array_intersect_key($produitsDisponibles, array_flip($devisProduitsIds));

        // 🔧 Injecter manuellement l'id dans chaque produit pour l'affichage Twig
        foreach ($produitsEnCours as $id => &$produit) {
            $produit['id'] = $id;
        }
        unset($produit);

        return $this->render('client/devis.html.twig', [
            'devisList' => $devisList,
            'produitsEnCours' => $produitsEnCours,
        ]);
    }

    // --------------------------------------------

    #[Route('/client/devis/{id}', name: 'client_devis_detail', methods: ['GET', 'POST'])]
    public function devisDetail(
        Request $request,
        int $id,
        CsrfTokenManagerInterface $csrfTokenManager,
        SessionInterface $session
    ): Response {
        $tokenJWT = $session->get('client_token');
        $clientUser = $session->get('client_user');

        if (!$tokenJWT || !$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        // 1. Appel API pour récupérer la transaction (devis) avec les détails produits inclus
        $response = $this->client->request('GET', 'http://api:8000/api/transactions/' . $id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $tokenJWT,
            ],
        ]);

        $devisAPI = $response->toArray();

        //TEST :
        // dump($devisAPI['estContenus']);
        // exit;

        // 2. Vérifie que c'est bien un devis
        if (($devisAPI['typeTransaction'] ?? '') !== 'Devis') {
            throw new NotFoundHttpException('Ce document n\'est pas un devis.');
        }

        // 3. Vérifie que ce devis appartient au client connecté
        if (($devisAPI['client']['id'] ?? null) !== $clientUser['id']) {
            throw new AccessDeniedHttpException('Ce devis ne vous appartient pas.');
        }


        // 4. Mapping des données pour la vue
        $devis = [
            'id' => $devisAPI['id'],
            'numDevis' => 'D' . str_pad($devisAPI['id'], 3, '0', STR_PAD_LEFT),
            'status' => match ($devisAPI['choixClient'] ?? '') {
                'Accepter' => 'Accepter',
                'Refuser' => 'Refuser',
                default => 'en_attente',
            },
            'choix_client' => $devisAPI['choixClient'] ?? null,
            'date' => isset($devisAPI['dateCreation']) ? new \DateTime($devisAPI['dateCreation']) : null,
            'total' => $devisAPI['montantTotal'] ?? null,
            'paiement' => $devisAPI['modePaiement'] ?? null,
            'etat_paiement' => $devisAPI['statutPaiement'] ?? null,
            'commentaire' => $devisAPI['commentaireClient'] ?? null,
            // récupérer directement la liste des produits (avec détails) via l’API
            'lignes' => array_map(function ($contenu) {
                return [
                    'produit' => $contenu['produit'],
                    'quantiteProduit' => $contenu['quantiteProduit'] ?? 0,  // note la casse ici
                    'remiseProduit' => $contenu['remiseProduit'] ?? 0,
                ];
            }, $devisAPI['estContenus'] ?? []),

        ];

        // Calcul total HT (obligatoire pour twig)
        $totalHT = 0;
        foreach ($devis['lignes'] as $ligne) {
            $produit = $ligne['produit'];
            $marge = $produit['marge'] ?? 0.2;
            $prix_unitaire = $produit['prixProduit'] ?? 0;  // camelCase, pas underscore
            $quantite = $ligne['quantiteProduit'] ?? 0;
            $ristourne = $ligne['remiseProduit'] ?? 0;


            $prix_avec_marge = $prix_unitaire * (1 + $marge);
            $prix_apres_ristourne = $prix_avec_marge * (1 - $ristourne / 100);

            $totalHT += $quantite * $prix_apres_ristourne;
        }
        $devis['total_ht'] = $totalHT;


        // ______________TEST :
        // if (!isset($devisAPI['produits'])) {
        //     dump('Pas de clé produits dans $devisAPI');
        //     dump($devisAPI);
        //     exit;
        // }

        // dump($devisAPI['produits']);
        // dump($devis['lignes']);
        // exit;

        // 6. Gestion POST commentaire
        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('devis_commentaire', $submittedToken))) {
                throw new AccessDeniedHttpException('Token CSRF invalide.');
            }

            // Ici, il faudrait appeler l’API pour sauvegarder le commentaire côté back,
            // mais si c’est pas encore implémenté, tu peux juste ajouter un flash message
            // et recharger la page.

            $this->addFlash('success', 'Commentaire envoyé (à implémenter côté API)');
            return $this->redirectToRoute('client_devis_detail', ['id' => $id]);
        }

        return $this->render('client/devis_detail.html.twig', [
            'devis' => $devis
        ]);
    }


    // --------------------------------------------A VOIR COTE COMMERCIAL AUSSI SI CEST BON ???

    #[Route('/client/devis/demande', name: 'client_devis_demande', methods: ['POST'])]
    public function demanderDevis(Request $request, SessionInterface $session, CsrfTokenManagerInterface $csrfTokenManager): RedirectResponse
    {

        // Vérifie que le token CSRF envoyé correspond à celui attendu pour sécuriser le formulaire contre les soumissions frauduleuses (Contre les attaques CSRF).
        $submittedToken = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('demande_devis', $submittedToken))) {
            throw new AccessDeniedHttpException('Token CSRF invalide.');
        }

        $objet = $request->request->get('objet');
        $description = $request->request->get('description');

        // ✅ Récupérer les IDs des produits envoyés dans le formulaire
        $produitsData = $request->request->all('produits');
        if (!is_array($produitsData)) {
            $produitsData = [];
        }


        foreach ($produitsData as $produitId => $data) {
            $quantite = $data['quantite'] ?? 1;
            // ici $quantite est bien la quantité modifiée par l'utilisateur

            // Exemple si tu veux récupérer le produit depuis la base :
            // $produit = $this->produitRepository->find($produitId);

            // Ensuite tu peux créer les lignes du devis ou les stocker pour traitement
            // Exemple (à adapter) :
            // $ligne = new LigneDevis();
            // $ligne->setProduit($produit);
            // $ligne->setQuantite($quantite);
            // $devis->addLigne($ligne);
        }

        // ✅ Vider la session
        $session->remove('devis_produits');

        $this->addFlash('success', 'Votre demande de devis a été envoyée avec succès.');

        return $this->redirectToRoute('client_devis');
    }

    // --------------------------------------------

    // /client/ajouter-au-devis/{id} :
    // Page d’accueil produit	client_accueil :
    // Ajouter un produit depuis l'accueil produits

    #[Route('/client/ajouter-au-devis/{id}', name: 'client_ajouter_au_devis', methods: ['POST'])]
    public function ajouterAuDevis(int $id, Request $request, SessionInterface $session, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        // Récupérer le token CSRF envoyé dans le formulaire
        $submittedToken = $request->request->get('_token');

        // Vérifier que le token CSRF est valide pour cette action et ce produit
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('ajouter_au_devis' . $id, $submittedToken))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // Récupérer la liste des produits déjà ajoutés au devis depuis la session
        $devisProduits = $session->get('devis_produits', []);
        // Ajouter le produit au devis seulement s'il n'est pas déjà présent
        if (!in_array($id, $devisProduits)) {
            $devisProduits[] = $id;
        }
        // Mettre à jour la session avec la nouvelle liste de produits
        $session->set('devis_produits', $devisProduits);

        // Ajouter un message flash pour confirmer l'ajout au devis à l'utilisateur
        $this->addFlash('success', "Produit $id ajouté au devis.");

        // Rediriger vers la page d'accueil client après ajout
        return $this->redirectToRoute('client_accueil');
    }

    // --------------------------------------------

    #[Route('/client/retirer-du-devis/{id}', name: 'client_retirer_du_devis', methods: ['POST'])]
    public function retirerDuDevis(int $id, Request $request, SessionInterface $session, CsrfTokenManagerInterface $csrfTokenManager): Response
    {

        // Vérification du token CSRF lié à la suppression sécurisée du produit $id
        $submittedToken = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('retirer_produit_' . $id, $submittedToken))) {
            throw new AccessDeniedHttpException('Token CSRF invalide.');
        }
        $devisProduits = $session->get('devis_produits', []);
        if (($key = array_search($id, $devisProduits)) !== false) {
            unset($devisProduits[$key]);
        }
        $session->set('devis_produits', array_values($devisProduits));

        $this->addFlash('success', "Produit $id retiré du devis.");
        return $this->redirectToRoute('client_devis');
    }

    // --------------------------------------------

    // /client/ajouter-produit-au-devis/{id} :
    // Page des envies	client_envies :
    // Ajouter un produit depuis la wishlist/envies

    #[Route('/client/ajouter-produit-au-devis/{id}', name: 'client_ajouter_devis')]
    public function ajouterProduitAuDevis(int $id, SessionInterface $session): Response
    {
        $devisProduits = $session->get('devis_produits', []);
        if (!in_array($id, $devisProduits)) {
            $devisProduits[] = $id;
        }
        $session->set('devis_produits', $devisProduits);
        $this->addFlash('success', "Produit $id ajouté à la sélection de devis.");
        return $this->redirectToRoute('client_envies');
    }

    // _________________________________________________________________________________________

    #[Route('/client/envies', name: 'client_envies')]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $enviesIds = $session->get('envies', []);

        // ✅ Sécurité en cas de données corrompues
        if (!is_array($enviesIds)) {
            $enviesIds = [];
        }

        // Sécuriser l’appel HTTP (en cas d’échec d’API)
        try {
            $response = $this->client->request('GET', 'http://api:8000/api/produits');
            $produits = $response->toArray();
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de récupérer les produits.');
            $produits = [];
        }


        // Indexer produits par id pour accès rapide
        $produitsDisponibles = [];
        foreach ($produits as $produit) {
            $produitsDisponibles[$produit['id']] = $produit;
        }

        $envies = [];
        foreach ($enviesIds as $id) {
            if (isset($produitsDisponibles[$id])) {
                $envies[] = $produitsDisponibles[$id];
            }
        }

        return $this->render('client/envies.html.twig', [
            'envies' => $envies,
        ]);
    }

    #[Route('/ajouter-envie', name: 'client_ajouter_envie', methods: ['POST'])]
    public function ajouterEnvie(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('ajouter_envie', $csrfToken))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 403);
        }

        $produitId = $data['id'] ?? null;
        if (!$produitId) {
            return new JsonResponse(['success' => false, 'message' => 'ID produit manquant'], 400);
        }

        $response = $this->client->request('GET', 'http://api:8000/api/produits');
        $produits = $response->toArray();

        $produitsValides = array_map(fn($p) => $p['id'], $produits);

        if (!in_array((int)$produitId, $produitsValides, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Produit invalide'], 400);
        }

        $session = $request->getSession();
        $envies = $session->get('envies', []);

        $produitId = (int)$produitId;
        $action = '';

        if (!in_array($produitId, $envies, true)) {
            $envies[] = $produitId;
            $action = 'ajoute';
        } else {
            $envies = array_values(array_filter($envies, fn($id) => (int)$id !== $produitId));
            $action = 'retire';
        }

        $session->set('envies', $envies);

        return new JsonResponse([
            'success' => true,
            'action' => $action,
        ]);
    }




    // _________________________________________________________________________________________

    #[Route('/client/factures', name: 'client_factures', methods: ['GET'])]
    public function factures(Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('client_token');
        $clientUser = $session->get('client_user');

        if (!$token || !$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        $idClient = $clientUser['id'];

        // Requête API authentifiée
        $response = $httpClient->request('GET', "http://api:8000/api/transactions/commandes/$idClient", [
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);

        $factures = [];

        if ($response->getStatusCode() === 200) {
            $commandes = $response->toArray();

            foreach ($commandes as $commande) {
                if (
                    // Vérifie aussi typeTransaction === 'Commande'
                    // Filtre statutPaiement === 'Payé' ajouté.
                    ($commande['typeTransaction'] ?? '') === 'Commande' &&
                    ($commande['statutPaiement'] ?? '') === 'Payé'
                ) {
                    $factures[] = [
                        'numero' => 'F' . str_pad($commande['id'], 3, '0', STR_PAD_LEFT),
                        'date' => new \DateTime($commande['dateCreation']),
                        'montant_ttc' => $commande['montantTotal'],
                        'fichier_pdf' => "facture_{$commande['id']}.pdf",
                    ];
                }
            }
        }

        return $this->render('client/factures.html.twig', ['factures' => $factures]);
    }


    // _________________________________________________________________________________________

    #[Route('/client/commande-cyclique', name: 'client_commande_cyclique', methods: ['GET', 'POST'])]
    public function commandeCyclique(Request $request): Response
    {
        // Appel API pour récupérer les produits
        $response = $this->client->request('GET', 'http://api:8000/api/produits', [
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw $this->createNotFoundException('Impossible de récupérer les produits');
        }

        $produits = $response->toArray();

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if (!$this->isCsrfTokenValid('commande_cyclique', $submittedToken)) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }

            $data = $request->request->all();

            // Traitement ici...

            $this->addFlash('success', 'Commande cyclique enregistrée');
            return $this->redirectToRoute('client_commande_cyclique');
        }

        return $this->render('client/commande_cyclique.html.twig', [
            'produits' => $produits,
        ]);
    }


    // _________________________________________________________________________________________


    #[Route('/client/pain-invendu', name: 'client_pain_invendu', methods: ['GET', 'POST'])]
    public function painInvendu(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Traitement formulaire déclaration pain invendu
            $this->addFlash('success', 'Déclaration enregistrée');
            return $this->redirectToRoute('client_pain_invendu');
        }

        // Exemple de produits à passer à la vue
        $produits = [
            ['id' => 1, 'nom' => 'Pain Invendu']
        ];

        return $this->render('client/pain_invendu.html.twig', [
            'produits' => $produits,
        ]);
    }

    // _____________________________________

    #[Route('/client/pain-invendu/submit', name: 'client_pain_invendu_submit', methods: ['POST'])]
    public function painInvenduSubmit(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        MailerInterface $mailer,
        HttpClientInterface $httpClient
    ): Response {
        $submittedToken = $request->request->get('_token');

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('pain_invendu_submit', $submittedToken))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $date = $request->request->get('date');
        $produit = $request->request->get('produit');
        $quantite = $request->request->get('quantite');
        $commentaire = $request->request->get('commentaire');

        try {
            // Appeler l'API pour récupérer les approvisionneurs
            $response = $httpClient->request('GET', 'http://api:8000/api/approvisionneurs');
            $approvisionneurs = $response->toArray();

            foreach ($approvisionneurs as $approvisionneur) {
                if (!isset($approvisionneur['email'])) {
                    continue; // on ignore les entrées sans email
                }

                // Toutes les données injectées sont échappées avec htmlspecialchars()
                // → Ça empêche l'injection de HTML ou de JavaScript malveillant dans l'email.

                // Le HTML est envoyé par mail, pas affiché dans une page Twig publique
                // → Donc aucun impact direct côté navigateur/client.

                $email = (new Email())
                    ->from('no-reply@tonsite.com')
                    ->to($approvisionneur['email'])
                    ->subject('Déclaration Pain Invendu')
                    ->html("
                    <p>Une nouvelle déclaration de pain invendu a été soumise :</p>
                    <ul>
                        <li><strong>Date :</strong> " . htmlspecialchars($date) . "</li>
                        <li><strong>Produit :</strong> " . htmlspecialchars($produit) . "</li>
                        <li><strong>Quantité :</strong> " . htmlspecialchars($quantite) . " kg</li>
                        <li><strong>Commentaire :</strong> " . nl2br(htmlspecialchars($commentaire)) . "</li>
                    </ul>
                ");

                $mailer->send($email);
            }

            $this->addFlash('success', 'Pain invendu déclaré avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur lors de l\'envoi de la déclaration : ' . $e->getMessage());
        }

        return $this->redirectToRoute('client_pain_invendu');
    }

    // _________________________________________________________________________________________


    #[Route('/client/suivi-livraison', name: 'client_suivi_livraison')]
    public function suiviLivraison(Request $request, HttpClientInterface $httpClient): Response
    {
        $session = $request->getSession();
        $token = $session->get('client_token');
        $clientUser = $session->get('client_user');

        if (!$token || !$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        try {
            $response = $httpClient->request('GET', 'http://api:8000/api/livraisons', [
                'headers' => [
                    'Authorization' => "Bearer $token"
                ]
            ]);
            $livraisons = $response->toArray();
            //  Mon TEST :
            // dd($livraisons);
            $clientId = $clientUser['id'];

            $livraisonsClient = array_filter($livraisons, function ($livraison) use ($clientId) {
                return isset($livraison['transaction']['client']['id']) &&
                    $livraison['transaction']['client']['id'] == $clientId;
            });
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            $this->addFlash('error', 'Erreur lors de la récupération des livraisons : ' . $e->getMessage());
            $livraisonsClient = [];
        }

        //  Mon TEST :
        // dd($livraisonsClient);

        return $this->render('client/suivi_livraison.html.twig', [
            'livraisons' => $livraisonsClient,
        ]);
    }
}
