<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\TranslationConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TranslationConverterTest extends TestCase
{
    #[DataProvider('provideTranslationsFromLokalise')]
    public function test_from_lokalise(string $in, string $out)
    {
        $this->assertEquals($out, TranslationConverter::fromLokalise($in));
    }

    public static function provideTranslationsFromLokalise(): array
    {
        return [
            [
                'in' => 'https://help.xentral.com/hc/de/articles/16164850902428-Arbeiten-mit-dem-%C3%9Cbertragungen-Modul',
                'out' => 'https://help.xentral.com/hc/de/articles/16164850902428-Arbeiten-mit-dem-%C3%9Cbertragungen-Modul',
            ],
            [
                'in' => 'https://community.xentral.com/hc/de/articles/360019652739-St%C3%BCckliste-verwenden',
                'out' => 'https://community.xentral.com/hc/de/articles/360019652739-St%C3%BCckliste-verwenden',
            ],
            [
                'in' => 'The [%1$s:attribute] field must be present & valid.',
                'out' => 'The :attribute field must be present & valid.',
            ],
            [
                'in' => 'Visit https://example.com/%C3%BCber and check the [%1$s:field] value.',
                'out' => 'Visit https://example.com/%C3%BCber and check the :field value.',
            ],
        ];
    }

    #[DataProvider('provideTranslationsToLokalise')]
    public function test_to_lokalise(string $in, string $out)
    {
        $this->assertEquals($out, TranslationConverter::toLokalise($in));
    }

    public static function provideTranslationsToLokalise(): array
    {
        return [
            [
                'in' => 'The :attribute must be accepted.',
                'out' => 'The {{attribute}} must be accepted.',
            ],
            [
                'in' => 'Page :page of {nb}',
                'out' => 'Page {{page}} of {nb}',
            ],
            [
                'in' => 'e.g. discount {ZAHLUNGSZIELSKONTO}% within {ZAHLUNGSZIELTAGESKONTO} days.',
                'out' => 'e.g. discount {ZAHLUNGSZIELSKONTO}% within {ZAHLUNGSZIELTAGESKONTO} days.',
            ],
            [
                'in' => 'The content can be accessed via the variable {PASSWORT}.',
                'out' => 'The content can be accessed via the variable {PASSWORT}.',
            ],
            [
                'in' => '<table style="width:100%;"><tr><td width="100%">:prefix should convert</td></tr></table>',
                'out' => '<table style="width:100%;"><tr><td width="100%">{{prefix}} should convert</td></tr></table>',
            ],
            [
                'in' => '<div style="color:red; width:50%;">The :attribute field is :status.</div>',
                'out' => '<div style="color:red; width:50%;">The {{attribute}} field is {{status}}.</div>',
            ],
        ];
    }
}
