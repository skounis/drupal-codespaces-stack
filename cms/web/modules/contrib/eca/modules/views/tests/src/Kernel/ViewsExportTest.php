<?php

namespace Drupal\Tests\eca_views\Kernel;

/**
 * Kernel tests for the "eca_views" submodule.
 *
 * @group eca
 * @group eca_views
 */
class ViewsExportTest extends ViewsQueryTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'rest',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();
    $this->view->getExecutable()->newDisplay('rest_export');
    $this->view->save();
  }

  /**
   * Tests views export.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testViewsExport(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsExport $viewsExport */
    $viewsExport = $this->actionManager->createInstance('eca_views_export', [
      'view_id' => 'test_view',
      'display_id' => 'rest_export_1',
      'arguments' => 'a/b',
      'filename' => 'temporary://abc.pdf',
      'token_for_filename' => 'file_token',
      'load_results_into_token' => TRUE,
    ]);

    $this->assertTrue($viewsExport->access($this->node));
    $viewsExport->execute();
    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $this->tokenService->getTokenData('file_token');
    $this->assertEquals('temporary://abc.pdf', $dto->getValue());
  }

  /**
   * Tests view with display not allowed to export.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutAccess(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsExport $viewsExport */
    $viewsExport = $this->actionManager->createInstance('eca_views_export', [
      'view_id' => 'test_view',
      'display_id' => 'default',
      'arguments' => 'a/b',
    ]);

    $this->assertFalse($viewsExport->access($this->node));
  }

  /**
   * Tests view with display not allowed to export.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutTokenFileName(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsExport $viewsExport */
    $viewsExport = $this->actionManager->createInstance('eca_views_export', [
      'view_id' => 'test_view',
      'display_id' => 'rest_export_1',
      'arguments' => 'a/b',
      'filename' => 'temporary://abc.pdf',
      'load_results_into_token' => TRUE,
    ]);

    $this->assertTrue($viewsExport->access($this->node));
    $viewsExport->execute();
    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $this->tokenService->getTokenData('eca-view-output-filename');
    $this->assertEquals('temporary://abc.pdf', $dto->getValue());
  }

  /**
   * Tests with no display ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutDisplay(): void {
    $viewsExport = $this->actionManager->createInstance('eca_views_export', [
      'view_id' => 'test_view',
      'arguments' => 'a/b',
      'filename' => 'temporary://abc.pdf',
      'load_results_into_token' => TRUE,
    ]);

    $this->assertFalse($viewsExport->access($this->node));
    $this->assertNull($this->tokenService->getTokenData('test'));
  }

  /**
   * Tests views export with no token loading.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutTokenLoading(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsExport $viewsExport */
    $viewsExport = $this->actionManager->createInstance('eca_views_export', [
      'view_id' => 'test_view',
      'display_id' => 'rest_export_1',
      'arguments' => 'a/b',
      'filename' => 'temporary://abc.pdf',
      'token_for_filename' => 'file_token',
    ]);

    $this->assertTrue($viewsExport->access($this->node));
    $viewsExport->execute();

    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $this->tokenService->getTokenData('file_token');
    $this->assertEquals('temporary://abc.pdf', $dto->getValue());
  }

  /**
   * Tests views export without filename.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testWithoutFilename(): void {
    /** @var \Drupal\eca_views\Plugin\Action\ViewsExport $viewsExport */
    $viewsExport = $this->actionManager->createInstance('eca_views_export', [
      'view_id' => 'test_view',
      'display_id' => 'rest_export_1',
      'arguments' => 'a/b',
      'token_for_filename' => 'file_token',
      'load_results_into_token' => TRUE,
    ]);

    $this->assertTrue($viewsExport->access($this->node));
    $viewsExport->execute();

    /** @var \Drupal\eca\Plugin\DataType\DataTransferObject $dto */
    $dto = $this->tokenService->getTokenData('file_token');
    $this->assertTrue(strpos($dto->getValue(), 'temporary://') !== FALSE);
  }

}
