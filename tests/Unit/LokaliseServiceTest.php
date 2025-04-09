<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Bambamboole\LaravelLokalise\LokaliseService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('provideForDisabledKeyConversion')]
    public function test_converting_keys_can_be_disabled(array $in, array $out)
    {
        self:
        self::assertEquals($out, $this->createSubject(false)->prepare($in));
    }

    public static function provideForDisabledKeyConversion(): array
    {
        return [
            [
                'in' => ['with :variable' => 'value'],
                'out' => ['with :variable' => 'value'],
            ],
        ];
    }

    #[DataProvider('provideForEnabledKeyConversion')]
    public function test_converting_keys_can_be_enabled(array $in, array $out)
    {
        self:
        self::assertEquals($out, $this->createSubject(false, true)->prepare($in));
    }

    public static function provideForEnabledKeyConversion(): array
    {
        return [
            [
                'in' => ['with :variable' => 'value'],
                'out' => ['with {{variable}}' => 'value'],
            ],
        ];
    }

    private function createSubject(bool $skipJsonFiles = true, bool $convertKeys = false): LokaliseService
    {
        return new LokaliseService($this->client, $this->repo, $this->basePath, $skipJsonFiles, $convertKeys);
    }
}
