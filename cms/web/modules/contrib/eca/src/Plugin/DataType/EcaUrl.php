<?php

namespace Drupal\eca\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\Url;

/**
 * A wrapper for URL objects, provided by ECA.
 */
#[DataType(
  id: "eca_url",
  label: new TranslatableMarkup("URL (provided by ECA)")
)]
class EcaUrl extends StringData {

  /**
   * The data value.
   *
   * @var \Drupal\Core\Url|null
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    if (isset($value)) {
      assert($value instanceof Url);
    }
    parent::setValue($value, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getString(): string {
    return isset($this->value) ? $this->value->toString() : '';
  }

}
