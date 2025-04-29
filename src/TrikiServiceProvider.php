<?php

declare(strict_types=1);

namespace WebMavens\Triki;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use WebMavens\Triki\Http\Middleware\AuthMiddleware;
use WebMavens\Triki\Http\Middleware\AuthorizeTriki;

class TrikiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('triki.auth', AuthMiddleware::class);
        $router->aliasMiddleware('triki.authorize', AuthorizeTriki::class);

        $this->registerTrikiGate();
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/views', 'triki');

        $packagePath = realpath(__DIR__ . '/../');

        $this->publishes([
            __DIR__ . '/config/triki.php' => config_path('triki.php'),
        ], 'triki-config');

        // Ensure shard.yml exists in the package
        if (File::exists($packagePath . '/shard.yml')) {
            exec("cd {$packagePath} && shards install");
        }

        // Generate obfuscator.cr in Laravel root if it doesn't exist
        $obfuscatorPath = base_path() . '/obfuscator.cr';

        if (!File::exists($obfuscatorPath)) {
            File::put($obfuscatorPath, $this->getDefaultObfuscatorConfig());
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/triki.php', 'triki');
    }

    private function getDefaultObfuscatorConfig(): string
    {
        return <<<'CR'
            require "triki"

            obfuscator = Triki.new({
              "users" => {
                "email" => :keep,
                "password" => :keep,
              },
              "failed_jobs" => :truncate,
              "jobs" => :truncate,
            })
            obfuscator.fail_on_unspecified_columns = false
            obfuscator.globally_kept_columns = %w[id created_at updated_at]
            obfuscator.obfuscate(STDIN, STDOUT)
            CR;
    }

    /**
     * Register the Triki authorization gate.
     */
    protected function registerTrikiGate(): void
    {
        Gate::define('viewTriki', function ($user) {
            if (!config('triki.auth.enabled')) {
                return true; // If auth is disabled, allow everyone
            }

            return in_array($user->email, config('triki.auth.authorized_emails', []));
        });
    }
}
