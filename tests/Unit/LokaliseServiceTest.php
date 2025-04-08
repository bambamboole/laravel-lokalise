<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\LocalTranslationRepository;
use Bambamboole\LaravelLokalise\LokaliseClient;
use Bambamboole\LaravelLokalise\LokaliseService;
use PHPUnit\Framework\TestCase;

class LokaliseServiceTest extends TestCase
{
    public function test_it_preserves_curly_braces_formats()
    {
        $client = $this->createMock(LokaliseClient::class);
        $repository = $this->createMock(LocalTranslationRepository::class);
        $service = new LokaliseService($client, $repository, __DIR__);

        $result = $service->prepare([
            // Basic Laravel placeholders
            'accepted' => 'The :attribute must be accepted.',

            // Variable within curly braces - should not be converted
            'page_page_nb' => 'Page :page of {nb}',

            // Complex variables in curly braces - should not be converted
            'eg_discount' => 'e.g. discount {ZAHLUNGSZIELSKONTO}% within {ZAHLUNGSZIELTAGESKONTO} days.',

            // Variable in curly braces with text explanation - should not convert the curly brace content
            'content_password' => 'The content can be accessed via the variable {PASSWORT}.',

            // HTML with styling that contains colons - should not convert CSS attributes
            'table_style' => '<table style="width:100%;"><tr><td width="100%">:prefix should convert</td></tr></table>',

            // Mixed example with both HTML styling and regular Laravel variables
            'mixed_html' => '<div style="color:red; width:50%;">The :attribute field is :status.</div>',
        ]);

        $this->assertEquals('The {{attribute}} must be accepted.', $result['accepted']);
        // Verify basic Laravel placeholders are converted
        $this->assertEquals('The {{attribute}} must be accepted.', $result['accepted']);

        // Verify variable within curly braces are not converted
        $this->assertEquals('Page {{page}} of {nb}', $result['page_page_nb']);

        // Verify complex variables in curly braces are not converted
        $this->assertEquals('e.g. discount {ZAHLUNGSZIELSKONTO}% within {ZAHLUNGSZIELTAGESKONTO} days.', $result['eg_discount']);

        // Verify variable in curly braces with text explanation is not converted
        $this->assertEquals('The content can be accessed via the variable {PASSWORT}.', $result['content_password']);

        // Verify HTML with styling that contains colons is not converted in attributes
        $this->assertEquals('<table style="width:100%;"><tr><td width="100%">{{prefix}} should convert</td></tr></table>', $result['table_style']);

        // Verify mixed example works correctly
        $this->assertEquals('<div style="color:red; width:50%;">The {{attribute}} field is {{status}}.</div>', $result['mixed_html']);
    }
}
