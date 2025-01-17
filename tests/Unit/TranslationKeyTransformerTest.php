<?php declare(strict_types=1);

namespace Bambamboole\LaravelLokalise\Tests\Unit;

use Bambamboole\LaravelLokalise\TranslationKeyTransformer;
use PHPUnit\Framework\TestCase;

class TranslationKeyTransformerTest extends TestCase
{
    /** @dataProvider dottedToNested */
    public function test_transform_dotted_to_nested(array $dotted, array $nested): void
    {
        $result = (new TranslationKeyTransformer)->transformDottedToNested($dotted);

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

    public function test_it_drops_invalid_keys_and_provides_them_via_skipped()
    {
        $transformer = new TranslationKeyTransformer;
        $nested = $transformer->transformDottedToNested(
            [
                'foo.bar' => 'baz',
                'foo.bar.baz' => 'bar',
            ]
        );

        self::assertEquals(['foo' => ['bar' => 'baz']], $nested);
        self::assertEquals([
            [
                'key' => 'foo.bar.baz',
                'value' => 'bar',
                'reason' => 'foo.bar already exists as a leaf node',
            ],
        ], $transformer->getSkipped());
    }
}
