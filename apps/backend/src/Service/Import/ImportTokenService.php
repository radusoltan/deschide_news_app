<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Repository\UserRepository;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;

/**
 * Service pentru generare JWT token pentru import
 * Folosește user admin existent (username: admin).
 */
class ImportTokenService
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Generează JWT token pentru user admin (folosit în comenzile de import).
     *
     * @throws RuntimeException dacă user admin nu există
     */
    public function getImportToken(): string
    {
        // Folosim user admin existent (username: admin)
        $user = $this->userRepository->findOneBy(['username' => 'admin']);

        if (!$user) {
            throw new RuntimeException('Admin user not found. Run fixtures first: symfony console doctrine:fixtures:load');
        }

        return $this->jwtManager->create($user);
    }

    /**
     * Validează JWT token.
     */
    public function validateToken(string $token): bool
    {
        try {
            $this->jwtManager->parse($token);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
