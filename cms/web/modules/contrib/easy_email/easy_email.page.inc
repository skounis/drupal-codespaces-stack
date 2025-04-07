<?php

use Drupal\Core\Url;
use Drupal\Core\Link;
/**
 * @file
 * Contains easy_email.page.inc.
 *
 * Page callback for Email entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Email entities.
 *
 * Default template: easy_email.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_easy_email(array &$variables) {
  // Fetch EasyEmail Entity Object.
  /** @var \Drupal\easy_email\Entity\EasyEmailInterface $easy_email */
  $easy_email = $variables['elements']['#easy_email'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
  if ($easy_email->hasField('body_html') && !empty($easy_email->getHtmlBody())) {
    $variables['preview_html_url'] = Url::fromRoute('entity.easy_email.preview', [
      'easy_email' => $easy_email->id(),
    ]);
  }

  /** @var \Drupal\easy_email\Entity\EasyEmailTypeInterface $easy_email_type */
  $easy_email_type = \Drupal::entityTypeManager()->getStorage('easy_email_type')->load($easy_email->bundle());

  if ($easy_email->hasField('body_plain') && (!empty($easy_email_type->getGenerateBodyPlain()) || !empty($easy_email->getPlainBody()))) {
    $variables['preview_plain_url'] = Url::fromRoute('entity.easy_email.preview_plain', [
      'easy_email' => $easy_email->id(),
    ]);
  }

  $variables['headers'] = [];

  /** @var \Drupal\easy_email\Service\EmailHandlerInterface $email_handler */
  $email_handler = \Drupal::service('easy_email.handler');

  $message = $email_handler->preview($easy_email);
  if (!empty($message['headers'])) {
    foreach ($message['headers'] as $header => $value) {
      $variables['headers'][] = [
        'name' => $header,
        'value' => $value,
      ];
    }
  }
  $variables['headers'][] = [
    'name' => 'To',
    'value' => implode(', ', $easy_email->getRecipientAddresses() ?? []),
  ];
  $variables['headers'][] = [
    'name' => 'Subject',
    'value' => $easy_email->getSubject(),
  ];

  $variables['is_sent'] = $easy_email->isSent();
  $variables['sent_time'] = $easy_email->getSentTime();

  $variables['inbox_preview'] = [
    '#theme' => 'easy_email_inbox_preview_view',
    '#easy_email' => $easy_email,
  ];

  if ($attachments = $easy_email->getAttachments()) {
    foreach ($attachments as $i => $attachment) {
      $attachments[$i] = Link::fromTextAndUrl($attachment->getFilename(),  Url::fromUri(\Drupal::service('file_url_generator')->generateAbsoluteString($attachment->getFileUri()), ['attributes' => ['target' => '_blank']]));
    }
  }

  if (!empty($attachments)) {
    $variables['attachments'] = [
      '#theme' => 'item_list',
      '#items' => $attachments,
    ];
  }

}

