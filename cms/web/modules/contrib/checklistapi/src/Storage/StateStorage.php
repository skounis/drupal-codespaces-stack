<?php

namespace Drupal\checklistapi\Storage;

use Drupal\Core\State\StateInterface;

/**
 * Provides state-based checklist progress storage.
 */
class StateStorage extends StorageBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * Constructs a class instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getSavedProgress() {
    return $this->state->get($this->stateKey());
  }

  /**
   * {@inheritdoc}
   */
  public function setSavedProgress(array $progress) {
    $this->state->set($this->stateKey(), $progress);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSavedProgress() {
    $this->state->delete($this->stateKey());
  }

  /**
   * Returns the state key.
   *
   * @return string
   *   The state key.
   */
  private function stateKey() {
    return 'checklistapi.progress.' . $this->getChecklistId();
  }

}
