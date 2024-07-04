<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\Commands\DownloadTranslationFilesCommand;
use Bambamboole\LaravelLokalise\Commands\UploadTranslationFilesCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Lokalise\LokaliseApiClient;

class LaravelLokaliseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LokaliseService::class, function (Application $app) {
            return new LokaliseService(
                new LokaliseClient(
                    new LokaliseApiClient(config('lokalise.token')),
                    config('lokalise.project_id'),
                ),
                new TranslationKeyTransformer(),
                new Filesystem(),
                $app->langPath(),
                $app->basePath(),
            );
        });
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lokalise.php', 'lokalise');
        if ($this->app->runningInConsole()) {
            $this->commands([
                DownloadTranslationFilesCommand::class,
                UploadTranslationFilesCommand::class,
            ]);
        }
    }
}
