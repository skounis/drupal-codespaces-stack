<?php

namespace Drupal\yoast_seo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'example_formatter' formatter.
 *
 * @FieldFormatter(
 *    id = "yoastseo_formatter",
 *    label = @Translation("Real-timeSeo formatter"),
 *    field_types = {
 *      "yoast_seo",
 *    }
 * )
 */
class YoastSeoFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $yoast_seo_manager = \Drupal::service('yoast_seo.manager');
    foreach ($items as $delta => $item) {
      $status = $yoast_seo_manager->getScoreStatus($item->status);

      // @todo find a way to give a weight, so the column doesn't appear
      // at the end.
      // Get template for the snippet.
      $overall_score_tpl = [
        '#theme' => 'view_overall_score',
        '#overall_score' => $status,
        '#attached' => [
          'library' => [
            'yoast_seo/yoast_seo_view',
          ],
        ],
      ];
      $output = \Drupal::service('renderer')->render($overall_score_tpl);

      $elements[$delta] = [
        '#markup' => $output,
      ];
    }

    return $elements;
  }

}
