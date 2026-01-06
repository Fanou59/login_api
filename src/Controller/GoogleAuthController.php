<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Google_Client;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GoogleAuthController extends AbstractController
{
    #[Route('/api/login/google', name: 'api_login_google', methods: ['POST'])]
    public function googleLogin(
        Request $request,
        EntityManagerInterface $em,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $idToken = $data['idToken'] ?? null;

        if (!$idToken) {
            return new JsonResponse(['error' => 'Token manquant'], 400);
        }

        $client = new Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
        $payload = $client->verifyIdToken($idToken);

        if ($payload) {
            $email = $payload['email'];

            // On cherche l'utilisateur par email
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                // S'il n'existe pas, on le crée (Inscription automatique via Google)
                $user = new User();
                $user->setEmail($email);
                $user->setFirstname($payload['given_name'] ?? '');
                $user->setLastname($payload['family_name'] ?? '');
                // On met un mot de passe aléatoire inutilisable car l'auth se fait via Google
                $user->setPassword(bin2hex(random_bytes(20)));

                $em->persist($user);
                $em->flush();
            }

            // On génère le même type de JWT que ton login classique
            $token = $jwtManager->create($user);

            return new JsonResponse([
                'token' => $token,
                'user' => [
                    'email' => $user->getEmail(),
                    'firstname' => $user->getFirstname(),
                ]
            ]);
        }

        return new JsonResponse(['error' => 'Authentification Google échouée'], 401);
    }
}
