<?php

namespace Drupal\eca_ui\Form;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\eca_ui\Service\TokenBrowserService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ECA settings for this site.
 */
class Settings extends ConfigFormBase {

  /**
   * The default documentation domain.
   *
   * @var string|null
   */
  protected ?string $defaultDocumentationDomain;

  /**
   * Token browser.
   *
   * @var \Drupal\eca_ui\Service\TokenBrowserService
   */
  protected TokenBrowserService $tokenBrowser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Settings {
    /** @var \Drupal\eca_ui\Form\Settings $instance */
    $instance = parent::create($container);
    $instance->defaultDocumentationDomain = $container->getParameter('eca.default_documentation_domain');
    $instance->tokenBrowser = $container->get('eca_ui.service.token_browser');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'eca_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['eca.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('eca.settings');
    $form['log_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Log level'),
      '#options' => RfcLogLevel::getLevels(),
      '#default_value' => $config->get('log_level'),
      '#weight' => -20,
    ];
    if ($this->defaultDocumentationDomain) {
      $form['documentation_domain'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Documentation domain'),
        '#description' => $this->t('This domain is used for creating links to further documentation resources about ECA plugins. The official documentation resource is <a href=":url" target="_blank" rel="noreferrer nofollow">:url</a>. Leave blank to disable documentation links at all.', [':url' => $this->defaultDocumentationDomain]),
        '#default_value' => $config->get('documentation_domain'),
        '#weight' => -10,
      ];
    }
    $form['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Execute models with user'),
      '#description' => $this->t('Specify here, which user ECA should always switch to when executing models. Leave empty to let ECA always execute models with the current user. <br/>Can be a numeric user ID (UID) or a valid UUID that identifies the user.<br/>When a user is specified here, you will have access to the original ID of the session user with the <em>[session_user:uid]</em> token.'),
      '#default_value' => $config->get('user'),
      '#weight' => 0,
    ];
    $form['service_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service account user'),
      '#description' => $this->t('The service account is a Drupal use that ECA will switch to when the action "Switch to service user" will be executed in a model. <br/>Can be a numeric user ID (UID) or a valid UUID that identifies the user.'),
      '#default_value' => $config->get('service_user'),
      '#weight' => 5,
    ];
    $form['token_browser'] = $this->tokenBrowser->getTokenBrowserMarkup();
    $form['token_browser']['#weight'] = 10;
    $form['dependency_calculation'] = [
      '#type' => 'details',
      '#title' => $this->t('Dependency calculation'),
      '#open' => TRUE,
      '#weight' => 20,
      '#tree' => TRUE,
    ];
    $form['dependency_calculation']['help'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('When a model is saved, dependencies defined by used plugins are automatically added. Further dependency calculations can be enabled or disabled below.<ul><li>Calculations may be enabled to ensure the availability of possibly used configurations.</li><li>Calculations may be disabled to improve the reusability of created models.</li></ul>') . '</p>',
      '#weight' => 10,
    ];
    $dependency_calculation = $config->get('dependency_calculation') ?? [];
    $form['dependency_calculation']['bundle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Entity bundle configurations (e.g. content types)'),
      '#default_value' => in_array('bundle', $dependency_calculation, TRUE),
      '#weight' => 20,
    ];
    $form['dependency_calculation']['field_storage'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Field storage configurations'),
      '#default_value' => in_array('field_storage', $dependency_calculation, TRUE),
      '#weight' => 30,
    ];
    $form['dependency_calculation']['field_config'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Field configurations per bundle'),
      '#default_value' => in_array('field_config', $dependency_calculation, TRUE),
      '#weight' => 40,
      '#states' => [
        'disabled' => [
          [
            ':input[name="dependency_calculation[field_storage]"]' => ['checked' => FALSE],
          ],
        ],
      ],
    ];
    $form['dependency_calculation']['new_field_config'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Newly added field configurations per bundle'),
      '#default_value' => in_array('new_field_config', $dependency_calculation, TRUE),
      '#weight' => 50,
      '#states' => [
        'disabled' => [
          [
            ':input[name="dependency_calculation[field_config]"]' => ['checked' => FALSE],
          ],
          [
            ':input[name="dependency_calculation[field_storage]"]' => ['checked' => FALSE],
          ],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['user', 'service_user'] as $field) {
      if (($uid = trim($form_state->getValue($field))) !== '') {
        if (ctype_digit($uid)) {
          if (!$this->entityTypeManager->getStorage('user')->load($uid)) {
            $form_state->setErrorByName($field, $this->t('There is no user with the specified user ID %id.', ['%id' => $uid]));
          }
        }
        elseif (Uuid::isValid($uid)) {
          if (!$this->entityTypeManager->getStorage('user')
            ->loadByProperties(['uuid' => $uid])) {
            $form_state->setErrorByName($field, $this->t('There is no user with the specified UUID %id.', ['%id' => $uid]));
          }
        }
        elseif (mb_strpos($uid, '[') !== 0 && !mb_strpos($uid, ']')) {
          $form_state->setErrorByName($field, $this->t('The given input is not a valid user ID, UUID or token.'));
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('eca.settings');
    $config->set('log_level', $form_state->getValue('log_level'));
    if ($this->defaultDocumentationDomain) {
      $config->set('documentation_domain', $form_state->getValue('documentation_domain'));
    }
    $config->set('user', trim($form_state->getValue('user')));
    $config->set('service_user', trim($form_state->getValue('service_user')));
    $dependency_calculations = [];
    foreach ($form_state->getValue('dependency_calculation', []) as $k => $v) {
      if ($v) {
        $dependency_calculations[] = $k;
      }
    }
    if (!in_array('field_storage', $dependency_calculations, TRUE)) {
      $dependency_calculations = array_filter($dependency_calculations, function ($calculation) {
        return !in_array($calculation, ['field_config', 'new_field_config'], TRUE);
      });
    }
    if (!in_array('field_config', $dependency_calculations, TRUE)) {
      $dependency_calculations = array_filter($dependency_calculations, function ($calculation) {
        return $calculation !== 'new_field_config';
      });
    }
    $config->set('dependency_calculation', $dependency_calculations);
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
