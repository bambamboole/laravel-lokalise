<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\DTO\Translation;
use Bambamboole\LaravelLokalise\DTO\TranslationKey;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Bambamboole\LaravelLokalise\TranslationKeyFactory;
use Lokalise\Endpoints\Files;
use Lokalise\Endpoints\Keys;
use Lokalise\Endpoints\Languages;
use Lokalise\LokaliseApiClient;
use Lokalise\LokaliseApiResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LokaliseClientTest extends TestCase
{
    private Keys|MockObject $keys;

    private Files|MockObject $files;

    private Languages|MockObject $languages;

    protected function setUp(): void
    {
        $this->keys = $this->createMock(Keys::class);
        $this->files = $this->createMock(Files::class);
        $this->languages = $this->createMock(Languages::class);
    }

    public function test_get_keys()
    {
        $this->keys->expects(self::once())
            ->method('list')
            ->with('test', [
                'filter_filenames' => 'test',
                'include_translations' => 1,
                'limit' => 500,
                'page' => 1,
            ])
            ->willReturn($this->mockResponse([
                'keys' => [
                    [
                        'key_id' => 1,
                        'key_name' => ['web' => 'test'],
                        'translations' => [
                            ['language_iso' => 'en', 'translation' => 'test'],
                        ],
                    ],
                ],
            ]));

        $client = $this->createSubject();
        $result = $client->getKeys('test');

        $firstKey = $result[0];
        $this->assertInstanceOf(TranslationKey::class, $firstKey);
        $this->assertEquals(1, $firstKey->keyId);
        $this->assertEquals('test', $firstKey->key);
        $this->assertEquals(new Translation('en', 'test'), $firstKey->getTranslationForLocale('en'));
    }

    public function test_it_resolves_pagination_while_fetching_keys()
    {
        $this->keys->expects($counter = self::exactly(3))
            ->method('list')
            ->willReturnCallback(
                function ($_, array $options) use ($counter) {
                    self::assertEquals($options['page'], $counter->numberOfInvocations());
                    $keyCount = $counter->numberOfInvocations() === 3 ? 1 : 500;
                    $keys = array_map(
                        fn ($key) => [
                            'key_id' => 0,
                            'key_name' => ['web' => "test-{$counter->numberOfInvocations()}-{$key}"],
                            'translations' => [
                                ['language_iso' => 'en', 'translation' => 'test'],
                            ],
                        ],
                        range(0, $keyCount - 1),
                    );

                    return $this->mockResponse(['keys' => $keys]);
                }
            );

        $client = $this->createSubject();
        $keys = $client->getKeys('test');

        $this->assertCount(1001, $keys);
    }

    public function test_upload_file()
    {
        $this->files->expects(self::once())
            ->method('upload')
            ->with('test', [
                'data' => base64_encode('content'),
                'filename' => 'test.json',
                'lang_iso' => 'en',
                'format' => 'json',
                'convert_placeholders' => true,
                'replace_modified' => false,
                'distinguish_by_file' => true,
                'slashn_to_linebreak' => true,
                'cleanup_mode' => true,
            ]);

        $client = $this->createSubject();
        $client->uploadFile('content', 'test.json', 'en');
    }

    public function test_get_locales()
    {
        $this->languages->expects(self::once())
            ->method('list')
            ->with('test')
            ->willReturn($this->mockResponse([
                'languages' => [
                    ['lang_iso' => 'en'],
                    ['lang_iso' => 'de'],
                ],
            ]));

        $client = $this->createSubject();
        $locales = $client->getLocales();

        $this->assertEquals(['en', 'de'], $locales);
    }

    public function test_get_files()
    {
        $this->files->expects(self::once())
            ->method('list')
            ->with('test')
            ->willReturn($this->mockResponse(
                [
                    'project_id' => 'test',
                    'files' => [
                        [
                            'file_id' => 33,
                            'filename' => 'lang/%LANG_ISO%/modules.php',
                            'key_count' => 420,
                        ],
                        [
                            'file_id' => 36,
                            'filename' => 'lang/%LANG_ISO%/validation.php',
                            'key_count' => 666,
                        ],
                    ],
                ]
            ));

        $files = $this->createSubject()->getFiles();

        $this->assertCount(2, $files);
        $this->assertEquals(['lang/%LANG_ISO%/modules.php', 'lang/%LANG_ISO%/validation.php'], $files);
    }

    public function test_delete_keys()
    {
        $this->keys->expects(self::once())
            ->method('bulkDelete')
            ->with('test', ['keys' => [1]]);

        $this->createSubject()->deleteKeys([new TranslationKey(1, 'test', [], [])]);
    }

    private function mockResponse(array $data): LokaliseApiResponse|MockObject
    {
        $mock = $this->createMock(LokaliseApiResponse::class);
        $mock->body = $data;

        return $mock;
    }

    private function createSubject(): LokaliseClient
    {
        $baseClient = new LokaliseApiClient('test');
        $baseClient->keys = $this->keys;
        $baseClient->files = $this->files;
        $baseClient->languages = $this->languages;

        return new LokaliseClient($baseClient, new TranslationKeyFactory, 'test');
    }
}
