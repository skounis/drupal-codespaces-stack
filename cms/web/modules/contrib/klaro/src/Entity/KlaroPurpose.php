<?php

namespace Drupal\klaro\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\klaro\KlaroPurposeInterface;

/**
 * Defines the Klaro! purpose config entity.
 *
 * @ingroup klaro
 *
 * @ConfigEntityType(
 *   id = "klaro_purpose",
 *   label = @Translation("Klaro! Purpose"),
 *   label_singular = @Translation("Klaro! purpose"),
 *   label_plural = @Translation("Klaro! purposes"),
 *   handlers = {
 *     "list_builder" = "Drupal\klaro\Controller\KlaroPurposeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "form" = {
 *       "add" = "Drupal\klaro\Form\KlaroPurposeForm",
 *       "edit" = "Drupal\klaro\Form\KlaroPurposeForm",
 *       "delete" = "Drupal\klaro\Form\KlaroPurposeDeleteForm",
 *     },
 *   },
 *   links = {
 *     "collection" = "/admin/config/user-interface/klaro/purposes",
 *     "add-form" = "/admin/config/user-interface/klaro/purposes/add",
 *     "edit-form" = "/admin/config/user-interface/klaro/purposes/{klaro_purpose}",
 *     "delete-form" = "/admin/config/user-interface/klaro/purposes/{klaro_purpose}/delete"
 *   },
 *   admin_permission = "administer klaro",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "description" = "description",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "weight",
 *   },
 * )
 */
class KlaroPurpose extends ConfigEntityBase implements KlaroPurposeInterface {

  /**
   * Machine name of the purpose.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the purpose.
   *
   * @var string
   */
  protected $label;

  /**
   * The description of the purpose.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The weight of the purpose.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * {@inheritdoc}
   */
  public function id(): ?string {
    return $this->get('id');
  }

  /**
   * {@inheritdoc}
   */
  public function setId(string $id): KlaroPurposeInterface {
    return $this->set('id', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): ?string {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label): KlaroPurposeInterface {
    return $this->set('label', $label);
  }

  /**
   * {@inheritdoc}
   */
  public function description(): ?string {
    return $this->get('description');
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): KlaroPurposeInterface {
    return $this->set('description', $description);
  }

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return $this->get('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight = 0): KlaroPurposeInterface {
    return $this->set('weight', $weight);
  }

}
