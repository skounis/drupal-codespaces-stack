<?php

namespace Drupal\eca_file\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rename a physical file.
 *
 * @Action(
 *   id = "eca_file_rename",
 *   label = @Translation("File: rename"),
 *   description = @Translation("Rename the physical file of a file entity."),
 *   type = "entity"
 * )
 */
class FileRename extends ConfigurableActionBase {

  use PluginFormTrait;

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
      'new_filename' => '',
      'exists_behavior' => FileExists::Replace->name,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['new_filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New file name'),
      '#default_value' => $this->configuration['new_filename'],
      '#required' => TRUE,
      '#weight' => -30,
      '#description' => $this->t('The new file name without schema or path, but with the file extension.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['exists_behavior'] = [
      '#type' => 'select',
      '#title' => $this->t('Behavior'),
      '#default_value' => $this->configuration['exists_behavior'],
      '#required' => TRUE,
      '#options' => [
        FileExists::Rename->name => $this->t('Appends number until name is unique'),
        FileExists::Replace->name => $this->t('Replace the existing file'),
        FileExists::Error->name => $this->t('Do nothing'),
      ],
      '#weight' => -40,
      '#description' => $this->t('The behavior when dealing with existing files.'),
      '#eca_token_select_option' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['new_filename'] = $form_state->getValue('new_filename');
    $this->configuration['exists_behavior'] = $form_state->getValue('exists_behavior');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $new_filename = $this->tokenService->replace($this->configuration['new_filename']);
    $result = AccessResult::allowedIf(is_string($new_filename) && $new_filename !== '');
    if (!$result->isAllowed()) {
      $result->setReason('The given new filename is invalid.');
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (!($entity instanceof FileInterface)) {
      return;
    }
    $file_uri = $entity->getFileUri();
    $old_filename = pathinfo($file_uri, PATHINFO_FILENAME) . '.' . pathinfo($file_uri, PATHINFO_EXTENSION);
    $new_filename = $this->tokenService->replace($this->configuration['new_filename']);
    $filepath_new = str_replace($old_filename, $new_filename, $file_uri);
    $exists_behavior = $this->configuration['exists_behavior'];
    if ($exists_behavior === '_eca_token') {
      $exists_behavior = $this->getTokenValue('exists_behavior', FileExists::Replace->name);
    }
    $behavior = match ($exists_behavior) {
      FileExists::Replace->name => FileExists::Replace,
      FileExists::Error->name => FileExists::Error,
      default => FileExists::Rename,
    };
    $filepath_new = $this->fileSystem->move($file_uri, $filepath_new, $behavior);
    $entity
      ->set('filename', $new_filename)
      ->set('uri', $filepath_new)
      ->save();
  }

}
