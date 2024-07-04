<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Illuminate\Support\Str;
use Lokalise\LokaliseApiClient;

class LokaliseClient
{
    public function __construct(private LokaliseApiClient $apiClient, private string $projectId) {}

    public function getFileNames(): array
    {
        $files = [];
        $page = 0;
        do {
            $page++;
            $result = $this->apiClient->files->list($this->projectId, [
                'limit' => 500,
                'page' => $page,
            ]);
            $newFiles = $result->body['files'];
            $files = array_merge($files, $newFiles);
        } while (count($newFiles) === 500);

        return collect($files)
            // Ignore the __unassigned__ file
            ->filter(fn ($file) => $file['filename'] !== '__unassigned__')
            // Ignore files without keys
            ->filter(fn ($file) => $file['key_count'] >= 1)
            // Get the filename
            ->map(fn ($file) => $file['filename'])
            // Reset the keys
            ->values()
            // Convert to array
            ->toArray();
    }

    public function getKeys(?string $fileName = null): array
    {
        $options = [
            'limit' => 500,
            'include_translations' => 1,
        ];
        if ($fileName !== null) {
            $options['filter_filenames'] = $fileName;
        }

        $keys = [];
        $page = 0;
        do {
            $page++;
            $options['page'] = $page;
            $result = $this->apiClient->keys->list($this->projectId, $options);
            $newKeys = $result->body['keys'];
            $keys = array_merge($keys, $newKeys);
        } while (count($newKeys) === 500);

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
