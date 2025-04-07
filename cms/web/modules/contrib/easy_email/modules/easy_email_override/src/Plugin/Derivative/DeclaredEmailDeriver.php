<?php

namespace Drupal\easy_email_override\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeclaredEmailDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * @var ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * @var ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new static();
    $instance->moduleHandler = $container->get('module_handler');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    return $instance;
  }

  public function getDerivativeDefinitions($base_plugin_definition) {
    $definitions = parent::getDerivativeDefinitions($base_plugin_definition);

    $module_list = [];
    $this->moduleHandler->invokeAllWith('mail', function (callable $hook, string $module) use (&$module_list) {
      $module_list[$module] = $this->moduleExtensionList->getName($module);
    });
    if (isset($module_list['easy_email'])) {
      unset($module_list['easy_email']);
    }
    if (isset($module_list['system'])) {
      // System module's hook_mail is only for tests.
      unset($module_list['system']);
    }

    foreach ($module_list as $module => $module_name) {
      $email_id = $module . '.*';
      if (!isset($definitions[$email_id])) {
        $definitions[$email_id] = [
          'id' => $email_id,
          'label' => $this->t('@module_name: All emails', ['@module_name' => $module_name]),
          'module' => $module,
          'key' => '*',
          'weight' => 50,
        ];
      }
    }

    $definitions['*.*'] = [
      'id' => '*.*',
      'label' => $this->t('All modules: All emails'),
      'module' => '*',
      'key' => '*',
      'weight' => 100,
    ];

    return $definitions;
  }

}

