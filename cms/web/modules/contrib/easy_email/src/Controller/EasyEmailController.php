<?php

namespace Drupal\easy_email\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\easy_email\Entity\EasyEmailTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class EasyEmailController.
 *
 *  Returns responses for Email routes.
 */
class EasyEmailController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * EasyEmailController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   */
  public function __construct(RequestStack $requestStack, FormBuilderInterface $formBuilder) {
    $this->requestStack = $requestStack;
    $this->formBuilder = $formBuilder;
  }


  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('form_builder')
    );
  }

  protected function buildEntityFromFormState() {
    $form_build_id = $this->requestStack->getCurrentRequest()->get('form_build_id');

    if (!empty($form_build_id)) {
      $form_state = new FormState();
      $form = $this->formBuilder->getCache($form_build_id, $form_state);
      if (!empty($form)) {
        $easy_email = $form_state->getFormObject()->buildEntity($form, $form_state);
        if ($easy_email->isNew()) {
          // Only allow this to work for previews, not for editing saved emails
          return $easy_email;
        }
      }
    }

    return NULL;
  }

  public function previewType(EasyEmailTypeInterface $easy_email_type) {
    $store = \Drupal::service('tempstore.private')->get('easy_email_type_preview');
    $uuid = $this->requestStack->getCurrentRequest()->get('uuid');
    if (!empty($uuid) && $preview = $store->get($uuid)) {
      $easy_email = $preview->getFormObject()->getEntity();
      if (!empty($easy_email)) {
        return $this->preview($easy_email);
      }
    }

    throw new AccessDeniedHttpException();
  }

  public function previewTypePlain(EasyEmailTypeInterface $easy_email_type) {

    $store = \Drupal::service('tempstore.private')->get('easy_email_type_preview');
    $uuid = $this->requestStack->getCurrentRequest()->get('uuid');
    if (!empty($uuid) && $preview = $store->get($uuid)) {
      $easy_email = $preview->getFormObject()->getEntity();
      if (!empty($easy_email)) {
        return $this->previewPlain($easy_email);
      }
    }
    throw new AccessDeniedHttpException();
  }

  public function previewPage(EasyEmailTypeInterface $easy_email_type) {
    $email = \Drupal::entityTypeManager()->getStorage('easy_email')->create([
      'type' => $easy_email_type->id(),
    ]);
    /** @var \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder */
    $entity_form_builder = \Drupal::service('entity.form_builder');
    $form = $entity_form_builder->getForm($email, 'default', ['easy_email_type_preview' => TRUE]);
    return $form;
  }

  public function previewPageTitle(EasyEmailTypeInterface $easy_email_type) {
    return $this->t('Preview: %title', ['%title' => $easy_email_type->label()]);
  }

  public function preview(EasyEmailInterface $easy_email) {
    $message = \Drupal::service('easy_email.handler')->preview($easy_email);
    $body = $message['body'];
    // If email is plain text, HTML body is empty.
    $content_type = $this->getContentType($message);
    if ($content_type === 'text/plain') {
      $body = '';
    }
    return $this->sendNormalizedResponse($body, 'text/html; charset=utf-8');
  }

  public function previewPlain(EasyEmailInterface $easy_email) {
    $message = \Drupal::service('easy_email.handler')->preview($easy_email);
    $body = !empty($message['plain']) ? $message['plain'] : '';
    // If email is plain text, plain text body is the main body.
    $content_type = $this->getContentType($message);
    if ($content_type === 'text/plain') {
      $body = $message['body'];
    }
    return $this->sendNormalizedResponse($body, 'text/plain; charset=utf-8');
  }

  protected function sendNormalizedResponse($body, $content_type) {
    if (is_array($body)) {
      $body = \Drupal::service('renderer')->render($body);
    }
    $body = trim($body);
    $response = new Response();
    $response->setContent($body);
    $response->headers->set('Content-Type', $content_type);
    return $response;
  }

  /**
   * Get content type from message array.
   *
   * @param array $message
   *
   * @return string
   */
  protected function getContentType($message) {
    $content_type_header = NULL;
    // Headers end up under params with Symfony Mailer.
    if (!empty($message['headers']['Content-Type'])) {
      $content_type_header = $message['headers']['Content-Type'];
    }
    if (str_contains($content_type_header, 'text/html')) {
      return 'text/html';
    }
    return 'text/plain';
  }

  /**
   * Displays a Email  revision.
   *
   * @param int $easy_email_revision
   *   The Email  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($easy_email_revision) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entity_type_manager */
    $entity_type_manager = $this->entityTypeManager();
    $easy_email = $entity_type_manager->getStorage('easy_email')->loadRevision($easy_email_revision);
    $view_builder = $entity_type_manager->getViewBuilder('easy_email');

    return $view_builder->view($easy_email);
  }

  /**
   * Page title callback for a Email  revision.
   *
   * @param int $easy_email_revision
   *   The Email  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($easy_email_revision) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entity_type_manager */
    $entity_type_manager = $this->entityTypeManager();
    $easy_email = $entity_type_manager->getStorage('easy_email')->loadRevision($easy_email_revision);
    return $this->t('Revision of %title from %date', ['%title' => $easy_email->label(), '%date' => \Drupal::service('date.formatter')->format($easy_email->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Email .
   *
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $easy_email
   *   A Email  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(EasyEmailInterface $easy_email) {
    $account = $this->currentUser();
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entity_type_manager */
    $entity_type_manager = $this->entityTypeManager();
    $easy_email_storage = $entity_type_manager->getStorage('easy_email');

    $build['#title'] = $this->t('Revisions for %title', ['%title' => $easy_email->label()]);
    $header = [$this->t('Revision'), $this->t('Status'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all email revisions") || $account->hasPermission('administer email entities')));
    $delete_permission = (($account->hasPermission("delete all email revisions") || $account->hasPermission('administer email entities')));

    $rows = [];

    $vids = $easy_email_storage->revisionIds($easy_email);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\easy_email\Entity\EasyEmailInterface $revision */
      $revision = $easy_email_storage->loadRevision($vid);
      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];

      // Use revision link to link to revisions that are not active.
      $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
      if ($vid != $easy_email->getRevisionId()) {
        $link = Link::fromTextAndUrl($date, Url::fromRoute('entity.easy_email.revision', ['easy_email' => $easy_email->id(), 'easy_email_revision' => $vid]))->toString();
      }
      else {
        $link = $easy_email->tolink($date)->toString();
      }

      $row = [];
      $column = [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
          '#context' => [
            'date' => $link,
            'username' => \Drupal::service('renderer')->renderPlain($username),
            'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
          ],
        ],
      ];
      $row[] = $column;
      $row[] = [
        'data' => $revision->isSent() ? $this->t('Sent') : $this->t('Unsent'),
      ];

      if ($latest_revision) {
        $row[] = [
          'data' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Current revision'),
            '#suffix' => '</em>',
          ],
        ];
        foreach ($row as &$current) {
          $current['class'] = ['revision-current'];
        }
        $latest_revision = FALSE;
      }
      else {
        $links = [];
        if ($revert_permission) {
          $links['revert'] = [
            'title' => $this->t('Revert'),
            'url' => $has_translations ?
              Url::fromRoute('entity.easy_email.translation_revert', ['easy_email' => $easy_email->id(), 'easy_email_revision' => $vid, 'langcode' => $langcode]) :
              Url::fromRoute('entity.easy_email.revision_revert', ['easy_email' => $easy_email->id(), 'easy_email_revision' => $vid]),
          ];
        }

        if ($delete_permission) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.easy_email.revision_delete', ['easy_email' => $easy_email->id(), 'easy_email_revision' => $vid]),
          ];
        }

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }

      $rows[] = $row;
    }

    $build['easy_email_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
