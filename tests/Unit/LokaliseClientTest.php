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

    public function testGetKeys()
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

        $this->assertEquals([new TranslationKey(1, 'test', [new Translation('en', 'test')])], $result);
    }

    public function testItResolvesPaginationWhileFetchingKeys()
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

    public function testUploadFile()
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

    public function testGetLocales()
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
