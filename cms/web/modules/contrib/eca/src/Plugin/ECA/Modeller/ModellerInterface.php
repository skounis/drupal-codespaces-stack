<?php

namespace Drupal\eca\Plugin\ECA\Modeller;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for ECA modeller plugins.
 */
interface ModellerInterface extends PluginInspectionInterface {

  /**
   * Add the ECA config entity to the modeller.
   *
   * This allows the modeller to call back to the currently operating ECA
   * config which holds additional information and functionality.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity for the modeller to work on.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface
   *   The modeller instance itself.
   */
  public function setConfigEntity(Eca $eca): ModellerInterface;

  /**
   * Generate an ID for the model.
   *
   * @return string
   *   The ID of the model.
   */
  public function generateId(): string;

  /**
   * Create a new ECA config and model entity.
   *
   * @param string $id
   *   The ID for the new model.
   * @param string $model_data
   *   The data for the new model.
   * @param string|null $filename
   *   The optional filename, if the modeller requires the model to be stored
   *   externally as a separate file.
   * @param bool $save
   *   TRUE, if the new entity should also be saved, FALSE otherwise (default).
   *
   * @return \Drupal\eca\Entity\Eca
   *   The new ECA config entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \LogicException
   */
  public function createNewModel(string $id, string $model_data, ?string $filename = NULL, bool $save = FALSE): Eca;

  /**
   * Saves an ECA config entity and its associated ECA model entity.
   *
   * @param string $data
   *   The data of the model to be converted to ECA config and stored as the
   *   modeller's own data.
   * @param string|null $filename
   *   The optional filename, if the modeller requires the model to be stored
   *   externally as a separate file.
   * @param bool|null $status
   *   The optional status for the ECA config using TRUE or FALSE to force
   *   that status regardless of setting in $data. Using NULL (=default) sets
   *   the status to what is defined in $data.
   *
   * @return bool
   *   Returns TRUE, if a reload of the saved model is required. That's the case
   *   when this is either a new model or if the label had changed. It returns
   *   FALSE otherwise, if none of those conditions applies.
   *
   * @see ::createNewModel
   * @see ::disable
   * @see ::enable
   * @see \Drupal\eca\Commands\EcaCommands::import
   * @see \Drupal\eca\Commands\EcaCommands::reimportAll
   * @see \Drupal\eca\Commands\EcaCommands::updateAllModels
   * @see \Drupal\eca_ui\Controller\EcaController::save
   * @see \Drupal\eca_ui\Form\Import::submitForm
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \LogicException
   */
  public function save(string $data, ?string $filename = NULL, ?bool $status = NULL): bool;

  /**
   * Updates and ECA config entity from the given ECA model entity.
   *
   * @param \Drupal\eca\Entity\Model $model
   *   The ECA model entity.
   *
   * @return bool
   *   Returns TRUE, if successful, FALSE otherwise.
   */
  public function updateModel(Model $model): bool;

  /**
   * Enables the current ECA config entity.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface
   *   This.
   */
  public function enable(): ModellerInterface;

  /**
   * Disables the current ECA config entity.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface
   *   This.
   */
  public function disable(): ModellerInterface;

  /**
   * Clones the current ECA config entity.
   *
   * @return \Drupal\eca\Entity\Eca|null
   *   The cloned ECA config entity, if successful, NULL otherwise.
   */
  public function clone(): ?Eca;

  /**
   * Exports the current ECA model.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The response with the contained export if possible and successful,
   *   NULL otherwise.
   */
  public function export(): ?Response;

  /**
   * Gets the external filename of the model, if applicable.
   *
   * @return string
   *   The filename.
   */
  public function getFilename(): string;

  /**
   * Sets the model data.
   *
   * @param string $data
   *   The modeller's data representing the model.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface
   *   This.
   */
  public function setModeldata(string $data): ModellerInterface;

  /**
   * Gets the model data.
   *
   * @return string
   *   The model data.
   */
  public function getModeldata(): string;

  /**
   * Determines if the modeller supports editing in Drupal's admin interface.
   *
   * @return bool
   *   TRUE, if the modellers supports editing inside Drupal's admin interface,
   *   FALSE otherwise.
   */
  public function isEditable(): bool;

  /**
   * Returns a render array with everything required for model editing.
   *
   * @return array
   *   The render array.
   */
  public function edit(): array;

  /**
   * Get the model ID.
   *
   * @return string
   *   The model ID.
   */
  public function getId(): string;

  /**
   * Get the model's label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Get the model's tags.
   *
   * @return array
   *   The list of tags.
   */
  public function getTags(): array;

  /**
   * Get the model's changelog.
   *
   * @return array
   *   The list of changelog items with the key identifying the version and the
   *   value containing the plain text description.
   */
  public function getChangelog(): array;

  /**
   * Get the model's documentation.
   *
   * @return string
   *   The documentation.
   */
  public function getDocumentation(): string;

  /**
   * Get the model's status.
   *
   * @return bool
   *   TRUE, if the model is enabled, FALSE otherwise.
   */
  public function getStatus(): bool;

  /**
   * Get the model's version.
   *
   * @return string
   *   The version string.
   */
  public function getVersion(): string;

  /**
   * Reads all ECA components and adds them to the ECA config entity.
   *
   * The model expects to have been given the model data prior to calling this
   * method. It will then analyze its own data structure, extract all events,
   * gateways, conditions and actions and stores them in the given ECA config
   * entity.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface
   *   This.
   */
  public function readComponents(Eca $eca): ModellerInterface;

  /**
   * Returns the associated ECA config entity.
   *
   * @return \Drupal\eca\Entity\Eca
   *   The associated ECA config entity.
   */
  public function getEca(): Eca;

  /**
   * Determines, if during ::readComponents at least one error occurred.
   *
   * @return bool
   *   TRUE, if at least one error occurred, FALSE otherwise.
   */
  public function hasError(): bool;

  /**
   * Exports all templates in modeller specific format for external use.
   *
   * @return \Drupal\eca\Plugin\ECA\Modeller\ModellerInterface
   *   This.
   */
  public function exportTemplates(): ModellerInterface;

}
