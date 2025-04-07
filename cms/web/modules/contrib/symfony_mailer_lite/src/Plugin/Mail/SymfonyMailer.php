<?php

namespace Drupal\symfony_mailer_lite\Plugin\Mail;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Annotation\Mail;
use Drupal\Core\Asset\AssetOptimizerInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\mailsystem\MailsystemManager;
use Drupal\symfony_mailer_lite\EmbeddedImage;
use Drupal\symfony_mailer_lite\EmbeddedImageValidatorInterface;
use Html2Text\Html2Text;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Provides a 'Drupal Symfony Mailer Lite' plugin to send emails.
 *
 * @Mail(
 *   id = "symfony_mailer_lite",
 *   label = @Translation("Drupal Symfony Mailer Lite"),
 *   description = @Translation("Drupal Symfony Mailer Lite Plugin.")
 * )
 */
class SymfonyMailer implements MailInterface, ContainerFactoryPluginInterface {

  public const FORMAT_HTML = 'text/html';
  public const FORMAT_PLAIN = 'text/plain';

  /**
   * An array containing configuration settings.
   *
   * @var array
   */
  protected $config;

  /**
   * The logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The asset resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
    * The CSS collection optimizer.
    *
    * @var \Drupal\Core\Asset\AssetOptimizerInterface
   */
  protected $cssOptimizer;

  /**
   * The CSS inliner.
   *
   * @var \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles
   */
  protected $cssInliner;

  /**
   * @var \Drupal\symfony_mailer_lite\EmbeddedImageValidatorInterface
   */
  protected $embeddedImageValidator;

  /**
   * @var \Symfony\Component\Mailer\MailerInterface
   */
  private MailerInterface $mailer;

  /**
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param ConfigFactoryInterface $config_factory
   * @param LoggerInterface $logger
   * @param RendererInterface $renderer
   * @param ModuleHandlerInterface $module_handler
   * @param MailManagerInterface $mail_manager
   * @param ThemeManagerInterface $theme_manager
   * @param AssetResolverInterface $asset_resolver
   * @param FileSystemInterface $file_system
   * @param $mime_type_guesser
   * @param AssetOptimizerInterface|null $cssOptimizer
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, LoggerInterface $logger, RendererInterface $renderer, ModuleHandlerInterface $module_handler, MailManagerInterface $mail_manager, ThemeManagerInterface $theme_manager, AssetResolverInterface $asset_resolver, EmbeddedImageValidatorInterface $embedded_image_validator, MailerInterface $mailer, AssetOptimizerInterface $cssOptimizer = NULL) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->mailManager = $mail_manager;
    $this->themeManager = $theme_manager;
    $this->assetResolver = $asset_resolver;
    $this->cssInliner = new CssToInlineStyles();
    $this->cssOptimizer = $cssOptimizer;
    $this->embeddedImageValidator = $embedded_image_validator;
    $this->mailer = $mailer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('logger.factory')->get('symfony_mailer_lite'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('plugin.manager.mail'),
      $container->get('theme.manager'),
      $container->get('asset.resolver'),
      $container->get('symfony_mailer_lite.embedded_image_validator'),
      $container->get('symfony_mailer_lite.mailer'),
      $container->get('asset.css.optimizer'),
    );
  }

  /**
   * Formats a message composed by drupal_mail().
   *
   * @param array $message
   *   A message array holding all relevant details for the message.
   *
   * @return array
   *   The message as it should be sent.
   */
  public function format(array $message) {
    $is_html = ($this->getContentType($message) === 'text/html');

    if ($is_html && empty($message['plain']) && $this->shouldGeneratePlain($message)) {
      // Generate plain text alternative. This must be done first with the
      // original message body, before overwriting it with the HTML version.
      $saved_body = $message['body'];
      $this->massageMessageBody($message, FALSE);
      $message['plain'] = $message['body'];
      $message['body'] = $saved_body;
    }

    $this->massageMessageBody($message, $is_html);

    $embeddable_images = [];
    preg_match_all('/"image:([^"]+)"/', $message['body'], $embeddable_images);
    $validated_embeddable_images = $this->getEmbeddableImages($embeddable_images, $message);
    foreach ($validated_embeddable_images as $image_id => $embedded_image) {
      $message['params']['images'][] = $embedded_image->getImageParamObject();
      $message['body'] = preg_replace($image_id, 'cid:' . $embedded_image->getCid(), $message['body']);
    }

    return $message;
  }

  protected function getEmbeddableImages(array $embeddable_image_matches, array $message) : array {
    $processed_images = [];
    // We replace all 'image:foo' in the body with a unique magic string like
    // 'cid:[randomname]' and keep track of this. It will be replaced by the
    // final "cid" in ::embed().
    $random = new Random();
    $embeddable_images = [];
    if (is_array($embeddable_image_matches[1])) {
      $image_count = count($embeddable_image_matches[1]);
      for ($i = 0; $i < $image_count; $i++) {
        if (isset($processed_images[$embeddable_image_matches[0][$i]])) {
          continue;
        }
        $processed_images[$embeddable_image_matches[0][$i]] = 1;
        $embedded_image = new EmbeddedImage($embeddable_image_matches[1][$i]);
        $embedded_image = $this->embeddedImageValidator->validateEmbeddedImage($embedded_image, $message);
        if ($embedded_image === FALSE) {
          continue;
        }
        $embedded_image->setCid($random->name(8, TRUE));
        $embeddable_images[$embeddable_image_matches[0][$i]] = $embedded_image;
      }
    }
    return $embeddable_images;
  }


  /**
   * Sends a message composed by drupal_mail().
   *
   * @param array $message
   *   A message array holding all relevant details for the message.
   *
   * @return bool
   *   TRUE if the message was successfully sent, and otherwise FALSE.
   */
  public function mail(array $message) {
    try {
      $email = new Email();

      $customTransport = $message['symfony_mailer_lite_transport'] ?? NULL;
      if ($customTransport !== NULL) {
        // https://symfony.com/doc/current/mailer.html#multiple-email-transports
        $email->getHeaders()->addTextHeader('X-Transport', $customTransport);
      }

      if (!empty($message['subject'])) {
        $email->subject($message['subject']);
      }
      $headers_to_skip = $this->headersToSkip();
      $this->normalizeHeaders($message);
      if (!empty($message['headers']) && is_array($message['headers'])) {
        foreach ($message['headers'] as $header_key => $header_value) {
          if (empty($header_value) || empty($header_key) || in_array($header_key, $headers_to_skip, FALSE)) {
            continue;
          }

          if ($header_key === 'Content-Type') {
            continue;
          }

          $email->getHeaders()->remove($header_key);
          if ($this->isIdHeader($header_key, $header_value)) {
            $email->getHeaders()->addIdHeader($header_key, $header_value);
            continue;
          }
          if ($this->isPathHeader($header_key, $header_value) && $mailbox = $this->parseMailbox($header_value)) {
            $email->getHeaders()->addPathHeader($header_key, $mailbox);
            continue;
          }
          if ($this->isMailboxListHeader($header_key, $header_value) && $mailboxes = $this->parseMailboxes($header_value)) {
            $email->getHeaders()->addMailboxListHeader($header_key, $mailboxes);
            continue;
          }
          if ($this->isMailboxHeader($header_key, $header_value) && $mailbox = $this->parseMailbox($header_value)) {
            $email->getHeaders()->addMailboxHeader($header_key, $mailbox);
            continue;
          }
          if ($this->isDateHeader($header_key, $header_value)) {
            $email->getHeaders()->addDateHeader($header_key, $header_value);
            continue;
          }
          if ($this->isParameterizedHeader($header_key, $header_value)) {
            $email->getHeaders()->addParameterizedHeader($header_key, $header_value, $this->parseParameterizedHeader($header_value));
            continue;
          }
          $email->getHeaders()->addTextHeader($header_key, $header_value);
        }
      }

      if (!empty($message['to'])) {
        $to = $this->parseMailboxes($message['to']);
        $email->to(...$to);
      }
      if (!empty($message['headers']['From'])) {
        $email->from($message['headers']['From']);
      }
      if (!empty($message['headers']['Reply-To'])) {
        $email->replyTo($message['headers']['Reply-To']);
      }
      if (!empty($message['headers']['Sender'])) {
        if (empty($email->getReplyTo())) {
          $email->replyTo($message['headers']['Sender']);
        }
        if (empty($email->getFrom())) {
          $email->from($message['headers']['Sender']);
        }
      }

      if (!empty($message['headers']['Cc'])) {
        $cc = $this->parseMailboxes($message['headers']['Cc']);
        if (!empty($cc)) {
          $email->cc(...$cc);
        }
      }
      if (!empty($message['headers']['Bcc'])) {
        $bcc = $this->parseMailboxes($message['headers']['Bcc']);
        if (!empty($bcc)) {
          $email->bcc(...$bcc);
        }
      }
      $content_type = $this->getContentType($message);
      $applicable_charset = $this->getApplicableCharset($message);

      // Set body.
      if ($content_type === 'text/html') {
        if ($message['body'] instanceof MarkupInterface) {
          $html = (string) $message['body'];
        }
        else if (is_array($message['body'])) {
          $html = implode("", $message['body']);
        }
        else {
          $html = $message['body'];
        }
        $email->html($html, $applicable_charset);
        if (!empty($message['plain'])) {
          if ($message['plain'] instanceof MarkupInterface) {
            $plain = (string) $message['plain'];
          }
          else if (is_array($message['plain'])) {
            $plain = implode("\r\n", $message['plain']);
          }
          else {
            $plain = $message['plain'];
          }
          $email->text($plain, $applicable_charset);
        }
      }
      else if (is_array($message['body'])) {
        $email->text(implode("\r\n", $message['body']), $applicable_charset);
      }
      else {
        $email->text($message['body'], $applicable_charset);
      }

      // Validate that $message['params']['files'] is an array.
      if (empty($message['params']['files']) || !is_array($message['params']['files'])) {
        $message['params']['files'] = [];
      }

      // Let other modules get the chance to add attachable files.
      $files = $this->moduleHandler->invokeAll('symfony_mailer_lite_attach', ['key' => $message['key'], 'message' => $message]);
      if (!empty($files) && is_array($files)) {
        $message['params']['files'] = array_merge(array_values($message['params']['files']), array_values($files));
      }

      // Attach files.
      if (!empty($message['params']['files']) && is_array($message['params']['files'])) {
        $this->attachFiles($email, $message['params']['files']);
      }

      // Attach files (provide compatibility with mimemail)
      if (!empty($message['params']['attachments']) && is_array($message['params']['attachments'])) {
        $this->attachFilesAsMimeMail($email, $message['params']['attachments']);
      }

      // Embed images.
      if (!empty($message['params']['images']) && is_array($message['params']['images'])) {
        $this->embedImages($email, $message['params']['images']);
      }

      // Send the message.
      $this->mailer->send($email);
      return TRUE;
    }
    catch (TransportExceptionInterface $e) {
      // some error prevented the email sending;
      $headers = ($email !== NULL) ? $email->getHeaders() : '';
      $headers = !empty($headers) ? nl2br($headers->toString()) : 'No headers were found.';
      $this->logger->error(
        'An attempt to send an e-mail message failed, and the following error
        message was returned : @exception_message<br /><br />The e-mail carried
        the following headers:<br /><br />@headers',
        ['@exception_message' => $e->getMessage(), '@headers' => $headers]);
    }
    catch (\Exception $e) {
      $this->logger->error('An attempt to send an e-mail message failed, and the following error
        message was returned : @exception_message',
        [
          '@exception_message' => $e->getMessage(),
        ]
      );
    }
    return FALSE;
  }

  /**
   * Process attachments.
   *
   * @param \Symfony\Component\Mime\Email $email
   * @param array $files
   *   The files which are to be added as attachments to the provided message.
   *
   * @internal
   */
  protected function attachFiles(Email $email, array $files) {

    // Iterate through each array element.
    foreach ($files as $file) {
      if ($file instanceof stdClass) {
        $file = (array) $file;
      }

      if (is_array($file)) {
        // Validate required fields.
        if (empty($file['uri']) || empty($file['filename']) || empty($file['filemime'])) {
          continue;
        }
        $content = file_get_contents($file['uri']);
        if ($content === FALSE) {
          $this->logger->error('Error loading email attachment file: @file', ['@file' => $file['uri']]);
          continue;
        }

        // Attach file.
        $email->addPart(new DataPart($content, $file['filename'], $file['filemime']));
      }
    }

  }

  /**
   * Process MimeMail attachments.
   *
   * @param \Symfony\Component\Mime\Email $email
   * @param array $attachments
   *   The attachments which are to be added message.
   *
   * @internal
   */
  protected function attachFilesAsMimeMail(Email $email, array $attachments) {
    // Iterate through each array element.
    foreach ($attachments as $a) {
      if (is_array($a)) {
        // Validate that we've got either 'filepath' or 'filecontent.
        if (empty($a['filepath']) && empty($a['filecontent'])) {
          continue;
        }

        // Validate required fields.
        if (empty($a['filename']) || empty($a['filemime'])) {
          continue;
        }

        // Attach file (either using a static file or provided content).
        if (!empty($a['filepath'])) {
          $file = new stdClass();
          $file->uri = $a['filepath'];
          $file->filename = $a['filename'];
          $file->filemime = $a['filemime'];
          $this->attachFiles($email, [$file]);
        }
        else {
          // Convert markup to string.
          if ($a['filecontent'] instanceof MarkupInterface) {
            $a['filecontent'] = (string) $a['filecontent'];
          }
          $email->addPart(new DataPart($a['filecontent'], $a['filename'], $a['filemime']));
        }
      }
    }
  }

  /**
   * Process inline images..
   *
   * @param \Symfony\Component\Mime\Email $email
   * @param array $images
   *   The images which are to be added as inline images to the provided
   *   message.
   *
   * @internal
   */
  protected function embedImages(Email $email, array $images) {
    if (empty($email->getHtmlBody())) {
      // Can't embed image without HTML body.
      return;
    }

    // Iterate through each array element.
    foreach ($images as $image) {
      if ($image instanceof stdClass) {
        $image = (array) $image;
      }

      // Validate required fields.
      if (empty($image['uri']) || empty($image['filename']) || empty($image['filemime']) || empty($image['cid'])) {
        continue;
      }

      // Keep track of the 'cid' assigned to the embedded image.
      $cid = NULL;

      // Get image data.
      if (UrlHelper::isValid($image['uri'], TRUE)) {
        $content = file_get_contents($image['uri']);
      }
      else {
        $content = file_get_contents(\Drupal::service('file_system')->realpath($image['uri']));
      }

      if ($content === FALSE) {
        $this->logger->error('Error loading embedded image file: @file', ['@file' => $image['uri']]);
        continue;
      }

      // Embed image.
      $part = (new DataPart($content, $image['filename'], $image['filemime']))->asInline();
      $cid = $part->getContentId();

      if (method_exists($email, 'addPart')) {
        $email->addPart($part);
      }
      else {
        // @todo Remove this once we drop support for Drupal 9
        $email->embed($content, $cid, $image['filemime']);
      }

      $html = $email->getHtmlBody();
      $html = preg_replace('/cid:' . $image['cid'] . '/', 'cid:' . $cid, $html);
      $email->html($html);
    }
  }


  /**
   * Returns the applicable charset.
   *
   * @param array $message
   *   The message for which the applicable charset is to be determined.
   *
   * @return string
   *   A string being the applicable charset.
   *
   * @internal
   */
  protected function getApplicableCharset(array $message): string {
    if (!empty($message['params']['charset'])) {
      return $message['params']['charset'];
    }

    // Get the default character set from the config.
    $charset = $this->configFactory->get('symfony_mailer_lite.message')->get('character_set');
    if (!empty($charset)) {
      return $charset;
    }

    return 'UTF-8';
  }

  /**
   * Massages the message body into the format expected for rendering.
   *
   * @param array $message
   *   The message.
   * @param boolean $is_html
   *   True if generating HTML output, false for plain text.
   *
   * @internal
   */
  protected function massageMessageBody(array &$message, $is_html) {
    $text_format = $this->getConversionTextFormat($message);
    $line_endings = Settings::get('mail_line_endings', PHP_EOL);
    $body = [];

    foreach ($message['body'] as $part) {
      if (!($part instanceof MarkupInterface)) {
        if ($is_html) {
          // Convert to HTML. The default 'plain_text' format escapes markup,
          // converts new lines to <br> and converts URLs to links.
          $body[] = check_markup($part, $text_format);
        }
        else {
          // The body will be plain text. However we need to convert to HTML
          // to render the template then convert back again. Use a fixed
          // conversion because we don't want to convert URLs to links.
          $body[] = preg_replace("|\n|", "<br />\n", HTML::escape($part)) . "<br />\n";
        }
      }
      else {
        $body[] = $part . $line_endings;
      }
    }

    // Merge all lines in the e-mail body and treat the result as safe markup.
    $message['body'] = Markup::create(implode('', $body));

    // Attempt to use the mail theme defined in MailSystem.
    if ($this->mailManager instanceof MailsystemManager) {
      $mail_theme = $this->mailManager->getMailTheme();
    }
    // Default to the active theme if MailsystemManager isn't used.
    else {
      $mail_theme = $this->themeManager->getActiveTheme()->getName();
    }

    $render = [
      '#theme' => $message['params']['theme'] ?? 'symfony_mailer_lite_email',
      '#message' => $message,
      '#is_html' => $is_html,
    ];

    if ($is_html) {
      $render['#attached']['library'] = [
        "$mail_theme/symfony_mailer_lite",
      ];
    }

    $message['body'] = $this->renderer->renderPlain($render);

    if ($is_html) {
      // Process CSS from libraries.
      $assets = AttachedAssets::createFromRenderArray($render);
      $css = '';
      // Request optimization so that the CssOptimizer performs essential
      // processing such as @include.
      foreach ($this->assetResolver->getCssAssets($assets, FALSE) as $asset) {
        if (($asset['type'] === 'file') && $asset['preprocess']) {
          $css .= $this->cssOptimizer->optimize($asset);
        } else {
          $css .= file_get_contents($asset['data']);
        }
      }

      // Let modules add CSS when calling MailManager::mail().
      if (!empty($message['params']['css']) && is_string($message['params']['css'])) {
        $css .= $message['params']['css'];
      }

      if ($css && !empty($message['body'])) {
        $message['body'] = $this->cssInliner->convert($message['body'], $css);
      }
    }
    else {
      // Convert to plain text.
      $message['body'] = (new Html2Text($message['body']))->getText();
    }
  }

  protected function headersToSkip() : array {
    return [
      'Content-Transfer-Encoding',
      'Cc',
      'Bcc',
      'From',
      'To',
    ];
  }

  /**
   * @param $key
   * @return string
   */
  protected function normalizeHeaderKey($key) : string {
    // Headers are case-insensitive, but we want to normalize them as camel case
    // so we can match them to determine the correct type of header for
    // Symfony Mailer.
    return ucwords(strtolower($key), '-');
  }

  /**
   * Normalize the case in header keys.
   *
   * @param array &$message
   *   A message array holding all relevant details for the message.
   */
  protected function normalizeHeaders(array &$message): void {
    if (!empty($message['headers']) && is_array($message['headers'])) {
      foreach ($message['headers'] as $header_key => $header_value) {
        unset($message['headers'][$header_key]);
        $header_key = $this->normalizeHeaderKey($header_key);
        $message['headers'][$header_key] = $header_value ?: NULL;
      }
    }
  }

  protected function isMultipart(array $message) : bool {
    $parts = 0;

    if (!empty($message['body'])) {
      $parts++;
    }

    if (!empty($message['plain'])) {
      $parts++;
    }

    if (!empty($message['params']['files'])) {
      $parts++;
    }

    if (!empty($message['params']['images'])) {
      $parts++;
    }

    return $parts > 1;
  }

  protected function isIdHeader($key, $value) : bool {
    return $key === 'Message-Id';
  }

  protected function isMailboxHeader($key, $value) : bool {
    return in_array($key, [
      'From',
      'Sender',
    ]);
  }

  protected function isMailboxListHeader($key, $value) : bool {
    return in_array($key, [
      'To',
      'Cc',
      'Bcc',
      'Reply-To',
    ]);
  }

  protected function isPathHeader($key, $value) : bool {
    return $key === 'Return-Path';
  }

  protected function isDateHeader($key, $value) : bool {
    return preg_match('/(Mon|Tue|Wed|Thu|Fri|Sat|Sun), [0-9]{2} (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) [0-9]{4} (2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9]) (\+|\-)([01][0-2])([0-5][0-9])/', $value);
  }

  protected function isParameterizedHeader($key, $value) : bool {
    // Assume the header is parameterized if there is at least one ;, always
    // treat the Content-Type header as parameterized.
    return (str_contains($value, ';') || $key === 'Content-Type');
  }

  protected function parseMailboxes($value) : array {
    $addresses = [];
    foreach (preg_split('/(?!\B"[^"]*),(?![^"]*"\B)/', $value) as $part) {
      $address = $this->parseMailbox($part);
      if ($address !== NULL) {
        $addresses[] = $address;
      }
    }
    return $addresses;
  }

  protected function parseMailbox(string $value) : ?Address {
    // Code copied from Drupal Symfony Mailer module's MailerHelper class,
    // which copied from \Symfony\Component\Mime\Address::create().
    if (strpos($value, '<')) {
      if (preg_match('~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~', $value, $matches)) {
        return new Address($matches['addrSpec'], trim($matches['displayName'], ' \'"'));
      }
      $this->logger->error("Could not parse @part as an address.", ['@part' => $value]);
      return NULL;
    }
    return new Address($value);
  }

  public function parseParameterizedHeader($value) : array {
    $header_parameters = [];

    // Split the provided value by ';' (semicolon), which we assume is the
    // character is used to separate the parameters.
    $parameter_pairs = explode(';', $value);

    // Iterate through the extracted parameters, and prepare each of them to be
    // added to a parameterized message header. There should be a single text
    // parameter and one or more key/value parameters in the provided header
    // value. We assume that a '=' (equals) character is used to separate the
    // key and value for each of the parameters.
    foreach ($parameter_pairs as $parameter_pair) {

      // Find out whether the current parameter pair really is a parameter
      // pair or just a single value.
      if (preg_match('/=/', $parameter_pair) > 0) {

        // Split the parameter so that we can access the parameter's key and
        // value separately.
        $parameter_pair = explode('=', $parameter_pair);

        // Validate that the parameter has been split in two, and that both
        // the parameter's key and value is accessible. If that is the case,
        // then add the current parameter's key and value to the array which
        // holds all parameters to be added to the current header.
        if (!empty($parameter_pair[0]) && !empty($parameter_pair[1])) {
          $header_parameters[trim($parameter_pair[0])] = trim($parameter_pair[1]);
        }
      }
    }

    // Add the parameterized header.
    return $header_parameters;
  }

  protected function getContentType(array $message) : string {
    // The message parameter takes priority over config. Support the alternate
    // parameter 'format' for back-compatibility.
    $content_type = $message['params']['content_type'] ?? $message['params']['format'] ?? NULL;
    // 1) check the message parameters.
    if ($content_type) {
      return $content_type;
    }

    // If the content type is being overridden, it takes priority over the one
    // set in the message.
    $config = $this->configFactory->get('symfony_mailer_lite.message');
    if ($config->get('override')) {
      $content_type = $config->get('content_type');
      if (!empty($content_type)) {
        return $content_type;
      }
    }

    // Then check the Content-Type header.
    if (isset($message['headers']['Content-Type'])) {
      return explode(';', $message['headers']['Content-Type'])[0];
    }

    // Get the default content type from the config.
    $content_type = $config->get('content_type');
    if (!empty($content_type)) {
      return $content_type;
    }

    return 'text/plain';
  }

  protected function shouldGeneratePlain(array $message) : bool {
    // Determine if a plain text alternative is required. The message parameter
    // takes priority over config. Support the alternate parameter 'convert'
    // for backward compatibility.
    if (!empty($message['params']['generate_plain']) || !empty($message['params']['convert'])) {
      return TRUE;
    }

    return (bool) $this->configFactory->get('symfony_mailer_lite.message')->get('generate_plain');
  }

  protected function getConversionTextFormat(array $message) : ?string {
    if (!empty($message['params']['text_format'])) {
      return $message['params']['text_format'];
    }
    $text_format = $this->configFactory->get('symfony_mailer_lite.message')->get('text_format');
    return !empty($text_format) ? $text_format : NULL;
  }

}
