<?php

namespace Drupal\eca_endpoint\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\AccessEventInterface;
use Drupal\eca\Event\RenderEventInterface;
use Drupal\eca\Event\TriggerEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The ECA endpoint controller.
 */
final class EndpointController implements ContainerInjectionInterface {

  use AjaxHelperTrait;

  /**
   * The trigger event service.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The main content renderer.
   *
   * @var \Drupal\Core\Render\MainContent\HtmlRenderer
   */
  protected HtmlRenderer $mainContentHtmlRenderer;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected ClassResolverInterface $classResolver;

  /**
   * The main content renderers.
   *
   * @var array
   */
  protected array $mainContentRenderers;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): EndpointController {
    return new EndpointController(
      $container->get('eca.trigger_event'),
      $container->get('renderer'),
      $container->get('main_content_renderer.html'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('logger.channel.eca'),
      $container->get('messenger'),
      $container->get('class_resolver'),
      $container->getParameter('main_content_renderers')
    );
  }

  /**
   * Constructs a new EcaEndpointController object.
   *
   * @param \Drupal\eca\Event\TriggerEvent $trigger_event
   *   The trigger event service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Render\MainContent\HtmlRenderer $html_renderer
   *   The main content renderer.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param array $main_content_renderers
   *   The main content renderers.
   */
  public function __construct(TriggerEvent $trigger_event, RendererInterface $renderer, HtmlRenderer $html_renderer, RouteMatchInterface $route_match, AccountInterface $current_user, ConfigFactoryInterface $config_factory, LoggerChannelInterface $logger, MessengerInterface $messenger, ClassResolverInterface $class_resolver, array $main_content_renderers) {
    $this->triggerEvent = $trigger_event;
    $this->renderer = $renderer;
    $this->mainContentHtmlRenderer = $html_renderer;
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->classResolver = $class_resolver;
    $this->mainContentRenderers = $main_content_renderers;
  }

  /**
   * Handles the request to the endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   * @param string|null $eca_endpoint_argument_1
   *   (optional) An additional path argument.
   * @param string|null $eca_endpoint_argument_2
   *   (optional) An additional path argument.
   *
   * @return \Symfony\Component\HttpFoundation\Response|array|null
   *   The response or a build array, NULL otherwise.
   */
  public function handle(Request $request, ?AccountInterface $account = NULL, ?string $eca_endpoint_argument_1 = NULL, ?string $eca_endpoint_argument_2 = NULL): mixed {
    $account = $account ?? $this->currentUser;

    $path_arguments = [];
    if (isset($eca_endpoint_argument_1)) {
      $path_arguments[] = $eca_endpoint_argument_1;
    }
    if (isset($eca_endpoint_argument_2)) {
      $path_arguments[] = $eca_endpoint_argument_2;
    }

    $neutral = AccessResult::neutral("No ECA configuration set an access result");
    $event = $this->triggerEvent->dispatchFromPlugin('eca_endpoint:access', $path_arguments, $account, $neutral);
    if (!($event instanceof AccessEventInterface) || !($result = $event->getAccessResult())) {
      $result = $neutral;
    }
    if ($result->isForbidden()) {
      // Access has been explicitly revoked. Therefore, return a 403.
      throw new AccessDeniedHttpException();
    }
    if (!$result->isAllowed()) {
      // No explicit access is allowed. Therefore, return a 404.
      // This may happen on following situations:
      // - No ECA configuration reacts upon the endpoint with given arguments
      //   at all, or
      // - An ECA configuration does react upon this for creating a response,
      //   but there is no ECA configuration that defines access for it.
      if (RfcLogLevel::DEBUG === (int) $this->configFactory->get('eca.settings')->get('log_level')) {
        $this->logger->debug("Returning a 404 page, because no access has been explicitly set for either revoking or granting access. Request path: %request_url", [
          '%request_path' => $request->getPathInfo(),
        ]);
      }
      throw new NotFoundHttpException();
    }

    $build = [];
    $response = $this->isAjax() ?
      new AjaxResponse() :
      new Response();
    // Make the response uncacheable by default.
    $response->setPrivate();

    // Keep in mind the current headers and content, to check if it got changed.
    $previous_headers = $response->headers->all();
    $previous_content = $response->getContent();

    $event = $this->triggerEvent->dispatchFromPlugin('eca_endpoint:response', $path_arguments, $request, $response, $account, $build);
    if ($response instanceof AjaxResponse) {
      if (Element::children($build)) {
        $wrapper = $request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, 'drupal_modal');
        $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers[$wrapper]);
        $response = $renderer->renderResponse($build, $request, $this->routeMatch);
      }
      foreach ($this->messenger->deleteAll() as $type => $type_messages) {
        /** @var string[]|\Drupal\Component\Render\MarkupInterface[] $type_messages */
        foreach ($type_messages as $message) {
          $response->addCommand(new MessageCommand((string) $message, NULL, ['type' => $type], FALSE));
        }
      }
      return $response;
    }
    if ($event instanceof RenderEventInterface) {
      $build = &$event->getRenderArray();
    }

    if (($response->headers->all() === $previous_headers) && ($response->getContent() === $previous_content)) {
      // No headers have been set, and no response content has been set.
      // Return the render array build as page content, if it was set.
      if ($build) {
        return $build;
      }
    }
    else {
      // The response got set, therefore it will be returned.
      if (!$response->headers->has('Content-Type')) {
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
      }
      [$content_type] = explode(';', $response->headers->get('Content-Type'), 2);
      $content_type = trim(($content_type ?: 'text/html'));
      $is_html_response = mb_strpos($content_type, 'html') !== FALSE;

      if ($build && !$response->getContent()) {
        // A render build is given, and response content has not been directly
        // set. For this case, render the render array build, and use serialized
        // contents if suitable.
        if ($is_html_response) {
          $content_response = $this->mainContentHtmlRenderer->renderResponse($build, $request, $this->routeMatch);
          // Merge in custom headers, then return it.
          foreach ($response->headers->all() as $k => $v) {
            $content_response->headers->set($k, $v);
          }
          return $content_response;
        }

        $serialized_contents = [];
        $only_serialized_contents = TRUE;
        if (!Element::children($build)) {
          $build = [$build];
        }
        foreach ($build as &$v) {
          if (isset($v['#serialized']) && !Element::children($v)) {
            $serialized_contents[] = $v['#serialized'];
            $v['#wrap'] = FALSE;
          }
          else {
            $only_serialized_contents = FALSE;
          }
        }
        unset($v);
        if ($only_serialized_contents) {
          $content = implode("\n", $serialized_contents);
        }
        else {
          $content = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
            return $this->renderer->render($build);
          });
        }

        $response->setContent($content);

        // Adjust max-age caching if necessary.
        $metadata = BubbleableMetadata::createFromRenderArray($build);
        if (isset($build['#cache']['max-age'])) {
          if ($response->getMaxAge() !== NULL) {
            $metadata->mergeCacheMaxAge($response->getMaxAge());
          }
          if (!$metadata->getCacheMaxAge()) {
            $response->setPrivate();
            $response->setMaxAge(0);
            $response->setSharedMaxAge(0);
            $response->setExpires((new DrupalDateTime("@0"))->getPhpDateTime());
          }
          elseif ($metadata->getCacheMaxAge() < $response->getMaxAge()) {
            $response->setMaxAge($metadata->getCacheMaxAge());
            $response->setSharedMaxAge($metadata->getCacheMaxAge());
          }
        }
      }

      return $response;
    }

    // No response content has been set via ECA. Therefore, return a 404.
    throw new NotFoundHttpException();
  }

  /**
   * Access check for the endpoint.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The current user account.
   * @param string|null $eca_endpoint_argument_1
   *   (optional) An additional path argument.
   * @param string|null $eca_endpoint_argument_2
   *   (optional) An additional path argument.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(?AccountInterface $account = NULL, ?string $eca_endpoint_argument_1 = NULL, ?string $eca_endpoint_argument_2 = NULL): AccessResultInterface {
    // Local menu links are being built up using a "fake" route match. Therefore
    // we catch the current route match from the global container instead.
    $route = $this->routeMatch->getRouteObject();
    if ($route && ($route->getDefault('_controller') === 'Drupal\eca_endpoint\Controller\EndpointController::handle')) {
      // Let ::handle decide whether access is allowed.
      return AccessResult::allowed()
        ->addCacheContexts([
          'url.path',
          'url.query_args',
          'user',
          'user.permissions',
        ]);
    }

    $account = $account ?? $this->currentUser;
    $path_arguments = [];
    if (isset($eca_endpoint_argument_1)) {
      $path_arguments[] = $eca_endpoint_argument_1;
    }
    if (isset($eca_endpoint_argument_2)) {
      $path_arguments[] = $eca_endpoint_argument_2;
    }

    $forbidden = AccessResult::forbidden("No ECA configuration set an access result");
    $event = $this->triggerEvent->dispatchFromPlugin('eca_endpoint:access', $path_arguments, $account, $forbidden);
    if ($event instanceof AccessEventInterface && ($result = $event->getAccessResult())) {
      return $result;
    }
    return $forbidden;
  }

}
