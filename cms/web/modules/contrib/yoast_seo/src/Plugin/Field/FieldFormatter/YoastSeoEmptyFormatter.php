<?php

namespace Drupal\yoast_seo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'yoastseo_empty_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "yoastseo_empty_formatter",
 *   label = @Translation("Empty formatter"),
 *   field_types = {
 *     "field_yoast_seo"
 *   }
 * )
 */
class YoastSeoEmptyFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Does not actually output anything.
    return [];
  }

}
