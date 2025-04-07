<?php

namespace Drupal\eca_ui\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\eca\Service\Modellers;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Eca.
 *
 * @package Drupal\eca\Controller
 */
final class EcaController extends ControllerBase {

  /**
   * Symfony request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * ECA modeller service.
   *
   * @var \Drupal\eca\Service\Modellers
   */
  protected Modellers $modellerServices;

  /**
   * Entity storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $storage;

  /**
   * ECA controller constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The symfony request.
   * @param \Drupal\eca\Service\Modellers $modeller_services
   *   The ECA modeller service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(Request $request, Modellers $modeller_services, EntityTypeManagerInterface $entity_type_manager) {
    $this->request = $request;
    $this->modellerServices = $modeller_services;
    $this->storage = $entity_type_manager->getStorage('eca');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): EcaController {
    return new EcaController(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('eca.service.modeller'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays add new model links for available modellers.
   *
   * Redirects to /admin/config/workflow/eca/add/[type] if only one modeller is
   * available.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array for a list of modellers that can add new models; however,
   *   if there is only one modeller available, the function will return a
   *   RedirectResponse to directly add that model type.
   */
  public function add() {
    $modellers = [];
    foreach ($this->modellerServices->getModellerDefinitions() as $modellerDefinition) {
      if (($modeller = $this->modellerServices->getModeller($modellerDefinition['id'])) && $modeller->isEditable()) {
        $url = Url::fromRoute($modellerDefinition['provider'] . '.add');
        if ($url->access()) {
          $label = $modellerDefinition['label'] ?? $modellerDefinition['id'];
          $description = $modellerDefinition['description'] ?? 'Use ' . $label . ' to create the new model.';
          $modellers[$modellerDefinition['id']] = [
            'provider' => $modellerDefinition['provider'],
            'label' => $label,
            'description' => $description,
            'add_link' => Link::fromTextAndUrl($label, $url),
          ];
        }
      }
    }
    if (count($modellers) === 1) {
      $modeller = array_shift($modellers);
      return $this->redirect($modeller['provider'] . '.add');
    }
    return [
      '#cache' => [
        'tags' => [
          'eca_modeller_plugins',
        ],
      ],
      '#theme' => 'entity_add_list',
      '#bundles' => $modellers,
      '#add_bundle_message' => $this->t('There are no modellers available yet. Install at least one module that integrates a modeller. A list of available integrations can be found on the <a href=":url" target="_blank" rel="nofollow noreferrer">ECA project page</a>.', [
        ':url' => 'https://www.drupal.org/project/eca#modellers',
      ]),
    ];
  }

  /**
   * Enable the given ECA entity if disabled.
   *
   * @param string $eca
   *   The ID of the ECA entity to enable.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response to go to the ECA collection page.
   */
  public function enable(string $eca): RedirectResponse {
    /** @var \Drupal\eca\Entity\Eca|null $config */
    $config = $this->storage->load($eca);
    if ($config && !$config->status() && $modeller = $config->getModeller()) {
      $modeller->enable();
    }
    return new RedirectResponse(Url::fromRoute('entity.eca.collection')->toString());
  }

  /**
   * Disable the given ECA entity if enabled.
   *
   * @param string $eca
   *   The ID of the ECA entity to disable.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response to go to the ECA collection page.
   */
  public function disable(string $eca): RedirectResponse {
    /** @var \Drupal\eca\Entity\Eca|null $config */
    $config = $this->storage->load($eca);
    if ($config && $config->status() && $modeller = $config->getModeller()) {
      $modeller->disable();
    }
    return new RedirectResponse(Url::fromRoute('entity.eca.collection')->toString());
  }

  /**
   * Clone the given ECA entity and save it as a new one.
   *
   * @param string $eca
   *   The ID of the ECA entity to clone.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response to go to the ECA collection page.
   */
  public function clone(string $eca): RedirectResponse {
    /** @var \Drupal\eca\Entity\Eca|null $config */
    $config = $this->storage->load($eca);
    if ($config && $config->isEditable() && $modeller = $config->getModeller()) {
      $modeller->clone();
    }
    return new RedirectResponse(Url::fromRoute('entity.eca.collection')->toString());
  }

  /**
   * Export the model from the given ECA entity.
   *
   * @param string $eca
   *   The ID of the ECA entity to export.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Redirect response to go to the ECA collection page.
   */
  public function export(string $eca): Response {
    /** @var \Drupal\eca\Entity\Eca|null $config */
    $config = $this->storage->load($eca);
    if ($config && $modeller = $config->getModeller()) {
      $response = $modeller->export();
      if ($response) {
        return $response;
      }
    }
    return new RedirectResponse(Url::fromRoute('entity.eca.collection')->toString());
  }

  /**
   * Edit the given ECA entity if the modeller supports that.
   *
   * @param string $eca
   *   The ID of the ECA entity to edit.
   *
   * @return array
   *   The render array for editing the ECA entity.
   */
  public function edit(string $eca): array {
    /** @var \Drupal\eca\Entity\Eca|null $config */
    $config = $this->storage->load($eca);
    if ($config && $config->isEditable() && $modeller = $config->getModeller()) {
      $build = $modeller->edit();
      $build['#title'] = $this->t('%label ECA Model', ['%label' => $config->label()]);
      return $build;
    }
    return [];
  }

  /**
   * Ajax callback to save an ECA model with a given modeller.
   *
   * @param string $modeller_id
   *   The plugin ID of the modeller that's being used for the posted model.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response object containing the message indicating the success of
   *   the save operation and if this is a new ECA entity to be saved, also
   *   containing a redirect instruction to the edit page of that entity.
   */
  public function save(string $modeller_id): AjaxResponse {
    $response = new AjaxResponse();
    if ($modeller = $this->modellerServices->getModeller($modeller_id)) {
      try {
        if ($modeller->save($this->request->getContent())) {
          $editUrl = Url::fromRoute('entity.eca.edit_form', ['eca' => mb_strtolower($modeller->getId())], ['absolute' => TRUE])->toString();
          $response->addCommand(new RedirectCommand($editUrl));
        }
        if (!$modeller->hasError()) {
          $message = new MessageCommand('Successfully saved the model.', NULL, [
            'type' => 'status',
          ]);
        }
        else {
          $message = new MessageCommand('Model contains error(s) and can not be saved.', NULL, [
            'type' => 'error',
          ]);
        }
      }
      catch (\Exception $ex) {
        // @todo Log details about the exception.
        $message = new MessageCommand($ex->getMessage(), NULL, [
          'type' => 'error',
        ]);
      }
    }
    else {
      $message = new MessageCommand('Invalid modeller ID.', NULL, [
        'type' => 'error',
      ]);
    }
    $response->addCommand($message);
    foreach ($this->messenger()->all() as $type => $messages) {
      foreach ($messages as $message) {
        $response->addCommand(new MessageCommand($message, NULL, ['type' => $type], FALSE));
      }
    }
    $this->messenger()->deleteAll();
    return $response;
  }

}
