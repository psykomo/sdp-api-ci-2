<?php

namespace Tests\Unit;

use App\Services\UnitOfWork;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

class UnitOfWorkTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        db_connect()->query(
            'CREATE TABLE IF NOT EXISTS unit_of_work_test (id INTEGER PRIMARY KEY, value TEXT NOT NULL)',
        );
        db_connect()->table('unit_of_work_test')->truncate();
    }

    protected function tearDown(): void
    {
        db_connect()->query('DROP TABLE IF EXISTS unit_of_work_test');

        parent::tearDown();
    }

    public function testCommitsAllWritesWhenCallbackSucceeds(): void
    {
        $db = db_connect();
        $unitOfWork = new UnitOfWork($db);

        $result = $unitOfWork->run(function () use ($db): string {
            $db->table('unit_of_work_test')->insert(['value' => 'first module']);
            $db->table('unit_of_work_test')->insert(['value' => 'second module']);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(2, $db->table('unit_of_work_test')->countAllResults());
    }

    public function testRollsBackEveryWriteWhenCallbackThrows(): void
    {
        $db = db_connect();
        $unitOfWork = new UnitOfWork($db);

        try {
            $unitOfWork->run(function () use ($db): void {
                $db->table('unit_of_work_test')->insert(['value' => 'inmate module']);
                $db->table('unit_of_work_test')->insert(['value' => 'transfer module']);

                throw new RuntimeException('Simulated audit failure');
            });

            $this->fail('Expected the simulated failure to be re-thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        }

        $this->assertSame(0, $db->table('unit_of_work_test')->countAllResults());
    }

    public function testNestedUnitOfWorkJoinsOuterTransaction(): void
    {
        $db = db_connect();
        $outer = new UnitOfWork($db);
        $inner = new UnitOfWork($db);

        $outer->run(function () use ($db, $inner, $outer): void {
            $db->table('unit_of_work_test')->insert(['value' => 'outer']);

            $this->assertTrue($inner->isActive());

            $inner->run(function () use ($db): void {
                $db->table('unit_of_work_test')->insert(['value' => 'module-local process']);
            });

            // Inner "commit" must not finalize the outer transaction yet.
            $this->assertTrue($outer->isActive());
        });

        $this->assertFalse($outer->isActive());
        $this->assertSame(2, $db->table('unit_of_work_test')->countAllResults());
    }

    public function testOuterRollbackUndoesNestedModuleWrites(): void
    {
        $db = db_connect();
        $outer = new UnitOfWork($db);
        $inner = new UnitOfWork($db);

        try {
            $outer->run(function () use ($db, $inner): void {
                $inner->run(function () use ($db): void {
                    $db->table('unit_of_work_test')->insert(['value' => 'inmate ownership change']);
                });

                $db->table('unit_of_work_test')->insert(['value' => 'transfer history']);

                throw new RuntimeException('Outer audit failed after nested module succeeded');
            });

            $this->fail('Expected the outer failure to be re-thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Outer audit failed after nested module succeeded',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, $db->table('unit_of_work_test')->countAllResults());
    }
}
