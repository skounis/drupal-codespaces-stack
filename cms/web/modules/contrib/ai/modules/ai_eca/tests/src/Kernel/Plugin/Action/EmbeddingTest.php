<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

/**
 * Kernel tests for the "ai_eca_execute_embedding"-action plugin.
 *
 * @group ai
 */
class EmbeddingTest extends AiActionTestBase {

  /**
   * Test action-plugin with all options provided.
   */
  public function testAllOptions(): void {
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Token input name.
    $tokenInputName = $this->randomMachineName();
    $tokenInputValue = [$this->randomMachineName() => $this->randomString()];
    $this->tokenService->addTokenData($tokenInputName, $tokenInputValue);

    /** @var \Drupal\ai_eca\Plugin\Action\Embedding $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_embedding', [
      'token_result' => $tokenResultName,
      'token_input' => $tokenInputName,
      'model' => 'echoai__ai',
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replace(sprintf('[%s:input]', $tokenResultName));
    // Assert that the hardcoded string of Echo AI is present.
    $this->assertStringContainsString('Hello world!', $output);
    // Assert that the given data is present.
    $this->assertStringContainsString(reset($tokenInputValue), $output);
    // Assert that the input token name is not present.
    $this->assertStringNotContainsString($tokenInputName, $output);
  }

}
