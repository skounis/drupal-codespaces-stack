<?php

namespace Drupal\dashboard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles redirection to the dashboard.
 */
class DashboardRedirectHandler {

  /**
   * Constructs a DashboardRedirectHandler object.
   *
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $urlGenerator
   *   The url generator.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   */
  public function __construct(
    protected UrlGeneratorInterface $urlGenerator,
    protected RequestStack $requestStack,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Redirect to the dashboard if there is an applicable dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  public function userLogin(AccountInterface $account): void {
    // Avoid redirect from user reset page to allow to reset the password.
    if ($this->routeMatch->getRouteName() == 'user.reset.login') {
      return;
    }

    // Respect the destination query parameter if set.
    $destination = $this->requestStack->getCurrentRequest()->query->get('destination');
    if ($destination) {
      return;
    }

    // Otherwise, redirect the user to their default dashboard, if available.
    $storage = $this->entityTypeManager->getStorage('dashboard');
    $dashboard_ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', TRUE)
      ->execute();

    foreach ($dashboard_ids as $dashboard_id) {
      $dashboard = $storage->load($dashboard_id);
      if ($dashboard->access('view', $account)) {
        $url = $this->urlGenerator->generateFromRoute('dashboard');
        $this->requestStack->getCurrentRequest()->query->set('destination', $url);
        return;
      }
    }
  }

}
