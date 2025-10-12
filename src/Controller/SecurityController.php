<?php

// src/Controller/SecurityController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;


// _________________________________________CSRF (OK)_____________________________________________



class SecurityController extends AbstractController
{
    #[Route('/login-backoffice', name: 'app_login_backoffice')]
    public function loginBackoffice(): Response
    {
        return $this->render('security/loginBackoffice.html.twig');
    }


    #[Route('/store-token', name: 'store_token', methods: ['POST'])]
    public function storeToken(Request $request, HttpClientInterface $http): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['token'])) {
            return $this->json(['error' => 'Token manquant'], 400);
        }

        $token = $data['token'];
        $decoded = json_decode(base64_decode(explode('.', $token)[1]), true);
        $roles = $decoded['roles'] ?? [];

        $session = $request->getSession();

        if (in_array('ROLE_CLIENT', $roles)) {
            $session->set('client_token', $token);
        } elseif (in_array('ROLE_APPROVISIONNEUR', $roles)) {
            $session->set('backoffice_token', $token);

            try {
                // Étape 1 : Récupérer l'utilisateur connecté
                $apiResponse = $http->request('GET', 'http://api:8000/api/me', [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $userInfo = $apiResponse->toArray();
                $userId = $userInfo['id'] ?? null;

                if (!$userId) {
                    return $this->json(['error' => 'ID utilisateur introuvable'], 400);
                }


                // Étape 2 : Récupérer l’approvisionneur lié à cet utilisateur
                $response = $http->request('GET', 'http://api:8000/api/approvisionneurs', [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'query' => ['user' => $userId]
                ]);
                $approvisionneurs = $response->toArray();

                if (count($approvisionneurs) === 0) {
                    return $this->json(['error' => 'Aucun approvisionneur trouvé pour cet utilisateur'], 400);
                }

                $approvisionneur = $approvisionneurs[0];
                $session->set('approvisionneur_id', $approvisionneur['id']);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Erreur lors de la récupération des données approvisionneur',
                    'message' => $e->getMessage()
                ], 500);
            }
        } elseif (in_array('ROLE_COMMERCIAL', $roles)) {
            $session->set('backoffice_token', $token);

            try {
                $apiResponse = $http->request('GET', 'http://api:8000/api/me', [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $userInfo = $apiResponse->toArray();
                $session->set('commercial_id', $userInfo['id'] ?? null);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Erreur commercial', 'message' => $e->getMessage()], 500);
            }
        } elseif (in_array('ROLE_OPERATEUR_MAINTENANCE', $roles)) {
            $session->set('backoffice_token', $token);

            try {
                $apiResponse = $http->request('GET', 'http://api:8000/api/me', [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $userInfo = $apiResponse->toArray();
                $session->set('maintenance_id', $userInfo['id'] ?? null);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Erreur maintenance', 'message' => $e->getMessage()], 500);
            }
        } else {
            return $this->json(['error' => 'Rôle non reconnu'], 403);
        }

        return $this->json(['success' => true]);
    }


    // ________________________________________________________________________________________


    #[Route('/login-client', name: 'app_login_client', methods: ['GET', 'POST'])]
    public function loginClient(): Response
    {
        return $this->render('security/loginClient.html.twig');
    }



    #[Route('/client/store-session', name: 'client_store_session', methods: ['POST'])]
    public function storeSession(Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // dump($data);
        if (!isset($data['token']) || !isset($data['user'])) {
            return new JsonResponse(['error' => 'Données manquantes'], 400);
        }

        $session->set('client_token', $data['token']);
        $session->set('client_user', $data['user']);
        $session->save();

        return new JsonResponse(['success' => true]);
    }



    // ________________________________________________________________________________________

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // ________________________________________________________________________________________

    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        HttpClientInterface $httpClient
    ): Response {
        $csrfToken = $csrfTokenManager->getToken('reset_password')->getValue();

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('reset_password', $submittedToken))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }

            $email = trim($request->request->get('email'));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Adresse email invalide.');
                return $this->redirectToRoute('app_forgot_password_request');
            }

            try {
                $response = $httpClient->request('POST', 'http://api:8000/api/forgot-password', [
                    'json' => ['email' => $email],
                ]);

                if ($response->getStatusCode() === 200) {
                    $this->addFlash('success', 'Un lien de réinitialisation a été envoyé.');
                } else {
                    $this->addFlash('danger', 'Erreur lors de l’envoi du mail.');
                }
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Impossible de contacter l’API.');
            }

            return $this->redirectToRoute('app_login_client');
        }

        return $this->render('reset_password/request.html.twig', [
            'csrf_token' => $csrfToken,
        ]);
    }


    // ________________________________________________________________________________________


    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, HttpClientInterface $client, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $csrfToken = $csrfTokenManager->getToken('register_form')->getValue();

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_csrf_token');
            if (!$csrfTokenManager->isTokenValid(new CsrfToken('register_form', $submittedToken))) {
                throw $this->createAccessDeniedException('Token CSRF invalide.');
            }

            $data = [
                'username' => $request->request->get('username'),
                'password' => $request->request->get('password'),
                'nom' => $request->request->get('nom'),
                'prenom' => $request->request->get('prenom'),
                'telephone' => $request->request->get('telephone'),
                'nomEntreprise' => $request->request->get('nomEntreprise'),
                'typeClient' => $request->request->get('typeClient'),
                'siret' => $request->request->get('siret'),
            ];

            $response = $client->request('POST', 'http://api:8000/api/register', [
                'json' => $data,
            ]);

            if ($response->getStatusCode() === 201) {
                $this->addFlash('success', 'Inscription réussie');
                return $this->redirectToRoute('app_login_client');
            }

            // dd($response->getStatusCode(), $response->getContent(false));

            try {
                $body = $response->toArray(false);
                $error = $body['error'] ?? 'Erreur lors de l’inscription';
            } catch (\Symfony\Component\HttpClient\Exception\JsonException $e) {
                $error = 'Réponse invalide de l’API: ' . $response->getContent(false);
            }


            return $this->render('security/register.html.twig', [
                'error' => $error,
                'old' => $data,
                'csrf_token' => $csrfToken,
            ]);
        }

        return $this->render('security/register.html.twig', [
            'csrf_token' => $csrfToken,
        ]);
    }
}
