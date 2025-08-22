<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LogoutController extends AbstractController
{
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function logout(
        Request $request,
        RefreshTokenManagerInterface $refreshTokenManager,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Récupérer l'utilisateur authentifié
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non authentifié'], 401);
        }

        // Récupérer le refresh token depuis le body de la requête
        $data = json_decode($request->getContent(), true);
        $refreshTokenString = $data['refresh_token'] ?? null;

        $deletedTokens = 0;

        // Si un refresh token spécifique est fourni, le supprimer
        if ($refreshTokenString) {
            $refreshToken = $refreshTokenManager->get($refreshTokenString);

            if ($refreshToken && $refreshToken->getUsername() === $user->getUserIdentifier()) {
                $refreshTokenManager->delete($refreshToken);
                ++$deletedTokens;
            }
        } else {
            // Sinon, supprimer tous les refresh tokens de l'utilisateur
            $deletedTokens = $this->revokeAllUserTokens($user, $entityManager);
        }

        return new JsonResponse([
            'message' => 'Déconnexion réussie',
            'tokens_deleted' => $deletedTokens,
        ], 200);
    }

    /**
     * Révoque tous les tokens de refresh d'un utilisateur.
     */
    private function revokeAllUserTokens($user, EntityManagerInterface $entityManager): int
    {
        $repository = $entityManager->getRepository(RefreshToken::class);

        $tokens = $repository->findBy([
            'username' => $user->getUserIdentifier(),
        ]);

        $count = count($tokens);

        foreach ($tokens as $token) {
            $entityManager->remove($token);
        }

        $entityManager->flush();

        return $count;
    }
}
