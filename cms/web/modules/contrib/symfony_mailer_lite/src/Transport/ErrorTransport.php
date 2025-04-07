<?php

namespace Drupal\symfony_mailer_lite\Transport;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class ErrorTransport extends AbstractTransport {

  protected string $errorMessage;

  public function __construct(string $errorMessage = '') {
    parent::__construct();
    $this->errorMessage = $errorMessage;
  }

  protected function doSend(SentMessage $message): void {
    throw new TransportException($this->errorMessage);
  }

  public function __toString(): string {
      return 'error://';
  }
}
