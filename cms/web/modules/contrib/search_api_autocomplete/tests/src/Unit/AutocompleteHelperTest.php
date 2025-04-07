<?php

namespace Drupal\Tests\search_api_autocomplete\Unit;

use Drupal\search_api_autocomplete\Utility\AutocompleteHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Tests various utility methods of the Search API Autocomplete module.
 *
 * @group search_api_autocomplete
 *
 * @coversDefaultClass \Drupal\search_api_autocomplete\Utility\AutocompleteHelper
 */
class AutocompleteHelperTest extends UnitTestCase {

  /**
   * Tests splitting of user input into complete and incomplete words.
   *
   * @param string $keys
   *   The processed keywords.
   * @param string[] $expected
   *   The expected result of splitting the given user input.
   *
   * @covers ::splitKeys
   *
   * @dataProvider splitKeysTestDataProvider
   */
  public function testSplitKeys(string $keys, array $expected): void {
    $service = new AutocompleteHelper();
    $this->assertEquals($expected, $service->splitKeys($keys));
  }

  /**
   * Data provider for testSplitKeys().
   */
  public static function splitKeysTestDataProvider(): array {
    $data = [];
    $data['simple word'] = ['word', ['', 'word']];
    $data['simple word with dash'] = ['word-dash', ['', 'word-dash']];
    $data['trailing whitespace'] = ['word-dash ', ['word-dash', '']];
    $data['quoted first word'] = ['"word" other', ['"word"', 'other']];
    $data['quoted word in middle'] = ['word "other" word', ['word "other"', 'word']];
    $data['quoted last word'] = ['word "other"', ['word "other"', '']];

    return $data;
  }

}
