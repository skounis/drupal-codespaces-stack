<?php

namespace Drupal\eca_render\Event;

use Drupal\eca_render\Plugin\Block\EcaBlock;

/**
 * Dispatched when an ECA Block is being rendered.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_render\Event
 */
class EcaRenderBlockEvent extends EcaRenderEventBase {

  /**
   * The block plugin instance.
   *
   * @var \Drupal\eca_render\Plugin\Block\EcaBlock
   */
  protected EcaBlock $block;

  /**
   * Constructs a new EcaRenderBlockEvent object.
   *
   * @param \Drupal\eca_render\Plugin\Block\EcaBlock $block
   *   The block plugin instance.
   */
  public function __construct(EcaBlock $block) {
    $this->block = $block;
  }

  /**
   * Get the block plugin instance.
   *
   * @return \Drupal\eca_render\Plugin\Block\EcaBlock
   *   The instance.
   */
  public function getBlock(): EcaBlock {
    return $this->block;
  }

  /**
   * {@inheritdoc}
   */
  public function &getRenderArray(): array {
    $build = &$this->block->build;
    return $build;
  }

}
