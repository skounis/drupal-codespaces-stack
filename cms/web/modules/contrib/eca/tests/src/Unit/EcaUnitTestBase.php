<?php

namespace Drupal\Tests\eca\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * The test base call for unit tests in module Eca.
 */
abstract class EcaUnitTestBase extends UnitTestCase {

  /**
   * Mock of an entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
  }

  /**
   * Get private or protected method.
   *
   * @param string $class
   *   The fully qualified class name.
   * @param string $method
   *   The method name.
   *
   * @return \ReflectionMethod
   *   The reflection method object.
   *
   * @throws \ReflectionException
   */
  protected function getPrivateMethod(string $class, string $method): \ReflectionMethod {
    $reflector = new \ReflectionClass($class);
    $method = $reflector->getMethod($method);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Get private or protected property.
   *
   * @param string $className
   *   The fully qualified class name.
   * @param string $propertyName
   *   The property name.
   *
   * @return \ReflectionProperty
   *   The reflection property object.
   *
   * @throws \ReflectionException
   */
  protected function getPrivateProperty(string $className, string $propertyName): \ReflectionProperty {
    $reflector = new \ReflectionClass($className);
    $property = $reflector->getProperty($propertyName);
    $property->setAccessible(TRUE);
    return $property;
  }

}
