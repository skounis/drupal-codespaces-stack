<?php

namespace Drupal\Tests\search_api_autocomplete\Unit;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Url;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\Tests\UnitTestCase;

// cspell:ignore fooo foooo ödön

/**
 * Tests the functionality of the suggestion factory class.
 *
 * @group search_api_autocomplete
 * @coversDefaultClass \Drupal\search_api_autocomplete\Suggestion\SuggestionFactory
 */
class SuggestionFactoryTest extends UnitTestCase {

  /**
   * Tests creating a suggestion from the suggested keys.
   *
   * @covers ::createFromSuggestedKeys
   */
  public function testCreateFromSuggestedKeys() {
    $factory = new SuggestionFactory('foo');

    $suggestion = $factory->createFromSuggestedKeys('bar');
    $this->assertEquals('bar', $suggestion->getSuggestedKeys());
    $this->assertNull($suggestion->getUserInput());
    $this->assertEquals('bar', $suggestion->getLabel());
    $this->assertNull($suggestion->getResultsCount());

    $suggestion = $factory->createFromSuggestedKeys('fooo');
    $this->assertEquals('fooo', $suggestion->getSuggestedKeys());
    $this->assertEquals('foo', $suggestion->getUserInput());
    $this->assertNull($suggestion->getLabel());
    $this->assertEquals('o', $suggestion->getSuggestionSuffix());
    $this->assertNull($suggestion->getResultsCount());

    $suggestion = $factory->createFromSuggestedKeys('foooo', 5);
    $this->assertEquals('foooo', $suggestion->getSuggestedKeys());
    $this->assertEquals('foo', $suggestion->getUserInput());
    $this->assertNull($suggestion->getLabel());
    $this->assertEquals('oo', $suggestion->getSuggestionSuffix());
    $this->assertEquals(5, $suggestion->getResultsCount());

    // Test case-insensitivity.
    $suggestion = $factory->createFromSuggestedKeys('Foooo', 5);
    $this->assertEquals('Foooo', $suggestion->getSuggestedKeys());
    $this->assertEquals('Foo', $suggestion->getUserInput());
    $this->assertNull($suggestion->getLabel());
    $this->assertEquals('oo', $suggestion->getSuggestionSuffix());
    $this->assertEquals(5, $suggestion->getResultsCount());

    // Test case-insensitivity with non-ASCII characters.
    Unicode::check();
    $factory = new SuggestionFactory('öd');
    $suggestion = $factory->createFromSuggestedKeys('Ödön', 5);
    $this->assertEquals('Ödön', $suggestion->getSuggestedKeys());
    $this->assertEquals('Öd', $suggestion->getUserInput());
    $this->assertNull($suggestion->getLabel());
    $this->assertEquals('ön', $suggestion->getSuggestionSuffix());
    $this->assertEquals(5, $suggestion->getResultsCount());
  }

  /**
   * Tests creating a suggestion from the suggested suffix.
   *
   * @covers ::createFromSuggestionSuffix
   */
  public function testCreateFromSuggestionSuffix() {
    $factory = new SuggestionFactory('foo');

    $suggestion = $factory->createFromSuggestionSuffix('bar');
    $this->assertEquals('foobar', $suggestion->getSuggestedKeys());
    $this->assertEquals('foo', $suggestion->getUserInput());
    $this->assertEquals('bar', $suggestion->getSuggestionSuffix());
    $this->assertNull($suggestion->getLabel());
    $this->assertNull($suggestion->getResultsCount());

    $suggestion = $factory->createFromSuggestionSuffix('o', 5);
    $this->assertEquals('fooo', $suggestion->getSuggestedKeys());
    $this->assertEquals('foo', $suggestion->getUserInput());
    $this->assertEquals('o', $suggestion->getSuggestionSuffix());
    $this->assertNull($suggestion->getLabel());
    $this->assertEquals(5, $suggestion->getResultsCount());
  }

  /**
   * Tests creating a URL suggestion.
   *
   * @covers ::createUrlSuggestion
   */
  public function testCreateUrlSuggestion() {
    $factory = new SuggestionFactory('foo');
    /** @var \Drupal\Core\Url $url */
    $url = $this->getMockBuilder(Url::class)
      ->disableOriginalConstructor()
      ->getMock();

    $suggestion = $factory->createUrlSuggestion($url, 'Foobar');
    $this->assertSame($url, $suggestion->getUrl());
    $this->assertNull($suggestion->getSuggestedKeys());
    $this->assertNull($suggestion->getUserInput());
    $this->assertEquals('Foobar', $suggestion->getLabel());
    $this->assertNull($suggestion->getResultsCount());
    $this->assertNull($suggestion->getRender());

    $render = ['foo' => 'bar'];
    $suggestion = $factory->createUrlSuggestion($url, NULL, $render);
    $this->assertSame($url, $suggestion->getUrl());
    $this->assertNull($suggestion->getSuggestedKeys());
    $this->assertNull($suggestion->getUserInput());
    $this->assertNull($suggestion->getLabel());
    $this->assertNull($suggestion->getResultsCount());
    $this->assertEquals($render, $suggestion->getRender());
  }

}
