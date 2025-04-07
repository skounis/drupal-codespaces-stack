<?php

namespace Drupal\eca\Plugin\ECA\Modeller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Plugin\ECA\EcaPluginBase;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;
use Drupal\eca\Service\Modellers;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for ECA modeller plugins.
 */
abstract class ModellerBase extends EcaPluginBase implements ModellerInterface {

  /**
   * ECA action service.
   *
   * @var \Drupal\eca\Service\Actions
   */
  protected Actions $actionServices;

  /**
   * ECA condition service.
   *
   * @var \Drupal\eca\Service\Conditions
   */
  protected Conditions $conditionServices;

  /**
   * ECA modeller service.
   *
   * @var \Drupal\eca\Service\Modellers
   */
  protected Modellers $modellerServices;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * Extension path resolver service.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * The documentation domain. May be NULL if not enabled or specified.
   *
   * @var string|null
   */
  protected ?string $documentationDomain;

  /**
   * ECA config entity.
   *
   * @var \Drupal\eca\Entity\Eca
   */
  protected Eca $eca;

  /**
   * Error flag.
   *
   * @var bool
   */
  protected bool $hasError = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->actionServices = $container->get('eca.service.action');
    $instance->conditionServices = $container->get('eca.service.condition');
    $instance->modellerServices = $container->get('eca.service.modeller');
    $instance->logger = $container->get('logger.channel.eca');
    $instance->uuid = $container->get('uuid');
    $instance->extensionPathResolver = $container->get('extension.path.resolver');
    $instance->documentationDomain = $container->getParameter('eca.default_documentation_domain') ?
      $container->get('config.factory')->get('eca.settings')->get('documentation_domain') : NULL;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  final public function setConfigEntity(Eca $eca): ModellerInterface {
    $this->eca = $eca;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(): bool {
    return FALSE;
  }

  /**
   * Prepares a model for export.
   *
   * By default, this is doing nothing. But this can be overwritten by
   * modeller implementations.
   */
  protected function prepareForExport(): void {}

  /**
   * {@inheritdoc}
   */
  public function export(): ?Response {
    $this->prepareForExport();
    $filename = mb_strtolower($this->getPluginId()) . '-' . mb_strtolower($this->getEca()->id()) . '.tar.gz';
    $tempFileName = 'temporary://' . $filename;
    $this->modellerServices->exportArchive($this->eca, $tempFileName);
    return new BinaryFileResponse($tempFileName, 200, [
      'Content-Type' => 'application/octet-stream',
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function edit(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEca(): Eca {
    return $this->eca;
  }

  /**
   * {@inheritdoc}
   */
  public function hasError(): bool {
    return $this->hasError;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): array {
    return [];
  }

}
