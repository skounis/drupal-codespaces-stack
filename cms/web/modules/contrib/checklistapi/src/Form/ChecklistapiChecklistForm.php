<?php

namespace Drupal\checklistapi\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a checklist form.
 */
class ChecklistapiChecklistForm implements FormInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The user storage service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  private $userStorage;

  /**
   * Constructs an instance.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage service.
   */
  public function __construct(DateFormatterInterface $date_formatter, MessengerInterface $messenger, UserStorageInterface $user_storage) {
    $this->dateFormatter = $date_formatter;
    $this->messenger = $messenger;
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $container->get('date.formatter');
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $container->get('messenger');
    /** @var \Drupal\user\UserStorageInterface $user_storage */
    $user_storage = $container->get('entity_type.manager')->getStorage('user');
    return new static($date_formatter, $messenger, $user_storage);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'checklistapi_checklist_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $checklist_id = NULL) {
    $form['#checklist'] = $checklist = checklistapi_checklist_load($checklist_id);
    $user_has_edit_access = $checklist->userHasAccess('edit');

    // Progress bar.
    $form['progress_bar'] = [
      '#theme' => 'checklistapi_progress_bar',
      '#message' => ($checklist->hasSavedProgress()) ? $this->t('Last updated @date by @user', [
        '@date' => $checklist->getLastUpdatedDate(),
        '@user' => $checklist->getLastUpdatedUser(),
      ]) : '',
      '#number_complete' => $checklist->getNumberCompleted(),
      '#number_of_items' => $checklist->getNumberOfItems(),
      '#percent_complete' => (int) round($checklist->getPercentComplete()),
      '#attached' => [
        'library' => [
          'core/drupal.progress',
        ],
      ],
    ];

    // Compact mode.
    if (checklistapi_compact_mode_is_on()) {
      $form['#attributes']['class'] = ['compact-mode'];
    }
    $form['compact_mode_link'] = [
      '#markup' => '<div class="compact-link"></div>',
    ];

    // General properties.
    $form['checklistapi'] = [
      '#attached' => [
        'library' => ['checklistapi/checklistapi'],
      ],
      '#tree' => TRUE,
      '#type' => 'vertical_tabs',
    ];

    // Loop through groups.
    $num_autochecked_items = 0;
    $groups = $checklist->items;
    foreach (Element::children($groups) as $group_key) {
      $group = &$groups[$group_key];
      $form[$group_key] = [
        '#title' => Xss::filter($group['#title']),
        '#type' => 'details',
        '#group' => 'checklistapi',
      ];
      if (!empty($group['#description'])) {
        $form[$group_key]['#description'] = Xss::filterAdmin($group['#description']);
      }

      // Loop through items.
      foreach (Element::children($group) as $item_key) {
        $item = &$group[$item_key];
        $saved_item = !empty($checklist->savedProgress['#items'][$item_key]) ? $checklist->savedProgress['#items'][$item_key] : 0;
        // Build title.
        $title = Xss::filter($item['#title']);
        if ($saved_item) {
          // Append completion details.
          /** @var \Drupal\user\UserInterface $user */
          $user = $this->userStorage->load($saved_item['#uid']);
          $title .= '<span class="completion-details"> - ' . $this->t('Completed @time by @user', [
            '@time' => $this->dateFormatter->format($saved_item['#completed'], 'short'),
            '@user' => ($user) ? $user->getDisplayName() : $this->t('[missing user]'),
          ]) . '</span>';
        }
        // Set default value.
        $default_value = FALSE;
        if ($saved_item) {
          $default_value = TRUE;
        }
        elseif (!empty($item['#default_value'])) {
          if ($default_value = $item['#default_value']) {
            $num_autochecked_items++;
          }
        }
        // Get description.
        $description = (isset($item['#description'])) ? '<p>' . Xss::filterAdmin($item['#description']) . '</p>' : '';
        // Append links.
        $links = [];
        foreach (Element::children($item) as $link_key) {
          $link = &$item[$link_key];
          $links[] = Link::fromTextAndUrl($link['#text'], $link['#url'])->toString();
        }
        if (count($links)) {
          $description .= '<div class="links">' . implode(' | ', $links) . '</div>';
        }
        // Compile the list item.
        $form[$group_key][$item_key] = [
          '#attributes' => ['class' => ['checklistapi-item']],
          '#default_value' => $default_value,
          '#description' => Xss::filterAdmin($description),
          '#disabled' => !($user_has_edit_access),
          '#title' => Xss::filterAdmin($title),
          '#type' => 'checkbox',
          '#group' => $group_key,
          '#parents' => ['checklistapi', $group_key, $item_key],
        ];
      }
    }

    // Actions.
    $form['actions'] = [
      '#access' => $user_has_edit_access,
      '#type' => 'actions',
      '#weight' => 100,
      'save' => [
        '#button_type' => 'primary',
        '#type' => 'submit',
        '#value' => $this->t('Save'),
      ],
      'clear' => [
        '#access' => $checklist->hasSavedProgress(),
        '#button_type' => 'danger',
        '#attributes' => ['class' => ['clear-saved-progress']],
        '#submit' => [[$this, 'clear']],
        '#type' => 'submit',
        '#value' => $this->t('Clear saved progress'),
      ],
    ];

    // Alert the user of autochecked items. Only set the message on GET requests
    // to prevent it from reappearing after saving the form. (Testing the
    // request method may not be the "correct" way to accomplish this.)
    if ($num_autochecked_items && $_SERVER['REQUEST_METHOD'] == 'GET') {
      $args = [
        '%checklist' => $checklist->title,
        '@num' => $num_autochecked_items,
      ];
      $message = $this->formatPlural(
        $num_autochecked_items,
        $this->t('%checklist found 1 unchecked item that was already completed and checked it for you. Save the form to record the change.', $args),
        $this->t('%checklist found @num unchecked items that were already completed and checked them for you. Save the form to record the changes.', $args)
      );
      $this->messenger->addStatus($message);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\checklistapi\ChecklistapiChecklist $checklist */
    $checklist = $form['#checklist'];

    // Save progress.
    $values = $form_state->getValue('checklistapi');
    $checklist->saveProgress($values);

    // Preserve the active tab after submission.
    $form_state->setRedirect($checklist->getRouteName(), [], [
      'fragment' => $values['checklistapi__active_tab'],
    ]);
  }

  /**
   * Form submission handler for the 'clear' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function clear(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect($form['#checklist']->getRouteName() . '.clear');
  }

}
