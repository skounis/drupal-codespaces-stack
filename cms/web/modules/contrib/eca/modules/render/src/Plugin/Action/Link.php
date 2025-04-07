<?php

namespace Drupal\eca_render\Plugin\Action;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\eca\Plugin\ECA\PluginFormTrait;

/**
 * Build a link element.
 *
 * @Action(
 *   id = "eca_render_link",
 *   label = @Translation("Render: link"),
 *   description = @Translation("Build a link element, optionally displaying its content as a modal or dialog."),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class Link extends RenderElementActionBase {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $url = $this->tokenService->replace($this->configuration['url']);
    $result = AccessResult::allowedIf(is_string($url) && $url !== '');
    if (!$result->isAllowed()) {
      $result->setReason('The given url is invalid.');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'title' => '',
      'url' => '',
      'link_type' => 'page',
      'width' => '',
      'display_as' => 'anchor',
      'absolute' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function doBuild(array &$build): void {
    $url = trim((string) $this->tokenService->replaceClear($this->configuration['url']));
    if ($url === '') {
      throw new \InvalidArgumentException("Cannot build a link element without a URL.");
    }
    try {
      $url = Url::fromUserInput($url);
    }
    catch (\Exception $e) {
      $url = Url::fromUri($url);
    }

    $title = trim((string) $this->tokenService->replaceClear($this->configuration['title']));
    if ($title === '') {
      $title = $url->toString();
    }

    $build = [
      '#type' => 'link',
      '#url' => $url,
      '#title' => $title,
      '#attributes' => [
        'id' => Html::getUniqueId('eca-link'),
        'class' => ['eca-link'],
      ],
    ];

    $display_as = $this->configuration['display_as'];
    if ($display_as === '_eca_token') {
      $display_as = $this->getTokenValue('display_as', 'anchor');
    }
    $display_as = explode(':', $display_as);
    if (in_array('button', $display_as, TRUE)) {
      $build['#attributes']['class'][] = 'button';
      if (in_array('small', $display_as, TRUE)) {
        $build['#attributes']['class'][] = 'button--small';
      }
      if (in_array('primary', $display_as, TRUE)) {
        $build['#attributes']['class'][] = 'button--primary';
      }
    }

    if ($url->isExternal()) {
      $build['#attributes']['rel'] = 'nofollow noreferrer';
    }
    if ($this->configuration['absolute']) {
      $url->setAbsolute(TRUE);
    }

    $link_type = $this->configuration['link_type'] ?? 'page';
    if ($link_type === '_eca_token') {
      $link_type = $this->getTokenValue('link_type', 'page');
    }
    if ($link_type === 'page_new_window') {
      $build['#attributes']['target'] = '_blank';
      $link_type = 'page';
    }
    elseif ($link_type === 'off_canvas') {
      $build['#attributes']['data-dialog-renderer'] = 'off_canvas';
    }
    elseif ($link_type === 'off_canvas_top') {
      $build['#attributes']['data-dialog-renderer'] = 'off_canvas_top';
    }
    if ($link_type !== 'page') {
      if ($link_type !== 'ajax') {
        $width = trim((string) $this->tokenService->replaceClear($this->configuration['width']));
        if ($width === '' || !ctype_digit($width)) {
          $width = '50';
        }
        if (!(mb_substr($width, -1) === '%')) {
          $width .= '%';
        }
        $build['#attributes']['data-dialog-options'] = Json::encode([
          'width' => $width,
          'title' => $title,
        ]);
        $build['#attributes']['data-dialog-type'] = $link_type === 'modal' ? 'modal' : 'dialog';
      }
      $build['#attributes']['class'][] = 'use-ajax';
      $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The title of the link.'),
      '#weight' => -200,
      '#default_value' => $this->configuration['title'],
      '#required' => FALSE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The link destination as a valid URL.'),
      '#weight' => -190,
      '#default_value' => $this->configuration['url'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['absolute'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enforce absolute'),
      '#description' => $this->t('This makes the destination URL of the link always absolute, also for relative and internal URLs.'),
      '#default_value' => $this->configuration['absolute'],
      '#weight' => -185,
      '#required' => FALSE,
    ];
    $form['link_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Link type'),
      '#description' => $this->t('Choose how the content of the link should be displayed. More about dialog types can be found <a target="_blank" href=":url">here</a>."', [
        ':url' => 'https://www.drupal.org/docs/drupal-apis/ajax-api/ajax-dialog-boxes#s-types-of-dialogs',
      ]),
      '#options' => [
        'ajax' => $this->t('Ajax request'),
        'modal' => $this->t('Modal dialog'),
        'dialog' => $this->t('Non-modal dialog'),
        'off_canvas' => $this->t('Off-canvas dialog'),
        'off_canvas_top' => $this->t('Off-canvas top'),
        'page' => $this->t('Direct to page (no dialog)'),
        'page_new_window' => $this->t('Direct to page (new window)'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['link_type'],
      '#weight' => -180,
      '#eca_token_select_option' => TRUE,
    ];
    $form['width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width percentage'),
      '#description' => $this->t('Specify the number of width percentage if the link content is being rendered in a modal or dialog.'),
      '#min' => 1,
      '#max' => 100,
      '#suffix' => '%',
      '#default_value' => $this->configuration['width'],
      '#weight' => -170,
      '#required' => FALSE,
    ];
    $form['display_as'] = [
      '#type' => 'select',
      '#title' => $this->t('Display link as'),
      '#options' => [
        'anchor' => $this->t('Normal link (anchor tag)'),
        'button:primary' => $this->t('Primary button'),
        'button:primary:small' => $this->t('Primary button (small)'),
      ],
      '#default_value' => $this->configuration['display_as'],
      '#required' => TRUE,
      '#weight' => -160,
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['url'] = $form_state->getValue('url');
    $this->configuration['link_type'] = $form_state->getValue('link_type');
    $this->configuration['width'] = $form_state->getValue('width');
    $this->configuration['display_as'] = $form_state->getValue('display_as');
    $this->configuration['absolute'] = !empty($form_state->getValue('absolute'));
  }

}
