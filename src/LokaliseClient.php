<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

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
        do {
            $page++;
            $options['page'] = $page;
            $result = $this->apiClient->keys->list($this->projectId, $options);
            $newKeys = $result->body['keys'];
            $keys = array_merge($keys, $newKeys);
        } while (count($newKeys) === 500);

        return array_map(fn ($key) => $this->translationKeyFactory->createFromLokalise($key), $keys);
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
