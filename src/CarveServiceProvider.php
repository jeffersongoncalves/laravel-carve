<?php

namespace JeffersonGoncalves\Carve;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\View\Factory;
use JeffersonGoncalves\Carve\Commands\ConvertCommand;
use JeffersonGoncalves\Carve\Commands\LintCommand;
use JeffersonGoncalves\Carve\Commands\RenderCommand;
use JeffersonGoncalves\Carve\View\CarveEngine;
use JeffersonGoncalves\Carve\View\Components\Carve as CarveComponent;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CarveServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('carve')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasCommands([
                RenderCommand::class,
                ConvertCommand::class,
                LintCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ConverterFactory::class);
        $this->app->singleton(CarveManager::class);
        $this->app->alias(CarveManager::class, 'carve');
    }

    public function packageBooted(): void
    {
        $this->registerBlade();
        $this->registerViewEngine();
        $this->registerMacros();
    }

    private function registerBlade(): void
    {
        // Output is HTML produced under the profile's safe mode, so it is
        // echoed raw. Use a safe profile for untrusted source.
        Blade::directive('carve', fn (string $expression): string => "<?php echo app(\JeffersonGoncalves\Carve\CarveManager::class)->toHtml({$expression}); ?>");

        Blade::directive('carveText', fn (string $expression): string => "<?php echo e(app(\JeffersonGoncalves\Carve\CarveManager::class)->toText({$expression})); ?>");

        Blade::component('carve', CarveComponent::class);
    }

    private function registerViewEngine(): void
    {
        if (! config('carve.views.enabled', true)) {
            return;
        }

        $this->callAfterResolving('view', function (Factory $view): void {
            $view->addExtension(
                (string) config('carve.views.extension', 'crv'),
                'carve',
                fn (): CarveEngine => new CarveEngine($this->app->make(CarveManager::class), config('carve.views.profile')),
            );
        });
    }

    private function registerMacros(): void
    {
        Str::macro('carve', fn (string $source, ?string $profile = null): string => app(CarveManager::class)->toHtml($source, $profile));

        Str::macro('carveText', fn (string $source, ?string $profile = null): string => app(CarveManager::class)->toText($source, $profile));

        Stringable::macro('carve', function (?string $profile = null): Stringable {
            /** @var Stringable $this */
            return new Stringable(app(CarveManager::class)->toHtml($this->value(), $profile));
        });

        Stringable::macro('carveText', function (?string $profile = null): Stringable {
            /** @var Stringable $this */
            return new Stringable(app(CarveManager::class)->toText($this->value(), $profile));
        });
    }
}
