<?php

namespace Drupal\bpmn_io\Services\Converter;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\bpmn_io\Plugin\ECA\Modeller\BpmnIo;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Service\Modellers;

/**
 * Converts an ECA-model to use BPMN.io.
 */
class Converter implements ConverterInterface {

  use StringTranslationTrait;

  /**
   * Converter instructor.
   *
   * @param \Drupal\eca\Service\Modellers $modellerServices
   *   ECA modeller service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current user.
   */
  public function __construct(
    protected Modellers $modellerServices,
    protected MessengerInterface $messenger,
    protected AccountProxy $currentUser,
  ) {
  }

  /**
   * Access control for the bpmn_io.convert route.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA entity to convert.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result for the given ECA entity.
   */
  public function access(Eca $eca): AccessResultInterface {
    if ($this->currentUser->hasPermission('edit bpmn io diagram') && $eca->status()) {
      $modeller = $this->modellerServices->getModeller('bpmn_io');
      if ($modeller) {
        $ecaModeller = $eca->getModeller();
        if (!($ecaModeller instanceof BpmnIo)) {
          return AccessResult::allowed();
        }
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function convert(Eca $eca): array {
    if (!($eca->getModeller() instanceof BpmnIo)) {
      // Let the modeller clone the ECA-entity.
      $eca = $eca->getModeller()->clone();
      $eca->set('modeller', 'bpmn_io');
      $eca->save();
    }

    // "Reset" the model data.
    /** @var \Drupal\bpmn_io\Plugin\ECA\Modeller\BpmnIo $modeller */
    $modeller = $eca->getModeller();
    $id = '';
    $emptyBpmn = $modeller->prepareEmptyModelData($id);
    $emptyBpmn = str_replace($id, $eca->id(), $emptyBpmn);
    $modeller->setModeldata($emptyBpmn);

    // Provide enough mappings so that the Javascript-class can do its thing.
    $build = $modeller->build();
    $build['canvas']['#suffix'] = '<div class="convert-overlay">' . $this->t('Conversion in progress ...') . '</div>';
    $build['#attached']['library'] = 'bpmn_io/convert';
    $build['#attached']['drupalSettings']['bpmn_io']['bpmn'] = $modeller->getModeldata();
    $build['#attached']['drupalSettings']['bpmn_io_convert'] = [
      'metadata' => [
        'name' => $eca->label(),
        'version' => $eca->get('version'),
        'redirect_url' => Url::fromRoute(
          'entity.eca.edit_form',
          ['eca' => $eca->id()],
          ['absolute' => TRUE]
        )->toString(),
      ],
    ];

    $components = [
      'events' => $eca->get('events'),
      'gateways' => $eca->get('gateways'),
      'conditions' => $eca->get('conditions'),
      'actions' => $eca->get('actions'),
    ];
    $build['#attached']['drupalSettings']['bpmn_io_convert']['elements'] = array_merge(...array_values($components));

    $bpmnMapping = array_merge(
      array_fill_keys(array_keys($components['events']), 'StartEvent'),
      array_fill_keys(array_keys($components['gateways']), 'ExclusiveGateway'),
      array_fill_keys(array_keys($components['conditions']), 'SequenceFlow'),
      array_fill_keys(array_keys($components['actions']), 'Task'),
    );
    $build['#attached']['drupalSettings']['bpmn_io_convert']['bpmn_mapping'] = $bpmnMapping;

    $templateMapping = array_merge(
      array_fill_keys(array_keys($components['events']), 'event'),
      array_fill_keys(array_keys($components['conditions']), 'condition'),
      array_fill_keys(array_keys($components['actions']), 'action'),
    );
    $build['#attached']['drupalSettings']['bpmn_io_convert']['template_mapping'] = $templateMapping;

    return $build;
  }

}
