<?php

namespace Drupal\Tests\simple_sitemap\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Drupal\simple_sitemap\Queue\QueueWorker;

/**
 * Tests Simple XML Sitemap functional integration.
 *
 * @group simple_sitemap
 */
class SimplesitemapTest extends SimplesitemapTestBase {

  /**
   * Verify sitemap.xml has the link to the front page after first generation.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testInitialGeneration() {
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('urlset');
    $this->assertSession()->responseContains(
      Url::fromRoute('<front>')->setAbsolute()->toString()
    );
    $this->assertSession()->responseContains('1.0');
    $this->assertSession()->responseContains('daily');
  }

  /**
   * Tests if a disabled sitemap returns a 404 and has no chunks.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDisableSitemap() {
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->statusCodeEquals(200);
    $sitemap = SimpleSitemap::load('default');
    $sitemap->disable()->save();
    $this->assertEmpty($sitemap->fromPublishedAndUnpublished()->getChunkCount());
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests if a deleted sitemap returns a 404.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDeleteSitemap() {
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->statusCodeEquals(200);
    $sitemap = SimpleSitemap::load('default');
    $sitemap->delete();
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests if a sitemap with no links returns a 404 and has no chunks.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testEmptySitemap() {
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->assertEmpty(SimpleSitemap::load('default')->fromPublishedAndUnpublished()->getChunkCount());
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Test cached sitemap.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testCachedSitemap() {
    $this->generator->customLinkManager()->add(
      '/node/' . $this->node->id(),
      ['priority' => 0.2, 'changefreq' => 'monthly']
    );
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->responseHeaderContains('X-Drupal-Cache-Tags', 'sitemap');
  }

  /**
   * Test custom link.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAddCustomLink() {
    $this->generator->customLinkManager()->add(
      '/node/' . $this->node->id(),
      ['priority' => 0.2, 'changefreq' => 'monthly']
    );
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.2');
    $this->assertSession()->responseContains('monthly');

    $this->drupalLogin($this->privilegedUser);

    $this->drupalGet('admin/config/search/simplesitemap/custom');
    $this->assertSession()->pageTextContains(
      '/node/' . $this->node->id() . ' 0.2 monthly'
    );

    $this->generator->customLinkManager()->add(
      '/node/' . $this->node->id(),
      ['changefreq' => 'yearly']
    );
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet('admin/config/search/simplesitemap/custom');
    $this->assertSession()->pageTextContains(
      '/node/' . $this->node->id() . ' 0.5 yearly'
    );
  }

  /**
   * Test default settings of custom links.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAddCustomLinkDefaults() {
    $this->generator->customLinkManager()
      ->remove()->add('/node/' . $this->node->id());
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('changefreq');
  }

  /**
   * Tests locks.
   */
  public function testLocking() {
    $this->generator->customLinkManager()
      ->remove()->add('/node/' . $this->node->id());
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->drupalLogin($this->createUser(['administer sitemap settings']));

    $this->drupalGet('/admin/config/search/simplesitemap/settings');
    $this->submitForm(['simple_sitemap_regenerate_now' => TRUE], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->pageTextNotContains('Unable to acquire a lock for sitemap generation.');

    \Drupal::lock()->acquire(QueueWorker::LOCK_ID);
    $this->submitForm(['simple_sitemap_regenerate_now' => TRUE], 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->pageTextContainsOnce('Unable to acquire a lock for sitemap generation.');
  }

  /**
   * Test removing custom paths from the sitemap settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testRemoveCustomLinks() {

    // Test removing one custom path from the sitemap.
    $this->generator->customLinkManager()
      ->add('/node/' . $this->node->id())
      ->remove('/node/' . $this->node->id());
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    // Test removing all custom paths from the sitemap.
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains(
      Url::fromRoute('<front>')->setAbsolute()->toString()
    );
  }

  /**
   * Tests setting bundle settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @todo Add form tests
   */
  public function testSetBundleSettings() {
    $this->assertFalse($this->generator->entityManager()->bundleIsIndexed('node', 'page'));

    // Index new bundle.
    $this->generator->customLinkManager()->remove();
    $this->generator->entityManager()->setBundleSettings('node', 'page', [
      'index' => TRUE,
      'priority' => 0.5,
      'changefreq' => 'hourly',
    ]);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseContains('hourly');

    $this->assertTrue($this->generator->entityManager()->bundleIsIndexed('node', 'page'));

    // Only change bundle priority.
    $this->generator->entityManager()->setBundleSettings('node', 'page', ['priority' => 0.9]);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('0.5');
    $this->assertSession()->responseContains('0.9');

    // Only change bundle changefreq.
    $this->generator->entityManager()->setBundleSettings('node', 'page', ['changefreq' => 'daily']);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('hourly');
    $this->assertSession()->responseContains('daily');

    // Remove changefreq setting.
    $this->generator->entityManager()->setBundleSettings('node', 'page', ['changefreq' => '']);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('changefreq');
    $this->assertSession()->responseNotContains('daily');

    // Index two bundles.
    $this->drupalCreateContentType(['type' => 'blog']);

    $node3 = $this->createNode(['title' => 'Node3', 'type' => 'blog']);
    $this->generator->entityManager()
      ->setBundleSettings('node', 'page', ['index' => TRUE])
      ->setBundleSettings('node', 'blog', ['index' => TRUE]);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('node/' . $node3->id());

    // Set bundle 'index' setting to false.
    $this->generator->entityManager()
      ->setBundleSettings('node', 'page', ['index' => FALSE])
      ->setBundleSettings('node', 'blog', ['index' => FALSE]);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);

    $this->assertSession()->responseNotContains('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('node/' . $node3->id());
  }

  /**
   * Test default settings of bundles.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetBundleSettingsDefaults() {
    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('changefreq');
  }

  /**
   * Test link count.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLinkCount() {
    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalLogin($this->createUser(['administer sitemap settings']));
    $this->drupalGet('admin/config/search/simplesitemap');
    $link_count_elements = $this->xpath('//*[@id="simple-sitemap-status-form"]//table/tbody/tr/td[4]');
    $this->assertSame('2', $link_count_elements[0]->getText());

    $this->createNode(['title' => 'Another node', 'type' => 'page']);
    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    $this->drupalLogin($this->createUser(['administer sitemap settings']));
    $this->drupalGet('admin/config/search/simplesitemap');
    $link_count_elements = $this->xpath('//*[@id="simple-sitemap-status-form"]//table/tbody/tr/td[4]');
    $this->assertSame('3', $link_count_elements[0]->getText());
  }

  /**
   * Test the lastmod parameter in different scenarios.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLastmod() {
    // Entity links should have 'lastmod'.
    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('lastmod');

    // Entity custom links should have 'lastmod'.
    $this->generator->entityManager()->setBundleSettings('node', 'page', ['index' => FALSE]);
    $this->generator->customLinkManager()->add('/node/' . $this->node->id());
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('lastmod');

    // Non-entity custom links should not have 'lastmod'.
    $this->generator->customLinkManager()->remove()->add('/');
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('lastmod');
  }

  /**
   * Tests the duplicate setting.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testRemoveDuplicatesSetting() {
    $this->generator->entityManager()->setBundleSettings('node', 'page');

    $this->generator->customLinkManager()
      ->add('/node/1')
      ->add('/node/2?foo=bar');

    $this->generator->saveSetting('remove_duplicates', TRUE)
      ->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);

    // Make sure the duplicate custom link is not included.
    $this->assertUniqueTextWorkaround('node/' . $this->node->id());

    // Make sure a duplicate path with a different query is included.
    $this->assertNoUniqueTextWorkaround('node/' . $this->node2->id());

    $this->generator->saveSetting('remove_duplicates', FALSE)
      ->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertNoUniqueTextWorkaround('node/' . $this->node->id());
  }

  /**
   * Test max links setting and the sitemap index.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMaxLinksSetting() {
    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->customLinkManager()->remove();
    $this->generator->saveSetting('max_links', 1)
      ->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('sitemap.xml?page=1');
    $this->assertSession()->responseContains('sitemap.xml?page=2');

    $this->drupalGet('sitemap.xml', ['query' => ['page' => 1]]);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());

    $this->drupalGet('sitemap.xml', ['query' => ['page' => 2]]);
    $this->assertSession()->responseContains('node/' . $this->node2->id());
    $this->assertSession()->responseContains('0.5');
    $this->assertSession()->responseNotContains('node/' . $this->node->id());
  }

  // phpcs:ignore @todo testGenerateDurationSetting

  /**
   * Test setting the base URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBaseUrlSetting() {
    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->saveSetting('base_url', 'http://base_url_test')
      ->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('http://base_url_test');

    // Set base URL in the sitemap index.
    $this->generator->saveSetting('max_links', 1)
      ->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('http://base_url_test/sitemap.xml?page=1');
  }

  /**
   * Test overriding of bundle settings for a single entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @todo Use form testing instead of responseContains().
   */
  public function testSetEntityInstanceSettings() {
    $this->generator->entityManager()
      ->setBundleSettings('node', 'page')
      ->setEntityInstanceSettings('node', $this->node->id(), [
        'priority' => 0.1,
        'changefreq' => 'never',
      ])
      ->setEntityInstanceSettings('node', $this->node2->id(), ['index' => FALSE]);
    $this->generator->customLinkManager()->remove();
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    // Test sitemap result.
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.1');
    $this->assertSession()->responseContains('never');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());
    $this->assertSession()->responseNotContains('0.5');

    $this->drupalLogin($this->privilegedUser);

    // Test UI changes.
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->assertSession()->responseContains('<option value="0.1" selected="selected">0.1</option>');
    $this->assertSession()->responseContains('<option value="never" selected="selected">never</option>');

    // Test database changes.
    $this->assertEquals(1, $this->getOverridesCount('node', $this->node->id()));

    $this->generator->entityManager()->setBundleSettings('node', 'page', [
      'priority' => 0.1,
      'changefreq' => 'never',
    ]);
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    // Test sitemap result.
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());
    $this->assertSession()->responseContains('0.1');
    $this->assertSession()->responseContains('never');
    $this->assertSession()->responseNotContains('node/' . $this->node2->id());
    $this->assertSession()->responseNotContains('0.5');

    // Test UI changes.
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->assertSession()->responseContains('<option value="0.1" selected="selected">0.1 (default)</option>');
    $this->assertSession()->responseContains('<option value="never" selected="selected">never (default)</option>');

    // Test if entity override has been removed from database after its equal to
    // its bundle settings.
    $this->assertEquals(0, $this->getOverridesCount('node', $this->node->id()));

    // Assert that creating a new content type doesn't remove the overrides.
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->submitForm(['simple_sitemap[default][index]' => '0'], 'Save');
    $this->assertEquals(1, $this->getOverridesCount('node', $this->node->id()));
    // Create a new content type.
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm([
      'name' => 'simple_sitemap_type',
      'type' => 'simple_sitemap_type',
      'simple_sitemap[default][index]' => '0',
    ], 'Save');
    // The entity override from the other content type should not be affected.
    $this->assertEquals(1, $this->getOverridesCount('node', $this->node->id()));

    // Assert that removing the other content type doesn't remove the overrides.
    $this->drupalGet('admin/structure/types/manage/simple_sitemap_type/delete');
    $this->submitForm([], 'Delete');
    $this->assertEquals(1, $this->getOverridesCount('node', $this->node->id()));
  }

  /**
   * Returns the number of entity overrides for the given entity type/ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   * @param string|array $variant
   *   A particular variant or an array of variants.
   *
   * @return int
   *   The number of overrides for the given entity type ID and entity ID.
   */
  protected function getOverridesCount($entity_type_id, $entity_id, $variant = 'default') {
    return $this->database->select('simple_sitemap_entity_overrides', 'o')
      ->fields('o', ['inclusion_settings'])
      ->condition('o.entity_type', $entity_type_id)
      ->condition('o.entity_id', $entity_id)
      ->condition('o.type', $variant)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Tests that a page does not break if an entity has its id set.
   */
  public function testNewEntityWithIdSet() {
    $new_node = Node::create([
      'nid' => random_int(5, 10),
      'type' => 'page',
    ]);
    // Assert that the form does not break if an entity has an id but is not
    // saved.
    // @see https://www.drupal.org/project/simple_sitemap/issues/3079897
    \Drupal::service('entity.form_builder')->getForm($new_node);
  }

  /**
   * Test indexing an atomic entity (here: a user)
   */
  public function testAtomicEntityIndexation() {
    $user_id = $this->privilegedUser->id();
    $this->generator->entityManager()->setBundleSettings('user');
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('user/' . $user_id);

    user_role_grant_permissions('anonymous', ['access user profiles']);

    // @todo Not pretty.
    drupal_flush_all_caches();

    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('user/' . $user_id);
  }

  // phpcs:ignore @todo Test indexing menu.
  // phpcs:ignore @todo Test deleting a bundle.

  /**
   * Test disabling sitemap support for an entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testDisableEntityType() {
    $this->generator->entityManager()
      ->setBundleSettings('node', 'page')
      ->disableEntityType('node');

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->pageTextNotContains('Simple XML Sitemap');

    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    $this->assertFalse($this->generator->entityManager()->entityTypeIsEnabled('node'));
  }

  /**
   * Test enabling sitemap support for an entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Behat\Mink\Exception\ExpectationException
   *
   * @todo Test admin/config/search/simplesitemap/entities form.
   */
  public function testEnableEntityType() {
    $this->generator->entityManager()
      ->disableEntityType('node')
      ->enableEntityType('node')
      ->setBundleSettings('node', 'page');

    $this->drupalLogin($this->privilegedUser);
    $this->drupalGet('admin/structure/types/manage/page');
    $this->assertSession()->pageTextContains('Simple XML Sitemap');

    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    $this->assertTrue($this->generator->entityManager()->entityTypeIsEnabled('node'));
  }

  // phpcs:ignore @todo testSitemapLanguages.

  /**
   * Test adding and removing sitemap variants.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testSitemapVariants() {

    // Test adding a variant.
    SimpleSitemap::create(['id' => 'test', 'type' => 'default_hreflang'])->save();

    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    $sitemaps = SimpleSitemap::loadMultiple();
    $this->assertArrayHasKey('test', $sitemaps);

    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    // Test if generation affected the default variant only.
    $this->drupalGet('test/sitemap.xml');
    $this->assertSession()->responseNotContains('node/' . $this->node->id());

    $this->generator->entityManager()->setBundleSettings('node', 'page');
    $this->generator->setSitemaps('test')->generate(QueueWorker::GENERATE_TYPE_BACKEND);

    // Test if bundle settings have been set for correct variant.
    $this->drupalGet($this->defaultSitemapUrl);
    $this->assertSession()->responseContains('node/' . $this->node->id());

    SimpleSitemap::load('test')->delete();

    $sitemaps = SimpleSitemap::loadMultiple();
    $this->assertArrayNotHasKey('test', $sitemaps);

    // Test if sitemap has been removed along with the variant.
    $this->drupalGet('test/sitemap.xml');
    $this->assertSession()->statusCodeEquals(404);
  }

  // phpcs:ignore @todo Test removeSitemap().

  /**
   * Test cases for ::testGenerationResume.
   */
  public static function generationResumeProvider() {
    return [
      [1000, 500, 1],
      [1000, 500, 3, ['de']],
      [1000, 500, 5, ['de', 'es']],
      [10, 10000, 10],
    ];
  }

  /**
   * Test resuming sitemap generation.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @dataProvider generationResumeProvider
   */
  public function testGenerationResume($element_count, $generate_duration, $max_links, $langcodes = []) {

    $this->addLanguages($langcodes);

    $expected_sitemap_count = (int) ceil(($element_count * (count($langcodes) + 1)) / $max_links);

    $this->drupalCreateContentType(['type' => 'blog']);
    for ($i = 1; $i <= $element_count; $i++) {
      $this->createNode(['title' => 'node-' . $i, 'type' => 'blog']);
    }

    $this->generator->entityManager()->setBundleSettings('node', 'blog');
    $this->generator->customLinkManager()->remove();
    $this->generator
      ->saveSetting('generate_duration', $generate_duration)
      ->saveSetting('max_links', $max_links)
      ->saveSetting('skip_untranslated', FALSE)
      ->saveSetting('remove_duplicates', FALSE);

    $this->generator->rebuildQueue();
    $generate_count = 0;
    /** @var \Drupal\simple_sitemap\Queue\QueueWorker $queue_worker */
    $queue_worker = \Drupal::service('simple_sitemap.queue_worker');
    while ($queue_worker->generationInProgress()) {
      $generate_count++;
      $this->generator->generate(QueueWorker::GENERATE_TYPE_BACKEND);
    }

    // Test if sitemap generation has been resumed when time limit is very low.
    $this->assertTrue($generate_duration > $element_count || $generate_count > 1, 'This assertion tests if the sitemap generation is split up into batches due to a low generation time limit setting. The failing of this assertion can mean that the sitemap was wrongfully generated in one go, but it can also mean that the assumed low time setting is still high enough for a one pass generation.');

    // Test if correct number of sitemaps have been created.
    $chunk_count = $this->database->select('simple_sitemap')
      ->condition('delta', 0, '<>')
      ->condition('status', TRUE)
      ->countQuery()->execute()->fetchField();
    $this->assertEquals((int) $chunk_count, $expected_sitemap_count);

    // Test if index has been created when necessary.
    $index = $this->database->query('SELECT id FROM {simple_sitemap} WHERE delta = 0 AND status = 1')
      ->fetchField();
    $this->assertTrue($chunk_count > 1 ? (FALSE !== $index) : !$index);
  }

  /**
   * Test the removal of hreflang tags in HTML.
   */
  public function testHrefLangRemoval() {
    // Test the nodes markup contains hreflang with default settings.
    $this->generator->saveSetting('disable_language_hreflang', FALSE);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertNotEmpty($this->xpath("//link[@hreflang]"));

    Cache::invalidateTags($this->node->getCacheTags());

    // Test the hreflang markup gets removed.
    $this->generator->saveSetting('disable_language_hreflang', TRUE);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertEmpty($this->xpath("//link[@hreflang]"));
  }

}
