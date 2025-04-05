<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\Commands\DownloadTranslationFilesCommand;
use Bambamboole\LaravelLokalise\Commands\InfoCommand;
use Bambamboole\LaravelLokalise\Commands\UploadTranslationFilesCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Lokalise\LokaliseApiClient;

class LaravelLokaliseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocalTranslationRepository::class, fn () => new LocalTranslationRepository(
            new Filesystem,
            config('lokalise.base_path'),
        ));
        $this->app->singleton(LokaliseClient::class, fn () => new LokaliseClient(
            new LokaliseApiClient(config('lokalise.token')),
            new TranslationKeyFactory,
            config('lokalise.project_id'),
        ));
        $this->app->singleton(LokaliseService::class, function (Application $app) {
            return new LokaliseService(
                $app->make(LokaliseClient::class),
                new TranslationKeyTransformer,
                new Filesystem,
                $app->make(LocalTranslationRepository::class),
                config('lokalise.base_path'),
            );
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lokalise.php', 'lokalise');
        if ($this->app->runningInConsole()) {
            $this->commands([
                InfoCommand::class,
                DownloadTranslationFilesCommand::class,
                UploadTranslationFilesCommand::class,
            ]);
        }
    }
}
