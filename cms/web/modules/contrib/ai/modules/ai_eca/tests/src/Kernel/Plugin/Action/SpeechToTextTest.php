<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

use Symfony\Component\Yaml\Yaml;

/**
 * Kernel tests for the "ai_eca_execute_stt"-action plugin.
 *
 * @group ai
 */
class SpeechToTextTest extends AiActionTestBase {

  /**
   * Text the ai_eca_execute_stt-plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testAction(): void {
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Token input name.
    $tokenInputName = $this->randomMachineName();
    $this->tokenService->addTokenData($tokenInputName, 'public://audio.mp3');
    // Model config.
    $config = [
      'language' => $this->randomMachineName(),
      'response_format' => 'text',
    ];

    $fileRepository = $this->container->get('file.repository');
    $fileRepository->writeData($this->randomMachineName(), 'public://audio.mp3');

    /** @var \Drupal\ai_eca\Plugin\Action\Embedding $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_stt', [
      'token_input' => $tokenInputName,
      'token_result' => $tokenResultName,
      'model' => 'echoai__ai',
      'config' => Yaml::dump($config),
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replaceClear(sprintf('[%s]', $tokenResultName));
    // Assert that the hardcoded string of Echo AI is present.
    $this->assertStringContainsString('Hello world!', $output);
    // Assert that the input token name is not present.
    $this->assertStringNotContainsString($tokenInputName, $output);
    // Assert that config is present.
    $this->assertStringContainsString(json_encode($config), $output);
  }

}
