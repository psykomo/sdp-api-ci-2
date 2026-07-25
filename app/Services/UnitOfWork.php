<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Executes a use case atomically on the application's default DB connection.
 *
 * Nest-safe:
 * - If no transaction is open, this starts one and commits/rolls it back.
 * - If already inside a transaction (outer UnitOfWork / CI4 transBegin),
 *   this joins that outer transaction and neither commits nor rolls back.
 *
 * The connection is resolved lazily via db_connect() so ConnectionResolver
 * can switch the default group (multi topology) before work begins.
 *
 * Modules may therefore wrap their own business processes with UnitOfWork
 * and still be called safely from a larger cross-module UnitOfWork.
 */
class UnitOfWork
{
    public function __construct(
        protected ?BaseConnection $db = null,
    ) {
    }

    /**
     * Always the current default connection (respects ConnectionResolver).
     */
    protected function connection(): BaseConnection
    {
        return $this->db ?? db_connect();
    }

    /**
     * Whether the default connection already has an open transaction.
     */
    public function isActive(): bool
    {
        return $this->connection()->transDepth > 0;
    }

    /**
     * @template T
     *
     * @param callable(BaseConnection): T $operation
     * @return T
     *
     * @throws Throwable Re-throws the original application/database failure.
     */
    public function run(callable $operation): mixed
    {
        $db              = $this->connection();
        $ownsTransaction = $db->transDepth === 0;

        if ($ownsTransaction && ! $db->transBegin()) {
            throw new RuntimeException('Unable to begin database transaction.');
        }

        try {
            $result = $operation($db);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Database transaction was marked as failed.');
            }

            if ($ownsTransaction && ! $db->transCommit()) {
                throw new RuntimeException('Unable to commit database transaction.');
            }

            return $result;
        } catch (Throwable $exception) {
            // Only the owner of the outermost boundary issues SQL ROLLBACK.
            // Nested callers rethrow so the outer UnitOfWork can abort everything.
            if ($ownsTransaction) {
                $db->transRollback();
            }

            throw $exception;
        }
    }
}
