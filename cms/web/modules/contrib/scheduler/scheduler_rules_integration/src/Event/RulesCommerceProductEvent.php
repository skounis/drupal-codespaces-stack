<?php

namespace Drupal\scheduler_rules_integration\Event;

use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Class for all Commerce Product events for use in Rules module.
 */
class RulesCommerceProductEvent extends EventBase {

  /**
   * Define constants to convert the event identifier into the full event name.
   *
   * The final event names here are defined in the event deriver and are
   * different in format from the event names for node events, as originally
   * coded long-hand in scheduler_rules_integration.rules.events.yml.
   * However, the identifiers (CRON_PUBLISHED, NEW_FOR_PUBLISHING, etc) are the
   * same for all types and this is how the actual event names are retrieved.
   */
  const CRON_PUBLISHED = 'scheduler:commerce_product_has_been_published_via_cron';
  const CRON_UNPUBLISHED = 'scheduler:commerce_product_has_been_unpublished_via_cron';
  const NEW_FOR_PUBLISHING = 'scheduler:new_commerce_product_is_scheduled_for_publishing';
  const NEW_FOR_UNPUBLISHING = 'scheduler:new_commerce_product_is_scheduled_for_unpublishing';
  const EXISTING_FOR_PUBLISHING = 'scheduler:existing_commerce_product_is_scheduled_for_publishing';
  const EXISTING_FOR_UNPUBLISHING = 'scheduler:existing_commerce_product_is_scheduled_for_unpublishing';

  /**
   * The commerce product which is being processed.
   *
   * This property name could be changed to lowerCamelCase but that would also
   * require the context_definitions key to be changed to match. This could also
   * be done, but when editing a rule we get commerceproduct in the drop-downs,
   * whereas all other usages in the Rules forms have commerce_product. This is
   * confusing for the admin/developer who has to select from this list when
   * editing a rule. Therefore keep the property name matching the entity type
   * id and prevent Coder from reporting the invalid name by disabling this
   * specific sniff for this file only.
   *
   * phpcs:disable Drupal.NamingConventions.ValidVariableName.LowerCamelName
   *
   * @var Drupal\commerce_product\Entity\ProductInterface
   */
  public $commerce_product;

  /**
   * Constructs the object.
   *
   * @param Drupal\commerce_product\Entity\ProductInterface $commerce_product
   *   The commerce_product item which is being processed.
   */
  public function __construct(ProductInterface $commerce_product) {
    $this->commerce_product = $commerce_product;
  }

  /**
   * Returns the entity which is being processed.
   */
  public function getEntity() {
    // The Rules module requires the entity to be stored in a specifically named
    // property which will obviously vary according to the entity type being
    // processed. This generic getEntity() method is not strictly required by
    // Rules but is added for convenience when manipulating the event entity.
    return $this->commerce_product;
  }

}
