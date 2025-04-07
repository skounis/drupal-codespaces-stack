<?php

namespace Drupal\ai_agents_form_integration\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Form Helper.
 */
class FormHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI Provider Plugin Manager.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProvider,
  ) {
  }

  /**
   * Helper to get the whole CSV as array.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return array
   *   The CSV as array.
   */
  public function getCsvAsArray(string $file_path): array {
    $csv = [];
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $csv[] = $data;
      }
      fclose($handle);
    }
    return $csv;
  }

  /**
   * Helper to get sample from CSV.
   *
   * @param string $file_path
   *   The file path.
   * @param int $limit
   *   The limit.
   * @param bool $as_array
   *   If the sample should be returned as array.
   *
   * @return array|string
   *   The sample.
   */
  public function getSampleFromCsv(string $file_path, $limit = 4, $as_array = TRUE): array|string {
    $sample = $as_array ? [] : '';
    $row = 1;
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row++;
        if ($row > $limit) {
          break;
        }
        if ($as_array) {
          $sample[] = $data;
        }
        else {
          $sample .= implode(',', $data) . "\n";
        }
      }
      fclose($handle);
    }
    return $sample;
  }

  /**
   * Helper function to figure out if the CSV has a header.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return bool
   *   If the CSV has a header.
   */
  public function csvHasHeader(string $file_path): bool {
    $sample = $this->getSampleFromCsv($file_path, 4, FALSE);

    $default = $this->aiProvider->getDefaultProviderForOperationType('chat_with_complex_json');
    // Simple prompt.
    $prompt = "The following is an excerpt of the first 4 lines of a CSV file, could you tell me if the CSV's first line is a header based on the context, just answer yes or no?\n\ncsv file:\n";
    $prompt .= $sample;
    $provider = $this->aiProvider->createInstance($default['provider_id']);
    $response = $provider->chat(new ChatInput([
      new ChatMessage('user', $prompt),
    ]), $default['model_id']);

    return strtolower($response->getNormalized()->getText()) == 'yes';
  }

}
