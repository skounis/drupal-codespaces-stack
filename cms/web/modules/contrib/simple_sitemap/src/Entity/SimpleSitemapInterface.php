<?php

namespace Drupal\simple_sitemap\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a sitemap entity.
 */
interface SimpleSitemapInterface extends ConfigEntityInterface {

  /**
   * Sets the fetch status to published.
   *
   * @return $this
   */
  public function fromPublished(): SimpleSitemapInterface;

  /**
   * Sets the fetch status to unpublished.
   *
   * @return $this
   */
  public function fromUnpublished(): SimpleSitemapInterface;

  /**
   * Sets the fetch status to published and unpublished.
   *
   * @return $this
   */
  public function fromPublishedAndUnpublished(): SimpleSitemapInterface;

  /**
   * Gets the sitemap type.
   *
   * @return \Drupal\simple_sitemap\Entity\SimpleSitemapTypeInterface
   *   The sitemap type entity.
   */
  public function getType(): SimpleSitemapTypeInterface;

  /**
   * Retrieves the sitemap content as string.
   *
   * @param int|null $delta
   *   Optional delta of the chunk.
   *
   * @return string
   *   The sitemap content.
   */
  public function toString(?int $delta = NULL): string;

  /**
   * Publishes the sitemap's content.
   *
   * @return $this
   */
  public function publish(): SimpleSitemapInterface;

  /**
   * Removes the sitemap's content.
   *
   * @return $this
   */
  public function deleteContent(): SimpleSitemapInterface;

  /**
   * Adds a new content chunk to the sitemap.
   *
   * @param array $links
   *   An array of links for this chunk.
   *
   * @return $this
   */
  public function addChunk(array $links): SimpleSitemapInterface;

  /**
   * Generates the index for this sitemap's content chunks.
   *
   * @return $this
   */
  public function generateIndex(): SimpleSitemapInterface;

  /**
   * Returns the number of all sitemap content chunks.
   *
   * @return int
   *   Number of chunks.
   */
  public function getChunkCount(): int;

  /**
   * Determines whether the sitemap has a content index.
   *
   * @return bool
   *   TRUE if the sitemap has an index, FALSE otherwise.
   */
  public function hasIndex(): bool;

  /**
   * Retrieves the sitemap's content index.
   *
   * @return string
   *   The sitemap index content.
   */
  public function getIndex(): string;

  /**
   * Returns the enabled status of the sitemap.
   *
   * This is different to ::status(), which returns TRUE
   * only if the sitemap is enabled AND its content published.
   *
   * @return bool
   *   The enabled status of the sitemap.
   */
  public function isEnabled(): bool;

  /**
   * Returns the status of this sitemap's content.
   *
   * @return int
   *   The content status of this sitemap.
   */
  public function contentStatus(): int;

  /**
   * Returns the timestamp of the sitemap chunk generation.
   *
   * @return int|null
   *   Timestamp of sitemap chunk generation.
   */
  public function getCreated(): ?int;

  /**
   * Returns the number of links indexed in the sitemap content.
   *
   * @return int
   *   Number of links.
   */
  public function getLinkCount(): int;

  /**
   * Determines whether this sitemap is set to be the default one.
   *
   * @return bool
   *   Whether the sitemap is the default sitemap.
   */
  public function isDefault(): bool;

  /**
   * Determines if the sitemap is to be a multilingual based on several factors.
   *
   * A hreflang/multilingual sitemap is only wanted if there are indexable
   * languages available and if there is a language negotiation method enabled
   * that is based on URL discovery. Any other language negotiation methods
   * should be irrelevant, as a sitemap can only use URLs to guide to the
   * correct language.
   *
   * @see https://www.drupal.org/project/simple_sitemap/issues/3154570#comment-13730522
   *
   * @return bool
   *   TRUE if the sitemap is multilingual and FALSE otherwise.
   */
  public function isMultilingual(): bool;

  /**
   * Removes the content from all or specified sitemaps.
   *
   * A sitemap entity can exist without the sitemap (XML) content which lives
   * in the DB. This purges the sitemap content.
   *
   * @param array|null $variants
   *   An array of sitemap IDs, or NULL for all sitemaps.
   * @param int|null $status
   *   Purge by sitemap status.
   */
  public static function purgeContent(?array $variants = NULL, ?int $status = SimpleSitemap::FETCH_BY_STATUS_ALL);

}
