<?php

use Drupal\block_content\Entity\BlockContent;

foreach (BlockContent::loadMultiple() as $id => $block) {
  echo "$id: " . $block->label() . "\n";
}
