<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GrantCatalogAdmin extends Command
{
    protected $signature = 'lessons:grant-admin {email : Exact email of an existing verified account} {--revoke : Remove administration access}';

    protected $description = 'Explicitly grant or revoke catalog administration for an existing verified account';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('An explicit valid account email is required.');

            return self::FAILURE;
        }
        $result = DB::transaction(function () use ($email): bool {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if ($user === null || ! $user->hasVerifiedEmail()) {
                return false;
            }
            $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

            return true;
        });
        if (! $result) {
            $this->error('The account must already exist and have a verified email. No account was created.');

            return self::FAILURE;
        }
        $this->info($this->option('revoke') ? 'Administration access revoked.' : 'Administration access granted.');

        return self::SUCCESS;
    }
}
