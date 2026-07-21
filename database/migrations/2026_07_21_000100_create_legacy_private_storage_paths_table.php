<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_private_storage_paths', function (Blueprint $table): void {
            $table->id();
            // R2 object keys are case-sensitive while the application's
            // default MySQL collation is not. Use a binary-safe digest as the
            // unique identity so A.pdf and a.pdf remain distinct keys.
            $table->char('path_hash', 64)->unique();
            $table->text('path');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_conflicted')->default(false);
            $table->unsignedInteger('reference_count')->default(1);
            $table->timestamps();
        });

        DB::table('conversation_messages')
            ->join('conversations', 'conversations.id', '=', 'conversation_messages.conversation_id')
            ->whereNotNull('conversation_messages.media_path')
            ->select('conversation_messages.id as reference_id', 'conversation_messages.media_path as path', 'conversations.company_id')
            ->orderBy('conversation_messages.id')
            ->chunk(500, function ($rows): void {
                $this->mergeReferences($rows);
            });

        DB::table('voucher_attachments')
            ->whereNotNull('file_path')
            ->select('id as reference_id', 'file_path as path', 'company_id')
            ->orderBy('id')
            ->chunk(500, function ($rows): void {
                $this->mergeReferences($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_private_storage_paths');
    }

    protected function mergeReferences(iterable $rows): void
    {
        /** @var array<string, array{path: string, companies: array<int, int>}> $references */
        $references = [];

        foreach ($rows as $row) {
            $path = trim((string) $row->path);

            if ($path === ''
                || str_starts_with($path, 'companies/')
                || str_starts_with($path, '/')
                || filter_var($path, FILTER_VALIDATE_URL) !== false) {
                continue;
            }

            $companyId = (int) $row->company_id;
            $hash = hash('sha256', $path);
            $references[$hash] ??= ['path' => $path, 'companies' => []];

            if ($references[$hash]['path'] !== $path) {
                throw new RuntimeException('A SHA-256 collision occurred while indexing legacy storage paths.');
            }

            $references[$hash]['companies'][$companyId] = ($references[$hash]['companies'][$companyId] ?? 0) + 1;
        }

        if ($references === []) {
            return;
        }

        $existing = DB::table('legacy_private_storage_paths')
            ->whereIn('path_hash', array_keys($references))
            ->get()
            ->keyBy('path_hash');
        $now = now();

        foreach ($references as $hash => $reference) {
            $record = $existing->get($hash);
            $companyIds = array_keys($reference['companies']);
            $referenceCount = array_sum($reference['companies']);

            if (! $record) {
                DB::table('legacy_private_storage_paths')->insert([
                    'path_hash' => $hash,
                    'path' => $reference['path'],
                    'company_id' => count($companyIds) === 1 ? $companyIds[0] : null,
                    'is_conflicted' => count($companyIds) !== 1,
                    'reference_count' => $referenceCount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            if (! hash_equals((string) $record->path, $reference['path'])) {
                throw new RuntimeException('A SHA-256 collision occurred while indexing legacy storage paths.');
            }

            $combinedCompanyIds = $record->is_conflicted
                ? []
                : array_unique([(int) $record->company_id, ...$companyIds]);
            $isConflicted = (bool) $record->is_conflicted || count($combinedCompanyIds) !== 1;

            DB::table('legacy_private_storage_paths')
                ->where('path_hash', $hash)
                ->update([
                    'company_id' => $isConflicted ? null : $combinedCompanyIds[0],
                    'is_conflicted' => $isConflicted,
                    'reference_count' => (int) $record->reference_count + $referenceCount,
                    'updated_at' => $now,
                ]);
        }
    }
};
