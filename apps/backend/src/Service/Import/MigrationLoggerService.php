<?php

declare(strict_types=1);

namespace App\Service\Import;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PDO;

/**
 * Service pentru tracking import Newscoop → Deschide
 * Salvează mapping-uri între ID-uri Newscoop și Deschide.
 */
class MigrationLoggerService
{
    private const TABLE_NAME = 'newscoop_migration_log';

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * Log import success.
     *
     * @param string $entityType Tipul entității (article, category, author, image, etc.)
     * @param int|string $newscoopId ID-ul din Newscoop
     * @param int $deschideId ID-ul din Deschide
     * @param array<string, mixed>|null $additionalData Date suplimentare (JSON)
     *
     * @throws Exception
     */
    public function logSuccess(
        string $entityType,
        int|string $newscoopId,
        int $deschideId,
        ?array $additionalData = null
    ): void {
        $this->connection->insert(self::TABLE_NAME, [
            'entity_type' => $entityType,
            'newscoop_id' => (string) $newscoopId,
            'deschide_id' => $deschideId,
            'status' => 'success',
            'error_message' => null,
            'additional_data' => $additionalData ? json_encode($additionalData) : null,
            'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log import error.
     *
     * @param string $entityType Tipul entității
     * @param int|string $newscoopId ID-ul din Newscoop
     * @param string $errorMessage Mesaj eroare
     * @param array<string, mixed>|null $additionalData Date suplimentare
     *
     * @throws Exception
     */
    public function logError(
        string $entityType,
        int|string $newscoopId,
        string $errorMessage,
        ?array $additionalData = null
    ): void {
        $this->connection->insert(self::TABLE_NAME, [
            'entity_type' => $entityType,
            'newscoop_id' => (string) $newscoopId,
            'deschide_id' => null,
            'status' => 'error',
            'error_message' => substr($errorMessage, 0, 5000), // Limită 5000 chars
            'additional_data' => $additionalData ? json_encode($additionalData) : null,
            'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get Deschide ID pentru un Newscoop ID.
     *
     * @param string $entityType Tipul entității
     * @param int|string $newscoopId ID-ul din Newscoop
     *
     * @throws Exception
     *
     * @return int|null ID-ul din Deschide sau null dacă nu există
     */
    public function getMapping(string $entityType, int|string $newscoopId): ?int
    {
        $result = $this->connection->fetchOne(
            'SELECT deschide_id FROM ' . self::TABLE_NAME . '
             WHERE entity_type = ? AND newscoop_id = ? AND status = ?',
            [$entityType, (string) $newscoopId, 'success']
        );

        return $result ? (int) $result : null;
    }

    /**
     * Get toate mapping-urile pentru un tip de entitate.
     *
     * @param string $entityType Tipul entității
     *
     * @throws Exception
     *
     * @return array<string, int> Array [newscoop_id => deschide_id]
     */
    public function getAllMappings(string $entityType): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT newscoop_id, deschide_id FROM ' . self::TABLE_NAME . '
             WHERE entity_type = ? AND status = ?',
            [$entityType, 'success']
        );

        $mappings = [];
        foreach ($result as $row) {
            $mappings[$row['newscoop_id']] = (int) $row['deschide_id'];
        }

        return $mappings;
    }

    /**
     * Get mapping-uri cu paginare.
     *
     * @param string $entityType Tipul entității
     * @param string $status Status (success, error)
     * @param int|null $limit Limită rezultate
     * @param int $offset Offset pentru paginare
     *
     * @throws Exception
     *
     * @return array<string, int> Array [newscoop_id => deschide_id]
     */
    public function getMappings(string $entityType, string $status = 'success', ?int $limit = null, int $offset = 0): array
    {
        $sql = 'SELECT newscoop_id, deschide_id FROM ' . self::TABLE_NAME . '
                WHERE entity_type = ? AND status = ?
                ORDER BY id ASC';

        $params = [$entityType, $status];
        $types = [];

        if ($limit) {
            $sql .= ' LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;
            $types = [2 => PDO::PARAM_INT, 3 => PDO::PARAM_INT];
        }

        $result = $this->connection->fetchAllAssociative($sql, $params, $types);

        $mappings = [];
        foreach ($result as $row) {
            $mappings[$row['newscoop_id']] = (int) $row['deschide_id'];
        }

        return $mappings;
    }

    /**
     * Verifică dacă o entitate a fost deja importată.
     *
     * @param string $entityType Tipul entității
     * @param int|string $newscoopId ID-ul din Newscoop
     *
     * @throws Exception
     *
     * @return bool True dacă entitatea a fost importată
     */
    public function isImported(string $entityType, int|string $newscoopId): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE_NAME . '
             WHERE entity_type = ? AND newscoop_id = ? AND status = ?',
            [$entityType, (string) $newscoopId, 'success']
        );

        return (int) $result > 0;
    }

    /**
     * Get statistici import.
     *
     * @param string|null $entityType Tipul entității (null pentru toate)
     *
     * @throws Exception
     *
     * @return array{success: int, error: int, total: int}
     */
    public function getStats(?string $entityType = null): array
    {
        if ($entityType) {
            $success = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE entity_type = ? AND status = ?',
                [$entityType, 'success']
            );

            $error = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE entity_type = ? AND status = ?',
                [$entityType, 'error']
            );
        } else {
            $success = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE status = ?',
                ['success']
            );

            $error = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE status = ?',
                ['error']
            );
        }

        return [
            'success' => $success,
            'error' => $error,
            'total' => $success + $error,
        ];
    }

    /**
     * Get statistici per tip de entitate.
     *
     * @throws Exception
     *
     * @return array<string, array{success: int, error: int, total: int}>
     */
    public function getStatsByEntityType(): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT entity_type, status, COUNT(*) as count
             FROM ' . self::TABLE_NAME . '
             GROUP BY entity_type, status'
        );

        $stats = [];
        foreach ($result as $row) {
            $entityType = $row['entity_type'];
            if (!isset($stats[$entityType])) {
                $stats[$entityType] = ['success' => 0, 'error' => 0, 'total' => 0];
            }

            $count = (int) $row['count'];
            $stats[$entityType][$row['status']] = $count;
            $stats[$entityType]['total'] += $count;
        }

        return $stats;
    }

    /**
     * Get ultimele erori.
     *
     * @param int $limit Număr maxim de erori
     *
     * @throws Exception
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentErrors(int $limit = 50): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT entity_type, newscoop_id, error_message, created_at
             FROM ' . self::TABLE_NAME . '
             WHERE status = ?
             ORDER BY created_at DESC
             LIMIT ?',
            ['error', $limit],
            [1 => PDO::PARAM_INT]
        );
    }

    /**
     * Clear toate log-urile pentru un tip de entitate
     * ATENȚIE: Operație destructivă!
     *
     * @param string $entityType Tipul entității
     *
     * @throws Exception
     */
    public function clearLogs(string $entityType): void
    {
        $this->connection->delete(self::TABLE_NAME, [
            'entity_type' => $entityType,
        ]);
    }

    /**
     * Clear toate log-urile
     * ATENȚIE: Operație destructivă!
     *
     * @throws Exception
     */
    public function clearAllLogs(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE ' . self::TABLE_NAME);
    }
}
