<?php

namespace Drupal\friendlycaptcha\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\friendlycaptcha\SiteVerify;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller providing the puzzles for the user in the browser.
 */
class PuzzleController extends ControllerBase {

  /**
   * The request service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The expirable key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected KeyValueStoreExpirableInterface $keyValueStore;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The config for this module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Puzzle constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request service.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueFactory
   *   The key value factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(RequestStack $requestStack, KeyValueExpirableFactoryInterface $keyValueFactory, LoggerChannelFactoryInterface $loggerFactory, ConfigFactoryInterface $configFactory) {
    $this->request = $requestStack->getCurrentRequest();
    $this->keyValueStore = $keyValueFactory->get('friendlycaptcha');
    $this->logger = $loggerFactory->get('friendlycaptcha');
    $this->config = $configFactory->get('friendlycaptcha.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): PuzzleController {
    return new static(
      $container->get('request_stack'),
      $container->get('keyvalue.expirable'),
      $container->get('logger.factory'),
      $container->get('config.factory')
    );
  }

  /**
   * Executes the controller request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The controller response.
   *
   * @throws \Exception
   */
  public function execute(): JsonResponse {
    $enableLogging = (bool) $this->config->get('enable_validation_logging');
    $accountId = 1;
    $appId = 1;
    $puzzleExpiry = SiteVerify::EXPIRY_TIMES_5_MINUTES;
    $anonymizedIp = SiteVerify::anonymizeIp($this->request->getClientIp());
    $requestTimes = $this->keyValueStore->get($anonymizedIp, 0) + 1;
    $this->keyValueStore->setWithExpire($anonymizedIp, $requestTimes, SiteVerify::SCALING_TTL_SECONDS);
    if ($enableLogging) {
      $this->logger->info('This is request %count from IP %ip in the last 30 minutes (or longer, if there were subsequent requests)', [
        '%count' => $requestTimes,
        '%ip' => $anonymizedIp,
      ]);
    }
    foreach (array_reverse(SiteVerify::SCALING, TRUE) as $threshold => $scale) {
      if ($requestTimes > $threshold) {
        $numberOfSolutions = $scale['solutions'];
        $puzzleDifficulty = $scale['difficulty'];
        break;
      }
    }
    if (!isset($numberOfSolutions, $puzzleDifficulty)) {
      return new JsonResponse('Error in configuration', 503);
    }
    if ($enableLogging) {
      $this->logger->info('Configured with %solutions solutions of %difficulty difficulty', [
        '%solutions' => $numberOfSolutions,
        '%difficulty' => $puzzleDifficulty,
      ]);
    }
    $nonce = random_bytes(8);
    $timeHex = dechex(time());
    $accountIdHex = SiteVerify::padHex(dechex($accountId), 4);
    $appIdHex = SiteVerify::padHex(dechex($appId), 4);
    $puzzleVersionHex = SiteVerify::padHex(dechex($appId), 1);
    $puzzleExpiryHex = SiteVerify::padHex(dechex($puzzleExpiry), 1);
    $numberOfSolutionsHex = SiteVerify::padHex(dechex($numberOfSolutions), 1);
    $puzzleDifficultyHex = SiteVerify::padHex(dechex($puzzleDifficulty), 1);
    $reservedHex = SiteVerify::padHex('', 8);
    $puzzleNonceHex = SiteVerify::padHex(bin2hex($nonce), 8);
    $bufferHex = SiteVerify::padHex($timeHex, 4) . $accountIdHex . $appIdHex . $puzzleVersionHex . $puzzleExpiryHex . $numberOfSolutionsHex . $puzzleDifficultyHex . $reservedHex . $puzzleNonceHex;
    $buffer = hex2bin($bufferHex);
    $hash = SiteVerify::signBuffer($buffer, $this->config->get('site_key'));
    $puzzle = $hash . '.' . base64_encode($buffer);
    return new JsonResponse([
      'data' => [
        'puzzle' => $puzzle,
      ],
    ]);
  }

}
