<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MaintenanceController extends AbstractController
{
    private HttpClientInterface $client;
    private string $apiUrl = 'http://api:8000/api/vehicules';

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    // Récupérer tous les véhicules et calculer leur statut
    private function fetchCamions(string $token): array
    {
        $camions = [];

        try {
            $response = $this->client->request('GET', $this->apiUrl, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            if ($response->getStatusCode() === 200) {
                $vehicules = $response->toArray();

                foreach ($vehicules as $v) {
                    // On récupère les livraisons
                    $livraisons = [];
                    try {
                        $livraisonsResponse = $this->client->request('GET', "{$this->apiUrl}/{$v['id']}/livraisons", [
                            'headers' => ['Authorization' => 'Bearer ' . $token],
                        ]);
                        if ($livraisonsResponse->getStatusCode() === 200) {
                            $livraisons = $livraisonsResponse->toArray();
                        }
                    } catch (\Exception $e) {
                        $livraisons = [];
                    }

                    try {
                        $dernierEntretienResponse = $this->client->request('GET', "{$this->apiUrl}/{$v['id']}/entretiens?orderBy=dateMaintenance&order=desc&limit=1", [
                            'headers' => ['Authorization' => 'Bearer ' . $token],
                        ]);
                        if ($dernierEntretienResponse->getStatusCode() === 200) {
                            $dernierEntretienData = $dernierEntretienResponse->toArray();
                            if (!empty($dernierEntretienData)) {
                                $dernierNettoyage = new \DateTime($dernierEntretienData[0]['dateMaintenance']);
                            } else {
                                $dernierNettoyage = new \DateTime('-30 days');
                            }
                        } else {
                            $dernierNettoyage = new \DateTime('-30 days');
                        }
                    } catch (\Exception $e) {
                        $dernierNettoyage = new \DateTime('-30 days');
                    }

                    // Calcul du nombre de chargements depuis le dernier nettoyage
                    $nbChargements = 0;
                    foreach ($livraisons as $livraison) {
                        if (isset($livraison['dateLivraison'])) {
                            $dateLivraison = new \DateTime($livraison['dateLivraison']);
                            if ($dateLivraison > $dernierNettoyage) {
                                $nbChargements++;
                            }
                        }
                    }
                    //  $status = 'à nettoyer'; // FORCÉ pour test !!!
                    if ($nbChargements >= 10) {
                        $status = 'à nettoyer';
                    } elseif (isset($v['disponible']) && $v['disponible'] == 1) {
                        $status = 'disponible';
                    } else {
                        $status = 'en maintenance';
                    }
                    // if ($nbChargements > 10) {
                    //     $status = 'en maintenance';
                    // } elseif (isset($v['disponible']) && $v['disponible'] == 1) {
                    //     $status = 'disponible';
                    // } else {
                    //     $status = 'à nettoyer';
                    // }

                    $camions[] = [
                        'id' => $v['id'],
                        'nom' => $v['immatriculationVehicule'],
                        'dernierNettoyage' => $dernierNettoyage,
                        'nbChargementsDepuisDernierNettoyage' => $nbChargements,
                        'status' => $status,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur API véhicules : ' . $e->getMessage());
        }

        return $camions;
    }


    // Récupérer véhicules disponibles directement depuis l'API dédiée
    private function fetchDisponibles(string $token): array
    {
        $camionsDisponibles = [];

        try {
            $response = $this->client->request('GET', "{$this->apiUrl}/disponible", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            if ($response->getStatusCode() === 200) {
                $vehicules = $response->toArray();
                foreach ($vehicules as $v) {
                    $camionsDisponibles[] = [
                        'id' => $v['id'],
                        'nom' => $v['immatriculationVehicule'],
                        'dernierNettoyage' => new \DateTime('-30 days'), // placeholder
                        'nbChargementsDepuisDernierNettoyage' => 0,
                        'status' => 'disponible',
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur API véhicules disponibles : ' . $e->getMessage());
        }
        return $camionsDisponibles;
    }


    private function fetchHistorique(string $token): array
    {
        $historique = [];

        try {
            $response = $this->client->request('GET', $this->apiUrl, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);

            if ($response->getStatusCode() === 200) {
                $vehicules = $response->toArray();

                foreach ($vehicules as $v) {
                    // Ici on simule la date dernier nettoyage (entre 15 et 45 jours)
                    $joursDepuisNettoyage = rand(15, 45);
                    $dernierNettoyage = new \DateTime("-{$joursDepuisNettoyage} days");

                    $historique[] = [
                        'id' => $v['id'],
                        'nom' => $v['immatriculationVehicule'],
                        'dernierNettoyage' => $dernierNettoyage,
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur API historique : ' . $e->getMessage());
        }
        return $historique;
    }


    #[Route('/dashboard/maintenance', name: 'maintenance_index')]
    public function index(Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');
        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $camions = $this->fetchCamions($token);

        $countANettoyer = count(array_filter($camions, fn($c) => $c['status'] === 'à nettoyer'));
        $countEnMaintenance = count(array_filter($camions, fn($c) => $c['status'] === 'en maintenance'));

        return $this->render('maintenance/index.html.twig', [
            'count_a_nettoyer' => $countANettoyer,
            'count_en_maintenance' => $countEnMaintenance,
        ]);
    }

    #[Route('/maintenance/disponibles', name: 'maintenance_disponibles')]
    public function disponibles(Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');
        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $camionsDisponibles = $this->fetchDisponibles($token);

        return $this->render('maintenance/disponibles.html.twig', [
            'camions' => $camionsDisponibles,
        ]);
    }

    #[Route('/maintenance/camions', name: 'maintenance_camions')]
    public function camions(Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');
        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $camions = $this->fetchCamions($token);
        // dump($camions); // TEST Recup

        $camionsANettoyer = array_filter($camions, fn($c) => $c['status'] === 'à nettoyer');

        return $this->render('maintenance/camions.html.twig', [
            'camions' => $camionsANettoyer,
        ]);
    }

    #[Route('/maintenance/en-cours', name: 'maintenance_en_cours')]
    public function enCours(Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');
        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $camions = $this->fetchCamions($token);
        $camionsEnMaintenance = array_filter($camions, fn($c) => $c['status'] === 'en maintenance');

        return $this->render('maintenance/en_cours.html.twig', [
            'camions' => $camionsEnMaintenance,
        ]);
    }

    #[Route('/maintenance/historique', name: 'maintenance_historique')]
    public function historique(Request $request): Response
    {
        $token = $request->getSession()->get('backoffice_token');
        if (!$token) {
            return $this->redirectToRoute('app_login_backoffice');
        }

        $historique = $this->fetchHistorique($token);

        return $this->render('maintenance/historique.html.twig', [
            'historique' => $historique,
        ]);
    }
}
