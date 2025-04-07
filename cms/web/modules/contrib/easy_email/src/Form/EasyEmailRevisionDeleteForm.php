<?php

namespace Drupal\easy_email\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Email revision.
 *
 * @ingroup easy_email
 */
class EasyEmailRevisionDeleteForm extends ConfirmFormBase {


  /**
   * The Email revision.
   *
   * @var \Drupal\easy_email\Entity\EasyEmailInterface
   */
  protected $revision;

  /**
   * The Email storage.
   *
   * @var Drupal\Core\Entity\RevisionableStorageInterface
   */
  protected $easyEmailStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new EasyEmailRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(RevisionableStorageInterface $entity_storage, Connection $connection) {
    $this->easyEmailStorage = $entity_storage;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('easy_email'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easy_email_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the revision from %revision-date?', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.easy_email.version_history', ['easy_email' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $easy_email_revision = NULL) {
    $this->revision = $this->easyEmailStorage->loadRevision($easy_email_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->easyEmailStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('Email: deleted %title revision %revision.', ['%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    \Drupal::messenger()->addStatus(t('Revision from %revision-date of Email %title has been deleted.', ['%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()]));
    $form_state->setRedirect(
      'entity.easy_email.canonical',
       ['easy_email' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {easy_email_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.easy_email.version_history',
         ['easy_email' => $this->revision->id()]
      );
    }
  }

}
