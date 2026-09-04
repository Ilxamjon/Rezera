<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SmokeBetaCommand extends Command
{
    protected $signature = 'rezera:smoke
                            {--strict : Fail on production-hardening warnings}';

    protected $description = 'Post-deploy / beta smoke checks (DB, btree_gist, exclusion, config)';

    public function handle(): int
    {
        $failed = 0;
        $warnings = 0;

        $this->info('Rezera smoke checks');

        if (! $this->check('Database connection', function (): void {
            DB::selectOne('SELECT 1 AS ok');
        })) {
            $failed++;
        }

        if (! $this->check('btree_gist extension', function (): void {
            $row = DB::selectOne('SELECT 1 AS ok FROM pg_extension WHERE extname = ?', ['btree_gist']);
            if ($row === null) {
                throw new \RuntimeException('btree_gist is not installed');
            }
        })) {
            $failed++;
        }

        if (! $this->check('reservations table present', function (): void {
            if (! Schema::hasTable('reservations')) {
                throw new \RuntimeException('reservations table missing — run migrations');
            }
        })) {
            $failed++;
        }

        if (! $this->check('GiST occupancy exclusion constraint', function (): void {
            $row = DB::selectOne(
                'SELECT 1 AS ok
                 FROM pg_constraint
                 WHERE conname = ?
                 LIMIT 1',
                ['reservations_no_overlapping_occupancy']
            );
            if ($row === null) {
                throw new \RuntimeException('Constraint reservations_no_overlapping_occupancy missing');
            }
        })) {
            $failed++;
        }

        if (! $this->check('categories seeded', function (): void {
            $count = (int) DB::table('business_categories')->count();
            if ($count < 1) {
                throw new \RuntimeException('No business categories — run db:seed');
            }
        })) {
            $failed++;
        }

        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $mockEnabled = (bool) config('payment.providers.mock.enabled');
        $mockSim = (bool) config('payment.providers.mock.allow_simulation');
        $seedDemo = filter_var(env('SEED_DEMO_CLUB', false), FILTER_VALIDATE_BOOLEAN);

        if ($env === 'production') {
            if ($debug) {
                $this->warn('[warn] APP_DEBUG=true in production');
                $warnings++;
            }
            if ($mockEnabled || $mockSim) {
                $this->warn('[warn] Mock payment still enabled/simulatable in production');
                $warnings++;
            }
            if ($seedDemo) {
                $this->warn('[warn] SEED_DEMO_CLUB=true — demo passwords must not ship to production');
                $warnings++;
            }
            if (! config('sentry.dsn') && ! env('SENTRY_LARAVEL_DSN') && ! env('SENTRY_DSN')) {
                $this->warn('[warn] Sentry DSN is empty');
                $warnings++;
            }
        } else {
            $this->line("[info] APP_ENV={$env} (strict production checks skipped unless --strict)");
        }

        $this->newLine();
        if ($failed > 0) {
            $this->error("Smoke failed: {$failed} check(s)");

            return self::FAILURE;
        }

        if ($this->option('strict') && $warnings > 0) {
            $this->error("Smoke failed in --strict mode: {$warnings} warning(s)");

            return self::FAILURE;
        }

        $this->info('Smoke OK'.($warnings > 0 ? " ({$warnings} warning(s))" : ''));

        return self::SUCCESS;
    }

    private function check(string $label, callable $callback): bool
    {
        try {
            $callback();
            $this->line("[ok] {$label}");

            return true;
        } catch (\Throwable $exception) {
            $this->error("[fail] {$label}: ".$exception->getMessage());

            return false;
        }
    }
}
