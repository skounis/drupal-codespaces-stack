<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

use Symfony\Component\Yaml\Yaml;

/**
 * Kernel tests for the "ai_eca_execute_tts"-action plugin.
 *
 * @group ai
 */
class TextToSpeechTest extends AiActionTestBase {

  /**
   * Text the ai_eca_execute_tts-plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAction(): void {
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Model config.
    $config = [
      'voice' => 'onyx',
      'response_format' => 'flac',
    ];

    /** @var \Drupal\ai_eca\Plugin\Action\Embedding $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_tts', [
      'token_result' => $tokenResultName,
      'model' => 'echoai__ai',
      'config' => Yaml::dump($config),
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replaceClear(sprintf('[%s]', $tokenResultName));
    $this->assertNotEmpty($output);
  }

}
