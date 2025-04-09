<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise;

use Bambamboole\LaravelLokalise\Models\Translation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lokalise\Exceptions\LokaliseResponseException;
use Lokalise\LokaliseApiClient;
use Symfony\Component\Console\Helper\ProgressBar;

class LokaliseClient
{
    private ?ProgressBar $progressBar = null;

    public function __construct(
        private readonly LokaliseApiClient $apiClient,
        private readonly string $projectId,
    ) {}

    public function withProgressbar(ProgressBar $progressBar): self
    {
        $this->progressBar = $progressBar;
        $this->progressBar->setMaxSteps((int) ($this->getLokaliseKeyCount() / 500));

        return $this;
    }

    public function getTranslations(?string $fileName = null): Collection
    {
        $options = [
            'limit' => 500,
            'include_translations' => true,
        ];
        if ($fileName !== null) {
            $options['filter_filenames'] = $fileName;
        }

        $keys = [];
        $page = 0;
        try {
            $this->progressBar?->start();

            do {
                $page++;
                $options['page'] = $page;
                $result = $this->apiClient->keys->list($this->projectId, $options);
                $newKeys = $result->body['keys'];
                $keys = array_merge($keys, $newKeys);
                $this->progressBar?->advance();
            } while (count($newKeys) === 500);
        } catch (LokaliseResponseException $e) {
            // Lokalise throws an error if you want to list keys for a non-existing file
            // We will catch that and just return an empty array
            if (Str::contains($e->getMessage(), '`filter_filenames` parameter has invalid values')) {
                return collect();
            }
            throw $e;
        }
        $this->progressBar?->finish();
        $this->progressBar = null;

        $translations = collect();
        foreach ($keys as $data) {
            $key = Str::replace('::', '.', $data['key_name']['web']);
            $translations = $translations->merge(
                array_map(
                    fn (array $translation) => new Translation(
                        $translation['language_iso'],
                        $key,
                        TranslationConverter::fromLokalise($translation['translation']),
                    ),
                    $data['translations'] ?? [],
                )
            );
        }

        return $translations->filter();
    }

    public function uploadFile(string $content, string $filename, string $locale, bool $cleanup = true, bool $replace = false): void
    {
        $this->apiClient->files->upload($this->projectId, [
            'data' => base64_encode($content),
            'filename' => $filename,
            'lang_iso' => $locale,
            'format' => 'json',
            'convert_placeholders' => true,
            'replace_modified' => $replace,
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
        $result = $this->apiClient->files->list($this->projectId, ['limit' => 5000]);

        return array_map(fn ($file) => $file['filename'], $result->body['files']);
    }

    public function getLokaliseKeyCount(): int
    {
        $result = $this->apiClient->files->list($this->projectId, ['limit' => 5000]);

        return array_sum(array_map(fn ($file) => $file['key_count'], $result->body['files']));
    }

    public function deleteKeys(array $keys): void
    {
        $this->apiClient
            ->keys
            ->bulkDelete(
                $this->projectId,
                [
                    'keys' => $keys,
                ],
            );
    }
}
