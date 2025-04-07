<?php

namespace Drupal\eca_views\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Run views query and export result.
 *
 * @Action(
 *   id = "eca_views_export",
 *   label = @Translation("Views: Export query into file"),
 *   description = @Translation("Use a view to execute a query and save the results to a file. You can also save the results in a token."),
 *   eca_version_introduced = "1.0.0"
 * )
 */
class ViewsExport extends ViewsQuery {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The filename being prepared by ::access() and used by ::execute().
   *
   * @var string
   */
  private string $filename;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('renderer');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    if (!$this->getDisplay() || !isset($this->view)) {
      return;
    }
    if ($this->configuration['load_results_into_token']) {
      parent::execute();
    }
    else {
      $this->view->preExecute();
      $this->view->execute();
    }
    $build = $this->view->display_handler->buildRenderable($this->view->args, FALSE);
    $output = (string) $this->renderer->renderRoot($build);
    file_put_contents($this->filename, $output);
    $token_name = trim($this->configuration['token_for_filename']);
    if ($token_name === '') {
      $token_name = 'eca-view-output-filename';
    }
    $this->tokenService->addTokenData($token_name, $this->filename);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    if ($result->isAllowed() && $display = $this->getDisplay()) {
      if (empty($display->getPluginDefinition()['returns_response'])) {
        $result = AccessResult::forbidden('The given view display is not meant to export. Please use a display type that supports exporting data.');
      }
      else {
        $this->filename = $this->getFilename($display);
        $dirname = $this->fileSystem->dirname($this->filename);
        if (!$this->fileSystem->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
          $result = AccessResult::forbidden('The given filename is not writable.');
        }
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'filename' => '',
      'token_for_filename' => '',
      'load_results_into_token' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['load_results_into_token'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Store results also in a token?'),
      '#description' => $this->t('Check this box to save the results to a token as well'),
      '#default_value' => $this->configuration['load_results_into_token'],
      '#weight' => -70,
    ];
    $form['token_for_filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name for file name'),
      '#description' => $this->t('Provide a token name where ECA will store the effectively used filename of the output.'),
      '#default_value' => $this->configuration['token_for_filename'],
      '#weight' => -20,
      '#eca_token_reference' => TRUE,
    ];
    $form['filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File name'),
      '#description' => $this->t('Sets the name of the file where the data will be exported. If left empty, the file name configured in the view will be used, or a random name otherwise.'),
      '#default_value' => $this->configuration['filename'],
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['load_results_into_token'] = !empty($form_state->getValue('load_results_into_token'));
    $this->configuration['token_for_filename'] = $form_state->getValue('token_for_filename');
    $this->configuration['filename'] = $form_state->getValue('filename');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Returns the filename being used for the export.
   *
   * If the plugin configuration provides a filename, this will be used.
   * Otherwise the filename configured for the display plugin will be used
   * or a default of temporary://view.output otherwise.
   *
   * @param \Drupal\views\Plugin\views\display\DisplayPluginBase $display
   *   The display plugin for which to determine the filename.
   *
   * @return string
   *   The filename.
   */
  protected function getFilename(DisplayPluginBase $display): string {
    if ($filename = $this->tokenService->replaceClear($this->configuration['filename'])) {
      return $filename;
    }
    if ($filename = $display->getOption('filename')) {
      return $this->tokenService->replaceClear($filename, ['view' => $this->view]);
    }
    return 'temporary://' . uniqid('eca.view.output', TRUE);
  }

}
