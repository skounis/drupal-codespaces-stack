<?php

namespace Drupal\bpmn_io\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\bpmn_io\Services\Converter\ConverterInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Service\Modellers;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for BPMN.iO modeller integration into ECA.
 *
 * @package Drupal\bpmn_io\Controller
 */
final class BpmnIo extends ControllerBase {

  /**
   * ECA modeller service.
   *
   * @var \Drupal\eca\Service\Modellers
   */
  protected Modellers $modellerServices;

  /**
   * The converter.
   *
   * @var \Drupal\bpmn_io\Services\Converter\ConverterInterface
   */
  protected ConverterInterface $converter;

  /**
   * BpmnIo constructor.
   *
   * @param \Drupal\eca\Service\Modellers $modeller_services
   *   The ECA modeller service.
   * @param \Drupal\bpmn_io\Services\Converter\ConverterInterface $converter
   *   The converter.
   */
  public function __construct(Modellers $modeller_services, ConverterInterface $converter) {
    $this->modellerServices = $modeller_services;
    $this->converter = $converter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): BpmnIo {
    return new static(
      $container->get('eca.service.modeller'),
      $container->get('bpmn_io.services.converter')
    );
  }

  /**
   * Callback to add a new BPMN.iO model and open the edit route.
   *
   * @return array
   *   The render array for editing the new model.
   */
  public function add(): array {
    if ($modeller = $this->modellerServices->getModeller('bpmn_io')) {
      /** @var \Drupal\bpmn_io\Plugin\ECA\Modeller\BpmnIo $modeller */
      $id = '';
      $emptyBpmn = $modeller->prepareEmptyModelData($id);
      try {
        $modeller->createNewModel($id, $emptyBpmn);
      }
      catch (\LogicException | EntityStorageException $e) {
        $this->messenger()->addError($e->getMessage());
        return [];
      }
      return $modeller->edit();
    }
    return [];
  }

  /**
   * Callback to convert the modeller of an ECA-entity to BPMN.io.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA-entity to convert.
   *
   * @return array
   *   The render array for editing the new model with convert enabled.
   */
  public function convert(Eca $eca): array {
    return $this->converter->convert($eca);
  }

}
