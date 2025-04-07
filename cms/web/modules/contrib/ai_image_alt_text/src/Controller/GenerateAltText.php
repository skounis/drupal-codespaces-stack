<?php

namespace Drupal\ai_image_alt_text\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_image_alt_text\ProviderHelper;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Defines an AI Translate Controller.
 */
class GenerateAltText extends ControllerBase {

  /**
   * AI module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $aiConfig;

  /**
   * AI image alt text configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $altTextConfig;

  /**
   * AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * Twig engine.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected TwigEnvironment $twig;

  /**
   * The AI provider helper.
   *
   * @var \Drupal\ai_image_alt_text\ProviderHelper
   */
  protected ProviderHelper $providerHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->aiConfig = $container->get('config.factory')->get('ai.settings');
    $instance->altTextConfig = $container->get('config.factory')->get('ai_image_alt_text.settings');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->twig = $container->get('twig');
    $instance->providerHelper = $container->get('ai_image_alt_text.provider');
    return $instance;
  }

  /**
   * Create an ai image alt text.
   *
   * @param \Drupal\file\Entity\File $file
   *   File entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function generate(File $file = NULL, $lang_code = 'en') {
    // Check that the user has access to the file.
    if (!$file || !$file->access('view')) {
      return new JsonResponse([
        'error' => $this->t('The file does not exist or you do not have access to it.'),
      ], 403);
    }
    // Check that the file is an image.
    if (!$file->getMimeType() || strpos($file->getMimeType(), 'image/') !== 0) {
      return new JsonResponse([
        'error' => $this->t('The file is not an image.'),
      ], 400);
    }

    // Check if there is a preferred model.
    $data = $this->providerHelper->getSetProvider();
    if (!$data) {
      return new JsonResponse([
        'error' => $this->t('No AI provider found.'),
      ], 500);
    }
    $provider = $data['provider_id'];
    $model = $data['model_id'];

    // Get the configuration.
    $prompt = $this->altTextConfig->get('prompt');
    $image_style_name = $this->altTextConfig->get('image_style');
    // If the image style is set, get the image URL.
    $image = new ImageFile();
    if ($image_style_name) {
      /** @var \Drupal\Image\Entity\ImageStyle */
      $image_style = $this->entityTypeManager->getStorage('image_style')->load($image_style_name);
      $image_uri = $file->getFileUri();
      // Get the image style url and generate it.
      $scaled_image_uri = $image_style->buildUri($image_uri);
      $image_style->createDerivative($image_uri, $scaled_image_uri);
      $image->setBinary(file_get_contents($scaled_image_uri));
      // Get the mime type from the image style image.
      $mime_type = mime_content_type($scaled_image_uri);
      $image->setMimeType($mime_type);
      $image->setFilename(basename($scaled_image_uri));
    }
    else {
      // Just get the file.
      $image->setFileFromFile($file);
    }
    $images[] = $image;
    $language = $this->languageManager->getLanguageName($lang_code) ?? 'English';
    $prompt_text = $this->twig->renderInline($prompt, [
      'entity_lang_name' => $language,
    ]);

    $input = new ChatInput([
      new ChatMessage('user',
        (string) $prompt_text,
        $images
      ),
    ]);
    $output = $provider->chat($input, $model);
    $alt_text = $output->getNormalized()->getText();
    return new JsonResponse([
      'alt_text' => $alt_text,
    ]);
  }

}
