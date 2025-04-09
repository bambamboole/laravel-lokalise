<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LokaliseServiceTest extends TestCase
{
    private string $basePath;

    private MockObject|LokaliseClient $client;

    private LocalTranslationRepository $repo;

    protected function setUp(): void
    {
        $this->basePath = dirname(__DIR__).'/fixtures';
        $this->client = $this->createMock(LokaliseClient::class);
        $this->repo = new LocalTranslationRepository(new Filesystem, $this->basePath);
    }

    public function test_it_skips_json_files_if_configured()
    {
        $this->client->expects(self::once())
            ->method('getLocales')
            ->willReturn(['en', 'de']);
        $this->client->expects(self::exactly(2))
            ->method('uploadFile')
            ->with(self::anything(), self::callback(fn ($file) => Str::endsWith($file, '.php')));

        $this->createSubject()->uploadTranslations();
    }

    public function test_it_includes_json_files_if_configured()
    {
        $this->client->expects(self::once())
            ->method('getLocales')
            ->willReturn(['en', 'de']);
        $this->client->expects(self::exactly(4))
            ->method('uploadFile')
            ->with(
                self::anything(),
                self::callback(fn ($file) => Str::endsWith($file, '.php') || Str::endsWith($file, '.json')),
            );

        $this->createSubject(false)->uploadTranslations();
    }

    private function createSubject(bool $skipJsonFiles = true): LokaliseService
    {
        return new LokaliseService($this->client, $this->repo, $this->basePath, $skipJsonFiles);
    }
}
