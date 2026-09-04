<?php

namespace App\Modules\Account\Jobs;

use App\Modules\Account\Models\AccountImport;
use App\Modules\Account\Services\AccountImportService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportAccountsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Processamento de planilhas grandes pode demorar vários minutos;
     * desativa retries (evita importações parciais duplicadas) e amplia o
     * timeout/retry_after do worker.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public int $retryAfter = 900;

    public function __construct(private readonly int $accountImportId) {}

    public function handle(AccountImportService $imports, CurrentTenant $context): void
    {
        $record = AccountImport::query()->find($this->accountImportId);

        if ($record === null) {
            return;
        }

        $tempPath = null;

        try {
            $tenant = Tenant::query()->find($record->tenant_id);

            if ($tenant === null) {
                return;
            }

            $context->set($tenant);

            $user = User::query()->withoutTenancy()->find($record->user_id);

            if ($user === null) {
                return;
            }

            $tempPath = $this->writeTempFile($record->filename, $record->content);
            $file = new UploadedFile($tempPath, $record->filename, null, null, true);

            $imports->importXlsx($file, $record->cost_center_id, $user);
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            $record->delete();
        }
    }

    private function writeTempFile(string $filename, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'account_import_');
        $decoded = base64_decode($content, true);

        file_put_contents($path, $decoded !== false ? $decoded : $content);

        return $path;
    }
}
