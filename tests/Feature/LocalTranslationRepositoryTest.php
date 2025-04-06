<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Feature;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\Models\Translation;
use Bambamboole\LaravelTranslationDumper\TranslationType;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class LocalTranslationRepositoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {

        self::tearDownAfterClass();
        $fs = new Filesystem;
        $fs->copyDirectory(dirname(__DIR__).'/fixtures', dirname(__DIR__).'/fixtures_backup');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(dirname(__DIR__).'/fixtures_backup')) {
            $fs = new Filesystem;
            $fs->deleteDirectory(dirname(__DIR__).'/fixtures');
            $fs->copyDirectory(dirname(__DIR__).'/fixtures_backup', dirname(__DIR__).'/fixtures');
            $fs->deleteDirectory(dirname(__DIR__).'/fixtures_backup');
        }
    }

    public function testItCanExtractLocalesFromFiles()
    {
        $locales = $this->createSubject()->getLocales();

        self::assertEquals(['de', 'en'], $locales);
    }

    public function testItCanGetTranslationFiles()
    {
        $files = $this->createSubject()->getTranslationFiles();

        self::assertCount(4, $files);
    }

    public function testItCanGetTranslationFilesFilteredByLocale()
    {
        $files = $this->createSubject()->getTranslationFiles('en');

        self::assertCount(2, $files);
    }

    public function testItCanGetTranslationFilesFilteredByType()
    {
        $files = $this->createSubject()->getTranslationFiles(type: TranslationType::PHP);

        self::assertCount(2, $files);
    }

    public function testItMergesCorrectlyOnSave()
    {
        $repo = $this->createSubject();

        $repo->saveTranslations(collect([
            new Translation('de', 'validation.accepted', 'foo'),
        ]));

        $translations = $repo->getTranslations('de/validation.php');

        self::assertEquals($translations['validation.accepted'], 'foo');
    }

    private function createSubject(): LocalTranslationRepository
    {
        return new LocalTranslationRepository(new Filesystem, dirname(__DIR__).'/fixtures');
    }
}
