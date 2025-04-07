<?php

declare(strict_types=1);

namespace Drupal\bpmn_io\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a BPMN.iO for ECA form.
 */
final class Modeller extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bpmn_io_modeller';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $id = NULL): array {
    $options = [
      'query' => [
        'destination' => Url::fromRoute('entity.eca.edit_form', ['eca' => $id])->toString(),
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'save' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#attributes' => [
          'class' => ['button--primary eca-save'],
        ],
      ],
      'layout_process' => [
        '#type' => 'submit',
        '#value' => $this->t('Layout model'),
        '#attributes' => [
          'class' => ['eca-layout-process'],
        ],
      ],
      'close' => [
        '#type' => 'submit',
        '#value' => $this->t('Close'),
        '#attributes' => [
          'class' => ['eca-close'],
        ],
      ],
      'export_archive' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.eca.export', ['eca' => $id], $options),
        '#title' => $this->t('Export'),
        '#attributes' => [
          'class' => ['button button--small eca-export-archive'],
        ],
      ],
      'export_recipe' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.eca.export_recipe', ['eca' => $id], $options),
        '#title' => $this->t('Export Recipe'),
        '#attributes' => [
          'class' => ['button button--small eca-export-recipe use-ajax'],
          'data-dialog-options' => Json::encode([
            'width' => 400,
            'title' => $this->t('Export Recipe'),
          ]),
          'data-dialog-type' => 'modal',
        ],
        '#attached' => [
          'library' => [
            'core/drupal.dialog.ajax',
          ],
        ],
      ],
      'export_svg' => [
        '#type' => 'submit',
        '#value' => $this->t('Export SVG'),
        '#attributes' => [
          'class' => ['button button--small eca-export-svg'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
