<?php

namespace Drupal\Tests\scheduler\Traits;

/**
 * Additional setup trait for Scheduler tests that use Commerce Product.
 */
trait SchedulerCommerceProductSetupTrait {

  /**
   * The internal name of the standard product type for testing.
   *
   * Use the pre-existing 'default' product type. This is a short-cut.
   *
   * @var string
   */
  protected $productTypeName = 'test_product';

  /**
   * The readable label of the standard product type for testing.
   *
   * @var string
   */
  protected $productTypeLabel = 'Test Product';

  /**
   * The product type object which is enabled for scheduling.
   *
   * @var Drupal\commerce_product\Entity\ProductType
   */
  protected $productType;

  /**
   * The default commerce store to which all products are added.
   *
   * @var Drupal\commerce_store\Entity\Store
   */
  protected $store;

  /**
   * The internal name of the product type not enabled for scheduling.
   *
   * @var string
   */
  protected $nonSchedulerProductTypeName = 'non_enabled_product';

  /**
   * The readable label of the product type not enabled for scheduling.
   *
   * @var string
   */
  protected $nonSchedulerProductTypeLabel = 'Non-scheduler Product';

  /**
   * The product type object which is not enabled for scheduling.
   *
   * @var Drupal\commerce_product\Entity\ProductType
   */
  protected $nonSchedulerProductType;

  /**
   * The product entity storage.
   *
   * Is this really needed now that we can use $this->entityStorageObject() ?
   *
   * @var Drupal\commerce\CommerceContentEntityStorage
   */
  protected $productStorage;

  /**
   * Set common properties, define content types and create users.
   */
  public function schedulerCommerceProductSetUp() {

    /** @var Store $store */
    $this->store = $this->entityStorageObject('commerce_store')->create([
      'type' => 'online',
      'name' => 'My Test Store',
    ]);
    $this->store->save();

    $product_type_storage = $this->container->get('entity_type.manager')->getStorage('commerce_product_type');

    // Create a test product type that is enabled for scheduling.
    /** @var Drupal\commerce_product\Entity\ProductType $productType */
    $this->productType = $product_type_storage->create([
      'id' => $this->productTypeName,
      'label' => $this->productTypeLabel,
      'variationType' => 'default',
    ]);

    // Add scheduler functionality to the product type, then save.
    $this->productType->setThirdPartySetting('scheduler', 'publish_enable', TRUE)
      ->setThirdPartySetting('scheduler', 'unpublish_enable', TRUE)
      ->save();

    // Enable the scheduler fields in the default form display, mimicking what
    // would be done if the entity bundle had been enabled via admin UI.
    $this->container->get('entity_display.repository')
      ->getFormDisplay('commerce_product', $this->productTypeName)
      ->setComponent('publish_on', ['type' => 'datetime_timestamp_no_default'])
      ->setComponent('unpublish_on', ['type' => 'datetime_timestamp_no_default'])
      ->save();

    // Add the body field using the existing commerce_product function.
    commerce_product_add_body_field($this->productType);

    // Create a test product type which is not enabled for scheduling.
    /** @var Drupal\commerce_product\Entity\ProductType $nonSchedulerProductType */
    $this->nonSchedulerProductType = $product_type_storage->create([
      'id' => $this->nonSchedulerProductTypeName,
      'label' => $this->nonSchedulerProductTypeLabel,
      'variationType' => 'default',
    ]);
    // Requires a separate save, not part of the create() above, if not doing
    // any other save() on the product type.
    $this->nonSchedulerProductType->save();

    /** @var Drupal\commerce\CommerceContentEntityStorage $productStorage */
    $this->productStorage = $this->container->get('entity_type.manager')->getStorage('commerce_product');

    // Add extra permissions to the role assigned to the adminUser.
    $this->addPermissionsToUser($this->adminUser, [
      'create ' . $this->productTypeName . ' commerce_product',
      'update any ' . $this->productTypeName . ' commerce_product',
      'delete any ' . $this->productTypeName . ' commerce_product',
      'create ' . $this->nonSchedulerProductTypeName . ' commerce_product',
      'update any ' . $this->nonSchedulerProductTypeName . ' commerce_product',
      'delete any ' . $this->nonSchedulerProductTypeName . ' commerce_product',
      'administer commerce_product_type',
      // 'administer commerce_store' is needed to see and use any store, i.e
      // cannot add a product without this. Is it a bug?
      'administer commerce_store',
      'access commerce_product overview',
      'view own unpublished commerce_product',
      'schedule publishing of commerce_product',
      'view scheduled commerce_product',
    ]);

    // Add extra permissions to the role assigned to the schedulerUser.
    $this->addPermissionsToUser($this->schedulerUser, [
      'create ' . $this->productTypeName . ' commerce_product',
      'update any ' . $this->productTypeName . ' commerce_product',
      'delete any ' . $this->productTypeName . ' commerce_product',
      // 'administer commerce_store' is needed to see and use any store, i.e
      // cannot add a product without this. Is it a bug?
      'administer commerce_store',
      'view own unpublished commerce_product',
      'schedule publishing of commerce_product',
    ]);
  }

  /**
   * Creates a product entity.
   *
   * @param array $values
   *   The values to use for the entity.
   *
   * @return Drupal\commerce_product\Entity\ProductInterface
   *   The created product object.
   */
  public function createProduct(array $values = []) {
    // Provide defaults for the critical values.
    $values += [
      'type' => $this->productTypeName,
      'title' => $this->randomstring(12),
    ];
    /** @var \Drupal\commerce_product\ProductInterface $product */
    $product = $this->productStorage->create($values);
    $product->save();
    return $product;
  }

  /**
   * Gets a product from storage.
   *
   * For nodes, there is drupalGetNodeByTitle() but nothing similar exists to
   * help Product testing. See getMediaItem for more details.
   *
   * @param string $name
   *   Optional name text to match on. If given and no match, returns NULL.
   *   If no $name is given then returns the product with the highest id value.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The commerce product object.
   */
  public function getProduct(?string $name = NULL) {
    $query = $this->productStorage->getQuery()
      ->accessCheck(FALSE)
      ->sort('product_id', 'DESC');
    if (!empty($name)) {
      $query->condition('title', $name);
    }
    $result = $query->execute();
    if (count($result)) {
      $id = reset($result);
      return $this->productStorage->load($id);
    }
    else {
      return NULL;
    }
  }

}
