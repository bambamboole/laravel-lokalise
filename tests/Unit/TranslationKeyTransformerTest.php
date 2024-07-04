<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\TranslationKeyTransformer;
use PHPUnit\Framework\TestCase;

class TranslationKeyTransformerTest extends TestCase
{
    /** @dataProvider dottedToNested */
    public function testTransformDottedToNested(array $dotted, array $nested): void
    {
        $result = (new TranslationKeyTransformer())->transformDottedToNested($dotted);

        self::assertEquals($nested, $result);
    }

    public static function dottedToNested(): array
    {
        return [
            [
                [
                    'foo.bar' => 'baz',
                    'foo.baz.baz' => 'bar',
                ],
                [
                    'foo' => [
                        'bar' => 'baz',
                        'baz' => [
                            'baz' => 'bar',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testItThrowsAnExceptionIfAPriorSetKeyHasToBeNested()
    {
        $nested = (new TranslationKeyTransformer())->transformDottedToNested(
            [
                'foo.bar' => 'baz',
                'foo.bar.baz' => 'bar',
            ]
        );

        self::assertEquals(['foo' => ['bar' => 'baz']], $nested);
    }
}
