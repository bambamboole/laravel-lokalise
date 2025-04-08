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

    public function test_it_can_extract_locales_from_files()
    {
        $locales = $this->createSubject()->getLocales();

        self::assertEquals(['de', 'en'], $locales);
    }

    public function test_it_can_get_translation_files()
    {
        $files = $this->createSubject()->getTranslationFiles();

        self::assertCount(4, $files);
    }

    public function test_it_can_get_translation_files_filtered_by_locale()
    {
        $files = $this->createSubject()->getTranslationFiles('en');

        self::assertCount(2, $files);
    }

    public function test_it_can_get_translation_files_filtered_by_type()
    {
        $files = $this->createSubject()->getTranslationFiles(type: TranslationType::PHP);

        self::assertCount(2, $files);
    }

    public function test_it_merges_correctly_on_save()
    {
        $repo = $this->createSubject();

        $repo->saveTranslations(collect([
            new Translation('de', 'validation.accepted', 'foo'),
        ]));

        $translations = $repo->getTranslations('de/validation.php');

        self::assertEquals($translations['validation.accepted'], 'foo');
    }

    public function test_it_does_not_escape_slashes()
    {
        $repo = $this->createSubject();

        $repo->saveTranslations(collect([
            new Translation('de', 'key with /', 'value with /'),
        ]));

        $translations = $repo->getTranslations('de.json');

        self::assertEquals($translations['key with /'], 'value with /');
    }

    public function test_it_sort_keys_by_alphabet()
    {
        $repo = $this->createSubject();

        $repo->saveTranslations(collect([
            new Translation('de', 'foo.b', 'b translation'),
            new Translation('de', 'foo.a', 'a translation'),
        ]));

        $translations = $repo->getTranslations('de/foo.php');

        self::assertSame($translations, [
            'foo.a' => 'a translation',
            'foo.b' => 'b translation',
        ]);
    }

    private function createSubject(): LocalTranslationRepository
    {
        return new LocalTranslationRepository(new Filesystem, dirname(__DIR__).'/fixtures');
    }
}
