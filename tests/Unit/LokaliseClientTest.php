<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\LokaliseClient;
use Lokalise\Endpoints\Keys;
use Lokalise\LokaliseApiClient;
use Lokalise\LokaliseApiResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LokaliseClientTest extends TestCase
{
    private Keys|MockObject $keys;

    protected function setUp(): void
    {
        $this->keys = $this->createMock(Keys::class);
    }

    public function testGetKeys()
    {
        $this->keys->expects($this->once())
            ->method('list')
            ->with('test', [
                'filter_filenames' => 'test',
                'include_translations' => 1,
                'limit' => 500,
            ])
            ->willReturn($this->mockResponse([
                'keys' => [
                    [
                        'key_name' => ['web' => 'test'],
                        'translations' => [
                            ['language_iso' => 'en', 'translation' => 'test'],
                        ],
                    ],
                ],
            ]));

        $client = $this->createSubject();
        $result = $client->getKeys('test');

        $this->assertEquals(['test' => ['en' => 'test']], $result);
    }

    private function createSubject(): LokaliseClient
    {
        $baseClient = new LokaliseApiClient('test');
        $baseClient->keys = $this->keys;

        return new LokaliseClient($baseClient, 'test');
    }

    private function mockResponse(array $data): LokaliseApiResponse|MockObject
    {
        $mock = $this->createMock(LokaliseApiResponse::class);
        $mock->body = $data;

        return $mock;
    }
}
