<?php

namespace Drupal\ai_chatbot\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides an AI form block.
 *
 * @Block(
 *   id = "ai_deepchat_block",
 *   admin_label = @Translation("AI DeepChat Chatbot"),
 *   category = @Translation("AI")
 * )
 */
class DeepChatFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The AI Assistant API runner.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantApiRunner
   */
  protected $aiAssistantRunner;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * The messages button service.
   *
   * @var \Drupal\ai_chatbot\Service\MessagesButtons
   */
  protected $messagesButton;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->formBuilder = $container->get('form_builder');
    $plugin->currentUser = $container->get('current_user');
    $plugin->aiAssistantRunner = $container->get('ai_assistant_api.runner');
    $plugin->fileUrlGenerator = $container->get('file_url_generator');
    $plugin->moduleHandler = $container->get('module_handler');
    $plugin->themeManager = $container->get('theme.manager');
    $plugin->messagesButton = $container->get('ai_chatbot.buttons');
    $plugin->cache = $container->get('cache.default');
    $plugin->logger = $container->get('logger.factory')->get('ai_chatbot');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'ai_assistant' => NULL,
      'bot_name' => 'Generic Chatbot',
      'bot_image' => '/core/misc/druplicon.png',
      'use_username' => TRUE,
      'default_username' => 'User',
      'use_avatar' => TRUE,
      'default_avatar' => '/core/misc/favicon.ico',
      'first_message' => 'Hello! How can I help you today?',
      'stream' => TRUE,
      'toggle_state' => 'remember',
      'width' => '400px',
      'height' => '500px',
      'placement' => 'bottom-right',
      'show_structured_results' => FALSE,
      'collapse_minimal' => FALSE,
      'style_file' => 'bard.yml',
      'show_copy_icon' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Warn people to install the CommonMark library.
    if (!class_exists('League\CommonMark\CommonMarkConverter')) {
      $form['notice'] = [
        '#theme' => 'status_messages',
        '#message_list' => [
          'warning' => [
            $this->t('To make the chat output look more formatted, we highly recommend that you install the Commonmark optional dependency from PHP League by running <code>composer require league/commonmark</code>.'),
          ],
        ],
      ];
    }
    $all = $this->entityTypeManager->getStorage('ai_assistant')->loadMultiple();
    $assistants = [];
    foreach ($all as $id => $ai_assistant) {
      $assistants[$id] = $ai_assistant->label();
    }

    $form['notice'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('Important and recommended to select the appropriate user role for this block to ensure it aligns with the AI assistance functionality and prevents anonymous users from accessing restricted features.'),
        ],
      ],
    ];

    $form['ai_assistant'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Assistant'),
      '#description' => $this->t('Select the AI Assistant to use for this chat form. You can create new %link.', [
        '%link' => Link::createFromRoute($this->t('AI Assistants here'), 'entity.ai_assistant.collection', [], [
          'attributes' => [
            'target' => '_blank',
          ],
        ])->toString(),
      ]),
      '#options' => $assistants,
      '#default_value' => $this->configuration['ai_assistant'],
    ];

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Message settings'),
      '#open' => FALSE,
    ];

    $form['messages']['first_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('First Message'),
      '#description' => $this->t('The first message to start things of. Can take markdown.'),
      '#default_value' => $this->configuration['first_message'],
    ];

    $form['messages']['bot_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bot name'),
      '#description' => $this->t('The name of the bot.'),
      '#default_value' => $this->configuration['bot_name'],
    ];

    $form['messages']['bot_image'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bot image'),
      '#description' => $this->t('The image of the bot.'),
      '#default_value' => $this->configuration['bot_image'],
    ];

    $form['messages']['default_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default User name'),
      '#description' => $this->t('The name of the user, if not fetched from the user or if not logged in.'),
      '#default_value' => $this->configuration['default_username'],
    ];

    $form['messages']['use_username'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use username'),
      '#description' => $this->t('Use the username in the chat messages if logged in.'),
      '#default_value' => $this->configuration['use_username'],
    ];

    $form['messages']['default_avatar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Avatar'),
      '#description' => $this->t('The avatar of the user, if not fetched from the user or if not logged in.'),
      '#default_value' => $this->configuration['default_avatar'],
    ];

    $form['messages']['use_avatar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use avatar'),
      '#description' => $this->t('Use the avatar in the chat messages if logged in.'),
      '#default_value' => $this->configuration['use_avatar'],
    ];

    $form['styling'] = [
      '#type' => 'details',
      '#title' => $this->t('Styling settings'),
      '#open' => FALSE,
    ];

    // Get the available styles.
    $styles = $this->getStyles();

    $form['styling']['style_file'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#description' => $this->t('The style of the chat window.'),
      '#options' => $styles,
      '#default_value' => $this->configuration['style_file'],
      '#required' => TRUE,
    ];

    $form['styling']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('The width of the chat window.'),
      '#default_value' => $this->configuration['width'],
    ];

    $form['styling']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('The height of the chat window.'),
      '#default_value' => $this->configuration['height'],
    ];

    $form['styling']['placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Placement'),
      '#description' => $this->t('The placement of the chat window.'),
      '#required' => TRUE,
      '#options' => [
        'bottom-right' => $this->t('Bottom right'),
        'bottom-left' => $this->t('Bottom left'),
      ],
      '#default_value' => $this->configuration['placement'],
    ];

    $form['styling']['collapse_minimal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsed minimal'),
      '#description' => $this->t('Show a minimal toggle button when minimized.'),
      '#default_value' => $this->configuration['collapse_minimal'],
    ];
    $form['styling']['show_copy_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add copy icon'),
      '#description' => $this->t('Adds a copy icon below each text so you can easily copy paste it.'),
      '#default_value' => $this->configuration['show_copy_icon'],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['stream'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stream'),
      '#description' => $this->t('Stream the messages in real-time.'),
      '#default_value' => $this->configuration['stream'],
    ];

    $form['advanced']['show_structured_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show structured results'),
      '#description' => $this->t('Show the structured results from the actions taken.'),
      '#default_value' => $this->configuration['show_structured_results'],
    ];

    $form['advanced']['toggle_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Toggle state'),
      '#description' => $this->t('The state of the toggle button.'),
      '#options' => [
        'remember' => $this->t('Remember'),
        'open' => $this->t('Opened'),
        'close' => $this->t('Closed'),
      ],
      '#default_value' => $this->configuration['toggle_state'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['ai_assistant'] = $form_state->getValue('ai_assistant');
    $this->configuration['bot_name'] = $form_state->getValue('messages')['bot_name'];
    $this->configuration['bot_image'] = $form_state->getValue('messages')['bot_image'];
    $this->configuration['use_username'] = $form_state->getValue('messages')['use_username'];
    $this->configuration['default_username'] = $form_state->getValue('messages')['default_username'];
    $this->configuration['use_avatar'] = $form_state->getValue('messages')['use_avatar'];
    $this->configuration['default_avatar'] = $form_state->getValue('messages')['default_avatar'];
    $this->configuration['first_message'] = $form_state->getValue('messages')['first_message'];
    $this->configuration['style_file'] = $form_state->getValue('styling')['style_file'];
    $this->configuration['width'] = $form_state->getValue('styling')['width'];
    $this->configuration['height'] = $form_state->getValue('styling')['height'];
    $this->configuration['placement'] = $form_state->getValue('styling')['placement'];
    $this->configuration['collapse_minimal'] = $form_state->getValue('styling')['collapse_minimal'];
    $this->configuration['show_copy_icon'] = $form_state->getValue('styling')['show_copy_icon'];
    $this->configuration['stream'] = $form_state->getValue('advanced')['stream'];
    $this->configuration['show_structured_results'] = $form_state->getValue('advanced')['show_structured_results'];
    $this->configuration['toggle_state'] = $form_state->getValue('advanced')['toggle_state'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $assistant */
    $assistant = $this->entityTypeManager->getStorage('ai_assistant')->load($this->configuration['ai_assistant']);

    $this->aiAssistantRunner->setAssistant($assistant);
    // Check if the assistant is setup and that the user has access to it.
    if (!$this->aiAssistantRunner->isSetup() || !$this->aiAssistantRunner->userHasAccess()) {
      $this->logger->warning('The AI Assistants AI provider is not setup or you are exposing it to a user that does not have access to it.');
      return [];
    }
    $this->aiAssistantRunner->streamedOutput($this->configuration['stream'] ?? FALSE);
    $block = [];

    $block['#theme'] = 'ai_deepchat';
    $block['#attached']['library'][] = 'ai_chatbot/deepchat';

    $user_data = $this->getUserData();

    $this->configuration['default_username'] = $user_data['username'];
    $this->configuration['default_avatar'] = $user_data['avatar'];
    $block['#settings'] = $this->configuration;
    $block['#deepchat_settings'] = $this->getDeepChatParameters($this->configuration['style_file']);
    $block['#current_theme'] = 'chatbot-' . $this->themeManager->getActiveTheme()->getName();
    $block['#attached']['drupalSettings']['ai_deepchat']['assistant_id'] = $this->aiAssistantRunner->getAssistant()->id();
    $block['#attached']['drupalSettings']['ai_deepchat']['thread_id'] = $this->aiAssistantRunner->getThreadsKey();
    $block['#attached']['drupalSettings']['ai_deepchat']['bot_name'] = $this->configuration['bot_name'];
    $block['#attached']['drupalSettings']['ai_deepchat']['bot_image'] = $this->configuration['bot_image'];
    $block['#attached']['drupalSettings']['ai_deepchat']['default_username'] = $user_data['username'];
    $block['#attached']['drupalSettings']['ai_deepchat']['default_avatar'] = $user_data['avatar'];
    $block['#attached']['drupalSettings']['ai_deepchat']['toggle_state'] = $this->configuration['toggle_state'];
    $block['#attached']['drupalSettings']['ai_deepchat']['width'] = $this->configuration['width'];
    $block['#attached']['drupalSettings']['ai_deepchat']['height'] = $this->configuration['height'];
    $block['#attached']['drupalSettings']['ai_deepchat']['first_message'] = $this->configuration['first_message'];
    $block['#attached']['drupalSettings']['ai_deepchat']['placement'] = $this->configuration['placement'];
    $block['#attached']['drupalSettings']['ai_deepchat']['show_structured_results'] = $this->configuration['show_structured_results'];
    $block['#attached']['drupalSettings']['ai_deepchat']['collapse_minimal'] = $this->configuration['collapse_minimal'];
    $block['#attached']['drupalSettings']['ai_deepchat']['show_copy_icon'] = $this->configuration['show_copy_icon'];
    $block['#attached']['drupalSettings']['ai_deepchat']['messages'] = $this->historicalMessages();

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * Get the context objects actual output.
   *
   * @param string $style
   *   The style to get the parameters for.
   *
   * @return array
   *   Return the parameters.
   */
  public function getDeepChatParameters(string $style) {
    // Some basic settings.
    $style_parameters = $this->getStyleParameters($style);
    // Special solution for style.
    $style = $style_parameters['style'] ?? '';
    // Add ; if its not there.
    if ($style && substr($style, -1) != ';') {
      $style .= '; ';
    }
    $style .= 'height: ' . $this->configuration['height'] . '; width: ' . $this->configuration['width'] . ';';

    if ($style_parameters) {
      unset($style_parameters['style']);
    }
    $deepchat['style'] = $style;
    foreach ($style_parameters as $key => $value) {
      if (isset($deepchat[$key])) {
        $deepchat[$key] = array_merge_recursive($deepchat[$key], $value);
      }
      else {
        $deepchat[$key] = $value;
      }
    }

    // Override the avatars.
    $user_data = $this->getUserData();
    $deepchat['avatars']['ai']['src'] = $this->configuration['bot_image'];
    $deepchat['avatars']['user']['src'] = $user_data['avatar'];
    $deepchat['class'] = 'deepchat-element';
    $deepchat['intromessage']['text'] = $this->configuration['first_message'];

    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['width'] = '25px';
    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['height'] = '25px';
    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['display'] = 'inline';
    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['float'] = 'none';

    // Enable displayServiceErrorMessages by default so that we can display
    // specific error messages.
    $deepchat['errorMessages'] = [
      'displayServiceErrorMessages' => TRUE,
    ];

    // Let people run hooks to change this.
    $this->moduleHandler->invokeAll('deepchat_settings', [&$deepchat]);

    $deepchat['id'] = 'chat-element';

    // Create the url.
    $url = Url::fromRoute(
      'ai_chatbot.api',
    );

    // Fix the call.
    $deepchat['connect'] = [
      'url' => $url->toString(),
      'method' => 'POST',
      'stream' => $this->configuration['stream'],
      'additionalBodyProps' => [
        'assistant_id' => $this->configuration['ai_assistant'],
        'stream' => $this->configuration['stream'],
        'structured_results' => $this->configuration['show_structured_results'],
        'show_copy_icon' => $this->configuration['show_copy_icon'],
      ],
    ];

    // For now unset any speech to text.
    if (isset($deepchat['speechToText'])) {
      unset($deepchat['speechToText']);
    }
    if (isset($deepchat['microphone'])) {
      unset($deepchat['microphone']);
    }

    // Now do JSON encode on all the settings that should have it.
    foreach ($deepchat as $key => $value) {
      if (is_array($value)) {
        $deepchat[$key] = Json::encode($value);
      }
    }

    return $deepchat;
  }

  /**
   * Get styles available.
   *
   * This will scan this modules folder deepchat_styles and also the enabled
   * themes deepchat_styles folder for styles that can be used.
   *
   * @return array
   *   Return an array of styles.
   */
  public function getStyles() {
    // Get the folder of this module.
    $module_path = $this->moduleHandler->getModule('ai_chatbot')->getPath();
    $styles = $this->getStylesFromPath($module_path . '/deepchat_styles');
    return $styles;
  }

  /**
   * Helper function to look for styles.
   *
   * @param string $path
   *   The path to look for styles in.
   * @param string $prefix
   *   The prefix to use for the styles.
   *
   * @return array
   *   Return an array of styles.
   */
  protected function getStylesFromPath(string $path, string $prefix = '') {
    $styles = [];

    foreach (scandir($path) as $file) {
      // If its a yaml or yml file.
      if (preg_match('/\.ya?ml$/', $file)) {
        $style = Yaml::parse(file_get_contents($path . '/' . $file));
        if (isset($style['name']) && isset($style['parameters'])) {
          $key = $prefix ? $prefix . ':' . $file : $file;
          $styles[$key] = $style['name'];
        }
      }
    }
    return $styles;
  }

  /**
   * Get the style YAML files parameters.
   *
   * @param string $style
   *   The style to get the parameters for.
   *
   * @return array
   *   Return the parameters.
   */
  public function getStyleParameters(string $style) {
    // If its cached, get it cached.
    $key = 'ai_chatbot:style:' . $style;
    $data = $this->cache->get($key);
    if ($data) {
      return $data->data;
    }

    $module_path = $this->moduleHandler->getModule('ai_chatbot')->getPath();
    $path = $module_path . '/deepchat_styles/' . $style;
    $style = Yaml::parse(file_get_contents($path));
    $this->cache->set($key, $style['parameters'], CacheBackendInterface::CACHE_PERMANENT, ['ai_chatbot:style']);
    return $style['parameters'];
  }

  /**
   * Helper function to get the avatar and the account if wanted.
   *
   * @return array
   *   Return the avatar and the account.
   */
  public function getUserData() {
    $user = $this->currentUser->getAccount();
    // Figure out username and avatar based on settings.
    $user = $this->currentUser->getAccount();
    $username = $this->configuration['default_username'];
    if ($user->isAuthenticated() && $this->configuration['use_username']) {
      $username = $user->getDisplayName();
    }

    $avatar = $this->configuration['default_avatar'];
    if ($user->isAuthenticated() && $this->configuration['use_avatar']) {
      $userEntity = $this->entityTypeManager->getStorage('user')->load($user->id());
      if (!empty($userEntity->user_picture->entity)) {
        $avatar = $this->fileUrlGenerator->generateAbsoluteString($userEntity->user_picture->entity->getFileUri());
      }
    }
    return [
      'username' => $username,
      'avatar' => $avatar,
    ];
  }

  /**
   * Get historical messages.
   *
   * @return array
   *   Return the historical messages.
   */
  public function historicalMessages() {
    $messages = [];
    if ($this->aiAssistantRunner->getAssistant()->get('allow_history') == 'none') {
      return $messages;
    }
    $session_messages = $this->aiAssistantRunner->getMessageHistory();
    $converter = NULL;
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      // Ignore the non-use statement loading since this dependency may not
      // exist.
      // @codingStandardsIgnoreLine
      $converter = new \League\CommonMark\CommonMarkConverter();
    }
    foreach ($session_messages as $message) {
      // Only show messages newer then 1 day and not finished messages.
      if (isset($message['timestamp']) && $message['timestamp'] > strtotime('-1 day')) {
        $new_message = [
          'role' => $message['role'],
          'html' => $converter ? $converter->convert($message['message'])->__toString() : $message['message'],
        ];
        // Add the buttons.
        $buttons = [];
        if ($message['role'] == 'assistant') {
          if ($this->configuration['show_copy_icon']) {
            $buttons[] = [
              'svg' => $this->moduleHandler->getModule('ai_chatbot')->getPath() . '/assets/copy-icon.svg',
              'class' => ['copy'],
              'alt' => $this->t('Copy message'),
              'title' => $this->t('Copy message'),
              'weight' => 0,
            ];
          }
          $new_message['html'] .= $this->messagesButton->getRenderedButtons($buttons, $this->configuration['ai_assistant'], $this->aiAssistantRunner->getThreadsKey(), TRUE);
        }
        $messages[] = $new_message;
      }
    }
    return $messages;
  }

}
