<?php

namespace Drupal\eca_file\Plugin\Action;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Write data to a physical file.
 *
 * @Action(
 *   id = "eca_file_write",
 *   label = @Translation("File: write"),
 *   description = @Translation("Write data to a physical file of a file entity."),
 *   type = "entity"
 * )
 */
class FileWrite extends ConfigurableActionBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'data' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('File content'),
      '#default_value' => $this->configuration['data'],
      '#required' => TRUE,
      '#description' => $this->t('The content to write to the given file.'),
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['data'] = $form_state->getValue('data');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof FileInterface)) {
      return;
    }
    $file_uri = $entity->getFileUri();
    $data = $this->tokenService->replace($this->configuration['data']);
    if ($this->fileSystem->saveData($data, $file_uri, FileExists::Replace)) {
      $entity
        ->set('filesize', mb_strlen($data))
        ->save();
    }
  }

}
