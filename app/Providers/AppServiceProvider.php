<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-creación de base de datos SQLite y migraciones automáticas en Hostinger si no existen
        try {
            $dbPath = database_path('database.sqlite');
            if (!file_exists($dbPath)) {
                @touch($dbPath);
            }

            if (config('database.default') === 'sqlite' && file_exists($dbPath)) {
                if (!Schema::hasTable('products')) {
                    Artisan::call('migrate', ['--force' => true]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Auto-migration error: ' . $e->getMessage());
        }
    }
}
