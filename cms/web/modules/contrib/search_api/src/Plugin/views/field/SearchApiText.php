<?php

namespace Drupal\search_api\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;

/**
 * Handles the display of text fields in Search API Views.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("search_api_text")
 */
class SearchApiText extends SearchApiStandard {

  /**
   * {@inheritdoc}
   */
  public function defineOptions(): array {
    $options = parent::defineOptions();

    $options['filter_type'] = [
      'default' => !empty($this->definition['filter_type']) ? $this->definition['filter_type'] : 'plain',
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $t_args = [
      '@strip' => $this->t('Strip HTML tags'),
      '@rewrite' => $this->t('Rewrite results'),
    ];

    $xss_allowed_tags = '<' . implode('> <', Xss::getHtmlTagList()) . '>';
    $xss_allowed_admin_tags = '<' . implode('> <', Xss::getAdminTagList()) . '>';

    $form['filter_type'] = [
      '#title' => $this->t('Enable HTML in this field'),
      '#type' => 'radios',
      '#options' => [
        'plain' => $this->t('Do not allow HTML'),
        'xss' => $this->t('Allow safe HTML only'),
        'xss_admin' => $this->t('Allow almost any HTML'),
      ],
      '#default_value' => $this->options['filter_type'],
      'plain' => [
        '#description' => $this->t('This will display any HTML tags in the field value escaped as plain text.'),
      ],
      'xss' => [
        '#description' => $this->t('Allowed tags: @tags. For instead removing those tags, use the "@strip" option under "@rewrite".', ['@tags' => $xss_allowed_tags] + $t_args),
      ],
      'xss_admin' => [
        '#description' => $this->t('Allowed tags: @tags. <strong>Use with caution.</strong> For instead removing those tags, use the "@strip" option under "@rewrite".', ['@tags' => $xss_allowed_admin_tags] + $t_args),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render_item($count, $item): MarkupInterface|string {
    return $this->sanitizeValue($item['value'], $this->options['filter_type']);
  }

}
