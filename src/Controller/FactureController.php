<?php

namespace App\Controller;

use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FactureController extends AbstractController
{
    private Pdf $pdf;
    private HttpClientInterface $httpClient;

    public function __construct(Pdf $pdf, HttpClientInterface $httpClient)
    {
        $this->pdf = $pdf;
        $this->httpClient = $httpClient;
    }

    #[Route('/client/facture/generate/{id}', name: 'client_facture_generate')]
    public function generatePdf(int $id, Request $request): Response
    {
        $session = $request->getSession();
        $token = $session->get('client_token');
        $clientUser = $session->get('client_user');

        if (!$token || !$clientUser || !isset($clientUser['id'])) {
            return $this->redirectToRoute('app_login_client');
        }

        $response = $this->httpClient->request('GET', "http://api:8000/api/transactions/commandes/{$clientUser['id']}", [
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);

        if ($response->getStatusCode() !== 200) {
            throw $this->createNotFoundException('Impossible de récupérer la facture.');
        }

        $commandes = $response->toArray();

        $commande = null;
        foreach ($commandes as $cmd) {
            if (
                ($cmd['id'] ?? null) === $id &&
                ($cmd['typeTransaction'] ?? '') === 'Commande' &&
                ($cmd['statutPaiement'] ?? '') === 'Payé'
            ) {
                $commande = $cmd;
                break;
            }
        }

        if (!$commande) {
            throw $this->createNotFoundException('Facture non trouvée ou paiement non validé.');
        }

        $facture = [
            'numero' => 'F' . str_pad($commande['id'], 3, '0', STR_PAD_LEFT),
            'date' => new \DateTime($commande['dateCreation']),
            'mode_paiement' => $commande['mode_paiement'] ?? ($commande['modePaiement'] ?? 'N/A'),
            'date_paiement' => isset($commande['datePaiement']) ? new \DateTime($commande['datePaiement']) : null,
            'statut_paiement' => $commande['statutPaiement'],
            'montant_ttc' => $commande['montantTotal'],
            'produits' => $commande['estContenus'] ?? [],
        ];

        $client = [
            'nom' => $clientUser['nom'] ?? '',
            'prenom' => $clientUser['prenom'] ?? '',
            'email' => $clientUser['email'] ?? '',
        ];

        $html = $this->renderView('client/facturePDF.html.twig', [
            'facture' => $facture,
            'client' => $client
        ]);


        $this->pdf->setTimeout(120);
        $pdfContent = $this->pdf->getOutputFromHtml($html);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"facture_{$facture['numero']}.pdf\""
        ]);
    }
}
