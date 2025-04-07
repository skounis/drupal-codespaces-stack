<?php

namespace Drupal\Tests\ai_eca\Kernel\Plugin\Action;

use Symfony\Component\Yaml\Yaml;

/**
 * Kernel tests for the "ai_eca_execute_chat"-action plugin.
 *
 * @group ai
 */
class ChatTest extends AiActionTestBase {

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
    // Prompt.
    $prompt = sprintf('%s [%s]', $this->randomString(), $tokenInputName);
    // System name.
    $systemName = $this->randomMachineName();
    // Model config.
    $config = [
      'system_name' => $systemName,
      'system_prompt' => 'You are a helpful assistant.',
      'max_tokens' => 2023,
      'temperature' => 0.5,
    ];

    /** @var \Drupal\ai_eca\Plugin\Action\Chat $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_chat', [
      'token_result' => $tokenResultName,
      'token_input' => $tokenInputName,
      'model' => 'echoai__ai',
      'prompt' => $prompt,
      'config' => Yaml::dump($config),
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replace(sprintf('[%s]', $tokenResultName));
    // Assert that the hardcoded string of Echo AI is present.
    $this->assertStringContainsString('Hello world!', $output);
    // Assert that the given data is present.
    $this->assertStringContainsString(reset($tokenInputValue), $output);
    // Assert that the input token name is not present.
    $this->assertStringNotContainsString($tokenInputName, $output);
    // Assert that the system name is present.
    $this->assertStringContainsString($systemName, $output);
    // Assert that config is present.
    $this->assertStringContainsString(json_encode($config), $output);
  }

  /**
   * Test action-plugin with necessary options provided.
   */
  public function testNecessaryOptions(): void {
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Prompt.
    $prompt = $this->randomString();
    // System name.
    $systemName = $this->randomMachineName();
    // Model config.
    $config = [
      'system_name' => $systemName,
      'system_prompt' => 'You are a helpful assistant.',
    ];

    /** @var \Drupal\ai_eca\Plugin\Action\Chat $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_chat', [
      'token_result' => $tokenResultName,
      'model' => 'echoai__ai',
      'prompt' => $prompt,
      'config' => Yaml::dump($config),
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replace(sprintf('[%s]', $tokenResultName));
    // Assert that the hardcoded string of Echo AI is present.
    $this->assertStringContainsString('Hello world!', $output);
    // Assert that the prompt is present.
    $this->assertStringContainsString($prompt, $output);
    // Assert that the system name is present.
    $this->assertStringContainsString($systemName, $output);
  }

  /**
   * Test action-plugin without input specifically configured.
   */
  public function testWithoutInput(): void {
    \Drupal::configFactory()->getEditable('system.site')
      ->set('name', 'My AI/ECA site')
      ->save();
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Token input name.
    $tokenInputName = $this->randomMachineName();
    $tokenInputValue = [$this->randomMachineName() => $this->randomString()];
    $this->tokenService->addTokenData($tokenInputName, $tokenInputValue);
    // Prompt.
    $prompt = sprintf('%s [site:name] [%s]', $this->randomString(), $tokenInputName);

    /** @var \Drupal\ai_eca\Plugin\Action\Chat $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_chat', [
      'token_result' => $tokenResultName,
      'model' => 'echoai__ai',
      'prompt' => $prompt,
    ]);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $output = $this->tokenService->replace(sprintf('[%s]', $tokenResultName));
    // Assert that the hardcoded string of Echo AI is present.
    $this->assertStringContainsString('Hello world!', $output);
    // Assert that the given data is present.
    $this->assertStringContainsString(reset($tokenInputValue), $output);
    $this->assertStringContainsString('My AI/ECA site', $output);
    // Assert that the input token name is not present.
    $this->assertStringNotContainsString($tokenInputName, $output);
  }

  /**
   * Test action-plugin for system settings.
   */
  public function testSystemSettings(): void {
    // Token result name.
    $tokenResultName = $this->randomMachineName();
    // Prompt.
    $prompt = $this->randomString();
    // Model config.
    $config = [
      'system_name' => 3,
      'system_prompt' => 'You are a helpful assistant.',
    ];

    /** @var \Drupal\ai_eca\Plugin\Action\Chat $action */
    $action = $this->actionManager->createInstance('ai_eca_execute_chat', [
      'token_result' => $tokenResultName,
      'model' => 'echoai__ai',
      'prompt' => $prompt,
      'config' => Yaml::dump($config),
    ]);
    $access = $action->access(NULL, NULL, TRUE);
    $this->assertTrue($access->isForbidden());
    $this->assertEquals('[system_name]: This value should be of type string.', $access->getReason());
  }

}
