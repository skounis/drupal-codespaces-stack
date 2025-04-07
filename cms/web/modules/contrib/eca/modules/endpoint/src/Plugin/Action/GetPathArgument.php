<?php

namespace Drupal\eca_endpoint\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;

/**
 * Get a path argument.
 *
 * @Action(
 *   id = "eca_endpoint_get_path_argument",
 *   label = @Translation("Request: Get path argument"),
 *   eca_version_introduced = "1.1.0"
 * )
 */
class GetPathArgument extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue(): mixed {
    $request = $this->getRequest();
    if ($request === NULL) {
      return NULL;
    }
    $path = $request->getPathInfo();
    $index = trim((string) $this->configuration['index']);
    if ($index === '') {
      return $path;
    }
    $parts = array_values(array_filter(explode('/', $request->getPathInfo()), static function ($v) {
      return $v !== '';
    }));
    $index = (int) $index;
    return $parts[$index] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'index' => '1',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['index'] = [
      '#type' => 'number',
      '#min' => 0,
      '#title' => $this->t('Index position'),
      '#description' => $this->t('The index position of the path argument, starting at 0. Example: When index position is set to <em>2</em> and the path is <em>/node/1/edit</em>, then the returned value is <em>edit</em>. When empty, the whole requested path will be returned.'),
      '#default_value' => $this->configuration['index'],
      '#weight' => -20,
      '#required' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['index'] = (string) $form_state->getValue('index');
    parent::submitConfigurationForm($form, $form_state);
  }

}
