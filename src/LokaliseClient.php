<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Illuminate\Support\Str;
use Lokalise\LokaliseApiClient;

class LokaliseClient
{
    public function __construct(private LokaliseApiClient $apiClient, private string $projectId) {}

    public function getFileNames(): array
    {
        $result = $this->apiClient->files->list($this->projectId);

        return collect($result->body['files'])
            // Ignore the __unassigned__ file
            ->filter(fn ($file) => $file['filename'] !== '__unassigned__')
            // Ignore files without keys
            ->filter(fn ($file) => $file['key_count'] >= 1)
            // Get the filename
            ->map(fn ($file) => $file['filename'])
            ->toArray();
    }

    public function getKeys(string $fileName): array
    {
        $result = $this->apiClient->keys->list($this->projectId, [
            'filter_filenames' => $fileName,
            'include_translations' => 1,
            'limit' => 500,
        ]);

        $keys = $result->body['keys'];
        $preparedKeys = [];
        foreach ($keys as $key) {
            $keyName = Str::replace('::', '.', $key['key_name']['web']);

            $translations = collect($key['translations'])
                ->mapWithKeys(fn ($translation) => [$translation['language_iso'] => $translation['translation']]);

            $preparedKeys[$keyName] = $translations->toArray();
        }

        return $preparedKeys;
    }

    public function uploadFile(string $content, string $filename, string $locale): void
    {
        $this->apiClient->files->upload($this->projectId, [
            'data' => base64_encode($content),
            'filename' => $filename,
            'lang_iso' => $locale,
            'format' => 'json',
            'convert_placeholders' => true,
            'replace_modified' => true,
            'distinguish_by_file' => true,
            'slashn_to_linebreak' => true,
        ]);
    }

    public function getLocales(): array
    {
        $result = $this->apiClient->languages->list($this->projectId);

        return array_map(fn ($language) => $language['lang_iso'], $result->body['languages']);
    }
}
