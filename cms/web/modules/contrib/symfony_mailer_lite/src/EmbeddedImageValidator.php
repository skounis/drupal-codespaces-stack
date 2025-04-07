<?php

namespace Drupal\symfony_mailer_lite;

use Drupal\Core\File\FileSystemInterface;

class EmbeddedImageValidator implements EmbeddedImageValidatorInterface {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * @var string
   */
  protected $drupalRootRealpath;

  /**
   * @var string
   */
  protected $publicFilesRealpath;


  /**
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $mimeTypeGuesser
   */
  public function __construct(FileSystemInterface $fileSystem, $mimeTypeGuesser) {
    $this->fileSystem = $fileSystem;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
  }


  public function validateEmbeddedImage(EmbeddedImage $embedded_image, array $message) {
    // Get the real path of the image file, expanding any symbolic links or ./ and ../ references.
    $image_file_realpath = $this->fileSystem->realpath($this->getDrupalRootRealpath() . $embedded_image->getImagePath());

    // Confirm the image file is within the public files directory.
    if (strpos($image_file_realpath, $this->getPublicFilesRealpath()) !== 0) {
      return FALSE;
    }

    // Get the real path of the image file relative to the Drupal root.
    $image_file_path = $this->getDrupalRootRelativePath($image_file_realpath);
    if (mb_strpos($image_file_path, '/') === 0) {
      $image_file_path = mb_substr($image_file_path, 1);
    }

    // Confirm the image file exists.
    if (!file_exists($image_file_path)) {
      return FALSE;
    }

    // Confirm the image file is actually an image.
    if ($this->mimeTypeGuesser instanceof \Symfony\Component\Mime\MimeTypeGuesserInterface) {
      $filemime = $this->mimeTypeGuesser->guessMimeType($image_file_path);
    }
    else {
      // @todo Remove this once we no longer have to support Drupal 9.
      $filemime = $this->mimeTypeGuesser->guess($image_file_path);
    }
    if (strpos($filemime, 'image/') !== 0) {
      return FALSE;
    }

    $embedded_image->setImagePath($image_file_path)
      ->setFileMime($filemime);

    return $embedded_image;
  }

  protected function getDrupalRootRealpath() {
    if ($this->drupalRootRealpath === NULL) {
      $this->drupalRootRealpath = $this->fileSystem->realpath(DRUPAL_ROOT);
    }
    return $this->drupalRootRealpath;
  }

  protected function getPublicFilesRealpath() {
    if ($this->publicFilesRealpath === NULL) {
      $this->publicFilesRealpath = $this->fileSystem->realpath('public://');
    }
    return $this->publicFilesRealpath;
  }

  protected function getDrupalRootRelativePath(string $real_path) {
    return preg_replace('/^' . preg_quote($this->getDrupalRootRealpath(), '/') . '/', '', $real_path);
  }

}
