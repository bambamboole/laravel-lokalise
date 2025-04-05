<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocalTranslationRepository
{
    private string $langPath;

    public function __construct(
        private readonly Filesystem $fs,
        private readonly string $basePath,
    ) {
        $this->langPath = is_dir($dir = $this->basePath.'/resources/lang') ? $dir : $this->basePath.'/lang';
    }

    public function getLocales(): array
    {
        $localeFolders = array_map(
            fn (string $path) => Str::afterLast($path, '/'),
            $this->fs->directories($this->langPath),
        );

        $jsonFileLocales = array_map(
            fn (\SplFileInfo $file) => Str::before($file->getFilename(), '.json'),
            array_filter(
                $this->fs->files($this->langPath),
                fn (\SplFileInfo $file) => $file->getExtension() === 'json',
            )
        );

        return array_unique(array_merge($localeFolders, $jsonFileLocales));
    }

    public function getTranslationFiles(): array
    {
        if (! $this->fs->isDirectory($this->langPath)) {
            return [];
        }

        return $this->fs->allFiles($this->langPath);
    }

    public function getTranslations(\SplFileInfo $file): array
    {
        if ($file->getExtension() === 'php') {
            $group = Str::before($file->getFilename(), '.php');
            $translations = require $file->getRealPath();

            return Arr::dot($translations, $group.'.');
        }

        return json_decode($this->fs->get($file->getRealPath()), true);
    }
}
