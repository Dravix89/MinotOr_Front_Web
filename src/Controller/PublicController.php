<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PublicController extends AbstractController
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    // ___________________________________________accueil :
    #[Route('/', name: 'public_accueil')]
    public function accueil(): Response
    {
        return $this->render('public/accueil.html.twig');
    }

    // ___________________________________________produits :
    #[Route('/produits', name: 'public_produits')]
    public function produits(): Response
    {
        $response = $this->client->request('GET', 'http://api:8000/api/produits', [
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw $this->createNotFoundException('Impossible de récupérer les produits');
        }

        $produits = $response->toArray();
        // dd($produits); //TEST POUR VOIR TABLAU !

        return $this->render('public/produits.html.twig', [
            'produits' => $produits,
        ]);
    }

    // ___________________________________________a propos :
    #[Route('/a-propos', name: 'public_apropos')]
    public function apropos(): Response
    {
        return $this->render('public/apropos.html.twig');
    }

    // ___________________________________________contact (CSRF (OK)) :
    #[Route('/contact', name: 'public_contact')]
    public function contact(): Response
    {
        return $this->render('public/contact.html.twig');
    }

    #[Route('/contact', name: 'public_contact_submit', methods: ['POST'])]
    public function contactSubmit(Request $request): Response
    {
        $submittedToken = $request->request->get('_csrf_token');

        if (!$this->isCsrfTokenValid('contact_form', $submittedToken)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $name = trim($request->request->get('name'));
        $email = trim($request->request->get('email'));
        $message = trim($request->request->get('message'));

        // Validation simple
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Le nom est obligatoire.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Un email valide est obligatoire.';
        }
        if (empty($message)) {
            $errors[] = 'Le message ne peut pas être vide.';
        }

        if (!empty($errors)) {
            // On ré-affiche le formulaire avec les erreurs
            return $this->render('public/contact.html.twig', [
                'errors' => $errors,
                'old' => ['name' => $name, 'email' => $email, 'message' => $message],
            ]);
        }

        // Un traitement : envoi email, stockage, etc.
        // Ex: $this->mailer->send(...);

        // Ajoute un message flash de confirmation
        $this->addFlash('success', 'Votre message a bien été envoyé. Merci !');

        // Redirige vers la page contact (GET) pour éviter le repost du formulaire
        return $this->redirectToRoute('public_contact');
    }
}
