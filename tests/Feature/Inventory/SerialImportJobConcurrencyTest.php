<?php

namespace Tests\Feature\Inventory;

use App\Jobs\AnalyzeSerialImport;
use App\Jobs\ProcessSerialImport;
use App\Models\SerialImportBatch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SerialImportJobConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('SERIAL_INVENTORY_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit disposable MySQL test database opt-in.');
        }
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            $this->fail('Refusing to migrate a database outside the disposable MySQL test boundary.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    public static function failureJobs(): array
    {
        return [[AnalyzeSerialImport::class, 'analyzing', null], [ProcessSerialImport::class, 'processing', ['mapping_hash' => 'snapshot']]];
    }

    #[DataProvider('failureJobs')]
    public function test_competing_completion_cannot_be_overwritten_by_stale_failure(string $jobClass, string $status, ?array $snapshot): void
    {
        $batch = SerialImportBatch::factory()->create(['status' => $status, 'mapping_snapshot' => $snapshot]);
        $connection = DB::connection();
        $rival = new PDO('mysql:unix_socket='.$connection->getConfig('unix_socket').';dbname='.$connection->getDatabaseName(), $connection->getConfig('username'), $connection->getConfig('password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rival->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $observed = false;
        $blocked = false;
        DB::listen(function (QueryExecuted $query) use ($rival, $batch, &$observed, &$blocked): void {
            if ($observed || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'serial_import_batches')) {
                return;
            }
            $observed = true;
            try {
                $statement = $rival->prepare("UPDATE serial_import_batches SET status = 'completed', last_error = NULL WHERE id = ?");
                $statement->execute([$batch->id]);
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 1205) {
                    throw $exception;
                }
                $blocked = true;
            }
        });

        $job = new $jobClass($batch->id);
        $job->failed(new \RuntimeException('SQLSTATE private technical detail'));

        $this->assertTrue($observed);
        $this->assertSame($blocked ? 'failed' : 'completed', $batch->fresh()->status->value);
        $this->assertTrue($blocked, 'Failure state check must hold the batch lock until its update.');
        $batch->update(['status' => 'completed', 'last_error' => null]);
        $job->failed(new \RuntimeException('late failure'));
        $this->assertSame('completed', $batch->fresh()->status->value);
        $this->assertNull($batch->last_error);
    }
}
