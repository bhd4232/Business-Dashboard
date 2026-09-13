<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v3 gap analysis P3 — NID/passport numbers were stored in plaintext.
 * `nid_number` / `nominee_nid_or_passport` (investors) and `guarantor_nid`
 * (investor_security_instruments) already had the plaintext-columns problem
 * this app's `encrypted` Eloquent cast solves elsewhere (ConversationChannel
 * tokens, PushDevice tokens, StorefrontCartRecord email/address) — same
 * pattern applied here, not a new mechanism.
 *
 * Columns move to TEXT first: Laravel's encrypted payload (base64 JSON of
 * iv+value+mac) runs well past a typical string(255) for anything longer
 * than a short NID once encrypted. Existing plaintext rows are re-encrypted
 * in place; re-running this migration is a no-op for rows already encrypted
 * (a successful Crypt::decryptString() means "already encrypted, skip").
 */
return new class extends Migration
{
    private const COLUMNS = [
        'investors' => ['nid_number', 'nominee_nid_or_passport'],
        'investor_security_instruments' => ['guarantor_nid'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->text($column)->nullable()->change();
                }
            });

            $this->rewrite($table, $columns, encrypt: true);
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->rewrite($table, $columns, encrypt: false);
        }
    }

    /** @param  list<string>  $columns */
    private function rewrite(string $table, array $columns, bool $encrypt): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns, $encrypt): void {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $column) {
                    $value = $row->{$column};

                    if (blank($value)) {
                        continue;
                    }

                    if ($encrypt) {
                        // Already-encrypted values decrypt cleanly -- skip
                        // them so a re-run (or a rollback+re-migrate) never
                        // double-encrypts.
                        try {
                            Crypt::decryptString($value);

                            continue;
                        } catch (DecryptException) {
                            $updates[$column] = Crypt::encryptString($value);
                        }
                    } else {
                        try {
                            $updates[$column] = Crypt::decryptString($value);
                        } catch (DecryptException) {
                            // Already plaintext -- nothing to reverse.
                        }
                    }
                }

                if ($updates !== []) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });
    }
};
