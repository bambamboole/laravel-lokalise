<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelTranslationDumper\ArrayExporter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Lokalise\LokaliseApiClient;

class LokaliseService
{
    public function __construct(
        private LokaliseApiClient $apiClient,
        private Filesystem $fs,
        private string $projectId,
        private string $langPath,
        private string $basePath,
    ) {}

    public function downloadTranslations(): void
    {
        $result = $this->apiClient->files->list($this->projectId);
        $files = collect($result->body['files'])
            // Ignore the __unassigned__ file
            ->filter(fn ($file) => $file['filename'] !== '__unassigned__')
            // Ignore files without keys
            ->filter(fn ($file) => $file['key_count'] >= 1)
            // Get the filename
            ->map(fn ($file) => $file['filename']);

        foreach ($files as $file) {
            $result = $this->apiClient->keys->list($this->projectId, [
                'filter_filenames' => $file,
                'include_translations' => 1,
                'limit' => 500,
            ]);
            $keys = $result->body['keys'];
            foreach ($this->getLocales() as $locale) {
                $translations = [];
                foreach ($keys as $key) {
                    $keyName = Str::replace('::', '.', $key['key_name']['web']);
                    $translation = collect($key['translations'])->filter(fn ($translation) => $translation['language_iso'] === $locale)->first();
                    if (! $translation || $this->isEmptyTranslation($translation)) {
                        continue;
                    }
                    $translations[$keyName] = $this->replacePlaceholders($translation['translation']);
                }
                if (empty($translations)) {
                    continue;
                }
                $translations = $this->transformDottedStringsToArray($translations);
                $path = Str::replace('%LANG_ISO%', $locale, $file);
                if (Str::endsWith($file, '.json')) {
                    $beautifiedTranslations = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
                } else {
                    $translations = $translations[array_key_first($translations)];
                    $beautifiedTranslations = (new ArrayExporter)->export($translations);
                }
                $this->fs->ensureDirectoryExists(Str::beforeLast($this->basePath.'/'.$path, '/'));
                $this->fs->put($this->basePath.'/'.$path, $beautifiedTranslations);
            }
        }
    }

    public function uploadTranslations(): void
    {
        foreach ($this->getLocales() as $locale) {
            $this->uploadJsonFileIfExists($locale);
            $this->uploadPhpFiles($locale);
        }
    }

    private function uploadJsonFileIfExists(string $locale): void
    {
        $filePath = $this->langPath.'/'.$locale.'.json';
        if ($this->fs->missing($filePath)) {
            return;
        }
        $translations = json_decode($this->fs->get($filePath), true);
        $translations = $this->prepare($translations);
        $this->apiClient->files->upload($this->projectId, [
            'data' => base64_encode(json_encode($translations)),
            'filename' => ltrim(str_replace($this->basePath, '', $filePath), '/'),
            'lang_iso' => $locale,
            'convert_placeholders' => true,
            'replace_modified' => true,
            'distinguish_by_file' => true,
            'slashn_to_linebreak' => true,
        ]);
    }

    private function uploadPhpFiles(string $locale): void
    {
        $localePath = $this->langPath.'/'.$locale;
        if (! $this->fs->isDirectory($localePath)) {
            return;
        }
        $phpFiles = array_filter($this->fs->files($localePath), fn (\SplFileInfo $file) => $file->getExtension() === 'php');

        foreach ($phpFiles as $file) {
            $absoluteFilePath = $localePath.'/'.$file->getBasename();
            $group = $file->getBasename('.php');
            $translations = require $absoluteFilePath;
            $translations = $this->prepare([$group => $translations]);
            $this->apiClient->files->upload($this->projectId, [
                'data' => base64_encode(json_encode($translations)),
                'filename' => ltrim(str_replace($this->basePath, '', $absoluteFilePath), '/'),
                'lang_iso' => $locale,
                'format' => 'json',
                'distinguish_by_file' => true, // This is important to distinguish between different files with the same key
                'replace_modified' => true,
                'convert_placeholders' => true,
                'slashn_to_linebreak' => true,
            ]);
        }
    }

    private function prepare(array $translations): array
    {
        $lokaliseTranslations = [];
        $keys = array_keys($translations);

        for ($i = 0; $i < count($keys); $i++) {
            $laravelKey = $keys[$i];
            $i18nKey = preg_replace("/:([\w\d]+)/", '{{$1}}', $laravelKey);
            $laravelValue = $translations[$laravelKey];

            if (is_array($laravelValue)) {
                $lokaliseTranslations[$i18nKey] = $this->prepare($laravelValue);
            } else {
                $translationWithReplacedVariableSyntax = preg_replace("/:([\w\d]+)/", '{{$1}}', $laravelValue);
                if (Str::contains($translationWithReplacedVariableSyntax, '|')) {
                    [$singular, $plural] = explode('|', $translationWithReplacedVariableSyntax);
                    $translationWithReplacedVariableSyntax = json_encode([
                        'one' => $singular,
                        'other' => $plural,
                    ]);
                }
                $lokaliseTranslations[$i18nKey] = $translationWithReplacedVariableSyntax;
            }
        }

        return $lokaliseTranslations;
    }

    private function replacePlaceholders($translation)
    {
        $json = json_decode($translation, true);
        if ($json && isset($json['one'], $json['other'])) {
            $translation = $json['one'].'|'.$json['other'];
        }
        // I get these strings and need to convert it to colon prefix variable names:
        // The [%1$s:attribute] field must be present when [%1$s:values] are present.
        //The :attribute field must be present when :values are present.
        $replaced = Str::of($translation)->replaceMatches('/\[\%1\$s:(\w+)\]/', ':$1')->__toString();

        return $replaced;
    }

    private function transformDottedStringsToArray(array $dottedStrings): array
    {
        $result = [];

        foreach ($dottedStrings as $dottedString => $translation) {
            $keys = explode('.', $dottedString);
            $current = &$result;

            while (count($keys) > 1) {
                $key = array_shift($keys);
                if (! isset($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }

            $lastKey = array_shift($keys);
            $current[$lastKey] = $translation;
        }

        return $result;
    }

    private function getLocales(): array
    {
        $result = $this->apiClient->languages->list($this->projectId);

        return array_map(fn ($language) => $language['lang_iso'], $result->body['languages']);
    }

    private function isEmptyTranslation(array $translation): bool
    {
        if (empty($translation['translation'])) {
            return true;
        }
        $json = json_decode($translation['translation'], true);
        if ($json && isset($json['one'], $json['other']) && empty($json['one']) && empty($json['other'])) {
            return true;
        }

        return false;
    }
}
