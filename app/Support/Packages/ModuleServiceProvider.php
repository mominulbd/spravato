<?php

declare(strict_types=1);

namespace App\Support\Packages;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Base provider for every package under /packages (docs/ARCHITECTURE.md §3).
 *
 * A package declares its policies, morph aliases, observers and listeners as
 * data; this class applies the repository conventions so a module provider
 * only has to describe itself:
 *
 *   routes/api.php        → /api/v1          middleware: api               names: <module>.*
 *   routes/api_public.php → /api/v1/public   middleware: api, public       names: public.<module>.*
 *   routes/api_admin.php  → /api/v1/admin    middleware: api, admin        names: admin.<module>.*
 *   migrations/           → loaded for artisan
 *   src/Console/Commands/ → registered as artisan commands
 *
 * The `public` and `admin` middleware groups are defined in bootstrap/app.php.
 * `public` is read by the Next.js site (uptms-app); `admin` by the admin SPA.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** @var array<class-string<Model>, class-string> model → policy */
    protected array $policies = [];

    /** @var array<string, class-string<Model>> morph alias → model (enforced map) */
    protected array $morphMap = [];

    /** @var array<class-string<Model>, class-string|list<class-string>> model → observer(s) */
    protected array $observers = [];

    /** @var array<class-string, list<string>> event → listeners (a class, or Class@method) */
    protected array $listen = [];

    /**
     * Laravel binds `public $singletons` / `public $bindings` itself before
     * register() runs; this adds request/job-scoped bindings the same way.
     *
     * @var array<class-string, class-string|\Closure> abstract → concrete
     */
    protected array $scoped = [];

    /** @var list<class-string> additional command classes beyond src/Console/Commands */
    protected array $commands = [];

    public function register(): void
    {
        foreach ($this->scoped as $abstract => $concrete) {
            $this->app->scoped($abstract, $concrete);
        }

        $configFile = $this->modulePath('config/'.$this->moduleKey().'.php');

        if (is_file($configFile)) {
            $this->mergeConfigFrom($configFile, $this->moduleKey());
        }

        $this->registerModule();
    }

    /**
     * @throws BindingResolutionException
     */
    final public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('migrations'));

        $this->bootRoutes();
        $this->bootPolicies();
        $this->bootMorphMap();
        $this->bootObservers();
        $this->bootListeners();
        $this->bootCommands();

        $this->bootModule();
    }

    /** Container bindings that need more than a class map. */
    protected function registerModule(): void {}

    /** Anything else the package needs at boot. */
    protected function bootModule(): void {}

    /** Absolute path inside the package directory. */
    protected function modulePath(string $path = ''): string
    {
        $root = dirname((string) (new ReflectionClass(static::class))->getFileName(), 2);

        return $path === '' ? $root : $root.'/'.ltrim($path, '/');
    }

    /** `ConsultationRequestsServiceProvider` → `consultation_requests`: route-name prefix and config key. */
    protected function moduleKey(): string
    {
        return Str::snake(Str::beforeLast(class_basename(static::class), 'ServiceProvider'));
    }

    protected function moduleNamespace(): string
    {
        return (new ReflectionClass(static::class))->getNamespaceName();
    }

    private function bootRoutes(): void
    {
        $key = $this->moduleKey();

        $groups = [
            'api.php' => ['api/v1', ['api'], $key.'.'],
            'api_public.php' => ['api/v1/public', ['api', 'public'], 'public.'.$key.'.'],
            'api_admin.php' => ['api/v1/admin', ['api', 'admin'], 'admin.'.$key.'.'],
        ];

        foreach ($groups as $file => [$prefix, $middleware, $name]) {
            $path = $this->modulePath('routes/'.$file);

            if (! is_file($path)) {
                continue;
            }

            Route::middleware($middleware)
                ->prefix($prefix)
                ->name($name)
                ->group($path);
        }
    }

    private function bootPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    private function bootMorphMap(): void
    {
        if ($this->morphMap !== []) {
            Relation::morphMap($this->morphMap);
        }
    }

    private function bootObservers(): void
    {
        foreach ($this->observers as $model => $observers) {
            foreach ((array) $observers as $observer) {
                $model::observe($observer);
            }
        }
    }

    private function bootListeners(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    private function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $commands = $this->commands;
        $directory = $this->modulePath('src/Console/Commands');

        if (is_dir($directory)) {
            foreach (Finder::create()->files()->in($directory)->name('*.php')->sortByName() as $file) {
                $commands[] = $this->moduleNamespace().'\\Console\\Commands\\'.$file->getBasename('.php');
            }
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }
}
