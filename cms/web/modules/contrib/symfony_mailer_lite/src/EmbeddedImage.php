<?php

namespace Drupal\symfony_mailer_lite;

class EmbeddedImage {

  /**
   * @var string
   */
  protected $imagePath;

  /**
   * @var string
   */
  protected $fileName;

  /**
   * @var string
   */
  protected $fileMime;

  /**
   * @var string
   */
  protected $cid;

  /**
   * @param string $image_path
   */
  public function __construct(string $image_path) {
    $this->imagePath = $image_path;
  }

  public function getImagePath(): string
  {
    return $this->imagePath;
  }

  public function setImagePath(string $imagePath): EmbeddedImage
  {
    $this->imagePath = $imagePath;
    return $this;
  }

  public function getFileMime(): string {
    return $this->fileMime;
  }

  public function setFileMime(string $fileMime): EmbeddedImage {
    $this->fileMime = $fileMime;
    return $this;
  }

  public function getCid(): string {
    return $this->cid;
  }

  public function setCid(string $cid): EmbeddedImage {
    $this->cid = $cid;
    return $this;
  }

  public function getFileName(): string {
    if ($this->fileName === NULL) {
      $this->fileName = basename($this->imagePath);
    }
    return $this->fileName;
  }

  public function setFileName(string $fileName): EmbeddedImage {
    $this->fileName = $fileName;
    return $this;
  }

  public function getImageParamObject() : \stdClass {
    $image_param = new \stdClass();
    $image_param->uri = $this->getImagePath();
    $image_param->filename = $this->getFileName();
    $image_param->filemime = $this->getFileMime();
    $image_param->cid = $this->getCid();
    return $image_param;
  }

}
