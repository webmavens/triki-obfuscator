<?php

declare(strict_types=1);

namespace WebMavens\Triki\Jobs;

use WebMavens\Triki\Notifications\DumpReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Exception;

class GenerateObfuscatedDumpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    protected string $dbConnection;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $keepTables,
        protected string $email
    ) {
        $this->dbConnection = config('database.default', 'mysql');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting obfuscated dump download...');

        try {
            $dbConfig = config("database.connections.{$this->dbConnection}");
            $dbUser = $dbConfig['username'] ?? '';
            $dbPass = $dbConfig['password'] ?? '';
            $dbName = $dbConfig['database'];
            $dbHost = $dbConfig['host'] ?? 'localhost';

            $date = now()->format('Y-m-d_H:i:s');
            $filename = "obfuscated_dump_{$date}.sql";

            $relativeDir = 'app/private/obfuscated';
            $absoluteDir = storage_path($relativeDir);
            $dumpPath = "{$absoluteDir}/{$filename}";

            if (!file_exists($absoluteDir)) {
                mkdir($absoluteDir, 0755, true);
            }

            $packagePath = realpath(__DIR__ . '/../../');
            $obfuscatorPath = escapeshellarg(base_path('obfuscator.cr'));

            $tablesToKeep = $this->keepTables;
            $tablesString = implode(' ', $tablesToKeep);

            if ($this->dbConnection === 'mysql') {
                $command = "cd {$packagePath} && mysqldump -c --single-transaction --quick --no-autocommit --add-drop-table --hex-blob -u {$dbUser} -p{$dbPass} {$dbName} --tables {$tablesString} | crystal run {$obfuscatorPath} 2>&1 | grep -v 'WARN - triki' > {$dumpPath}";
            } elseif ($this->dbConnection === 'pgsql') {
                $pgTables = '';

                foreach ($tablesToKeep as $table) {
                    $pgTables .= "--table={$table} ";
                }

                $command = "cd {$packagePath} && PGPASSWORD={$dbPass} pg_dump --host={$dbHost} --port=5432 --username={$dbUser} --dbname={$dbName} {$pgTables}--no-owner --no-privileges --format=plain | crystal run {$obfuscatorPath} 2>&1 | grep -v 'WARN - triki' > {$dumpPath}";
            } elseif ($this->dbConnection === 'sqlite') {
                Log::warning('Obfuscated dumps are not supported for SQLite.');
                $sqlitePath = escapeshellarg($dbConfig['database']);

                $dumpCommands = implode("\n", array_map(fn($table) => ".dump {$table}", $this->keepTables));
                $dumpCommandsEscaped = escapeshellarg($dumpCommands);

                $command = "echo {$dumpCommandsEscaped} | sqlite3 {$sqlitePath} > {$dumpPath}";
            } else {
                throw new Exception("Unsupported database connection: {$this->dbConnection}");
            }

            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                $errorMessage = implode("\n", $output);
                Log::error('Obfuscated dump generation failed.', [
                    'command'     => $command,
                    'output'      => $output,
                    'return_code' => $returnVar,
                ]);

                Notification::route('mail', $this->email)->notify(
                    new DumpReadyNotification('failure', $errorMessage)
                );

                throw new Exception('Failed to generate obfuscated dump. Check logs for details.');
            }

            Log::info('Obfuscated dump generated successfully.', ['file' => $dumpPath]);

            Notification::route('mail', $this->email)->notify(
                new DumpReadyNotification('success')
            );
        } catch (Exception $e) {
            Log::error('Error in GenerateObfuscatedDumpJob: ' . $e->getMessage());
            Notification::route('mail', $this->email)->notify(
                new DumpReadyNotification('failure', $e->getMessage())
            );

            throw $e;
        }
    }
}
