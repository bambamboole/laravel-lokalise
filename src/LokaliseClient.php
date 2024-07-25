<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\DTO\TranslationKey;
use Illuminate\Support\Str;
use Lokalise\Exceptions\LokaliseResponseException;
use Lokalise\LokaliseApiClient;

class LokaliseClient
{
    public function __construct(
        private readonly LokaliseApiClient $apiClient,
        private readonly TranslationKeyFactory $translationKeyFactory,
        private readonly string $projectId,
    ) {}

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
        try {

            do {
                $page++;
                $options['page'] = $page;
                $result = $this->apiClient->keys->list($this->projectId, $options);
                $newKeys = $result->body['keys'];
                $keys = array_merge($keys, $newKeys);
            } while (count($newKeys) === 500);
        } catch (LokaliseResponseException $e) {
            // Lokalise throws an error if you want to list keys for a non-existing file
            // We will catch that and just return an empty array
            if (Str::contains($e->getMessage(), '`filter_filenames` parameter has invalid values')) {
                return [];
            }
            throw $e;
        }

        return array_map(fn ($key) => $this->translationKeyFactory->createFromLokalise($key), $keys);
    }

    public function uploadFile(string $content, string $filename, string $locale, bool $cleanup = true): void
    {
        $this->apiClient->files->upload($this->projectId, [
            'data' => base64_encode($content),
            'filename' => $filename,
            'lang_iso' => $locale,
            'format' => 'json',
            'convert_placeholders' => true,
            'replace_modified' => false,
            'distinguish_by_file' => true,
            'slashn_to_linebreak' => true,
            'cleanup_mode' => $cleanup,
        ]);
    }

    public function getLocales(): array
    {
        $result = $this->apiClient->languages->list($this->projectId);

        return array_map(fn ($language) => $language['lang_iso'], $result->body['languages']);
    }

    public function getFiles(): array
    {
        $result = $this->apiClient->files->list($this->projectId);

        return array_map(fn ($file) => $file['filename'], $result->body['files']);
    }

    /** @param TranslationKey[] $keys */
    public function deleteKeys(array $keys): void
    {
        $this->apiClient
            ->keys
            ->bulkDelete(
                $this->projectId,
                [
                    'keys' => array_map(fn (TranslationKey $key) => $key->keyId, $keys),
                ],
            );
    }
}
