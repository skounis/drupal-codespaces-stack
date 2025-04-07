<?php

namespace Drupal\easy_email\Service;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\easy_email\Entity\EasyEmailInterface;
use Drupal\easy_email\Event\EasyEmailEvent;
use Drupal\easy_email\Event\EasyEmailEvents;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EmailAttachmentEvaluator implements EmailAttachmentEvaluatorInterface {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the EmailTokenEvaluator
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser $mimeTypeGuesser
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, FileSystemInterface $fileSystem, MimeTypeGuesser $mimeTypeGuesser, LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    $this->fileSystem = $fileSystem;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * @inheritDoc
   */
  public function evaluateAttachments(EasyEmailInterface $email, $save_attachments_to = FALSE) {
    $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_PREATTACHMENTEVAL);
    $files = $email->getEvaluatedAttachments();

    // If save attachments has been enabled, check for any programmatically added files and save them.
    if (!empty($save_attachments_to) && !empty($files)) {
      foreach ($files as $i => $file) {
        $this->saveAttachment($email, $file->uri, $save_attachments_to);
        unset($files[$i]); // This will get re-added in the direct files below.
      }
    }

    // Files attached directly to email entity
    if ($email->hasField('attachment')) {
      $attachments = $email->getAttachments();
      if (!empty($attachments)) {
        foreach ($attachments as $attachment) {
          $realpath = $this->fileSystem->realpath($attachment->getFileUri());
          if (!file_exists($realpath)) {
            $this->logger->warning('Attachment not found: @attachment', ['@attachment' => $attachment->getFileUri()]);
            continue;
          }
          if (!$this->attachmentInAllowedPath($realpath)) {
            $this->logger->warning('Attachment not in allowed path: @attachment', ['@attachment' => $attachment->getFileUri()]);
            continue;
          }
          $file = [
            'filepath' => $attachment->getFileUri(),
            'filename' => $attachment->getFilename(),
            'filemime' => $attachment->getMimeType(),
          ];
         $files[] = $file;
        }
      }
    }

    // Dynamic Attachments
    if ($email->hasField('attachment_path')) {
      $attachment_paths = $email->getAttachmentPaths();
      if (!empty($attachment_paths)) {
        foreach ($attachment_paths as $path) {
          // Relative paths that start with '/' get messed up by the realpath call below.
          if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
          }
          $realpath = $this->fileSystem->realpath($path);
          if (!file_exists($realpath)) {
            $this->logger->warning('Attachment not found: @attachment', ['@attachment' => $path]);
            continue;
          }
          if (!$this->attachmentInAllowedPath($realpath)) {
            $this->logger->warning('Attachment not in allowed path: @attachment', ['@attachment' => $path]);
            continue;
          }

          if (!empty($save_attachments_to) && $email->hasField('attachment')) {
            $this->saveAttachment($email, $realpath, $save_attachments_to);
          }

          $file = [
            'filepath' => $path,
            'filename' => $this->fileSystem->basename($path),
            'filemime' => $this->mimeTypeGuesser->guessMimeType($path),
          ];
          $files[] = $file;
        }
      }
    }

    $email->setEvaluatedAttachments($files);

    $this->eventDispatcher->dispatch(new EasyEmailEvent($email), EasyEmailEvents::EMAIL_ATTACHMENTEVAL);
  }

  /**
   * Evaluate whether an attachment is in the allowed path.
   *
   * @param string $path
   *   Path of the attachment.
   *
   * @return bool
   *   Whether or not the attachment is in the allowed path.
   */
  protected function attachmentInAllowedPath(string $path) {
    $allowed_paths = $this->configFactory->get('easy_email.settings')->get('allowed_attachment_paths');
    if (empty($allowed_paths)) {
      return FALSE;
    }
    foreach ($allowed_paths as $allowed_path) {
      $allowed_realpath = $this->fileSystem->realpath($allowed_path);
      if ($this->pcreFnmatch($allowed_realpath, $path)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Helper function to replace fnmatch().
   *
   * @param string $pattern
   *   The pattern to match against.
   * @param string $string
   *   The string to evaluate.
   *
   * @return bool
   *   Whether or not the pattern matches the string.
   */
  protected function pcreFnmatch($pattern, $string) {
    // Period at start must be the same as pattern:
    if (strpos($string, '.') === 0 && strpos($pattern, '.') !== 0) {
      return FALSE;
    }

    $transforms = [
      '\*'   => '[^/]*',
      '\?'   => '.',
      '\[\!' => '[^',
      '\['   => '[',
      '\]'   => ']',
      '\.'   => '\.',
    ];
    $pattern = '#^' . strtr(preg_quote($pattern, '#'), $transforms) . '$#i';

    return (boolean) preg_match($pattern, $string);
  }

  /**
   * @param \Drupal\easy_email\Entity\EasyEmailInterface $email
   * @param \Drupal\file\FileInterface $file
   */
  protected function saveAttachment(EasyEmailInterface $email, $source, $dest_directory) {
    \Drupal::service('file_system')->prepareDirectory($dest_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $file_entity = \Drupal::service('file.repository')->writeData(file_get_contents($source), $dest_directory . '/' . $this->fileSystem->basename($source));
    $email->addAttachment($file_entity->id());
  }

}
