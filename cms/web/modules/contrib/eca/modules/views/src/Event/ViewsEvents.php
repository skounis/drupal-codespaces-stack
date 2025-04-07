<?php

namespace Drupal\eca_views\Event;

/**
 * Views events.
 */
final class ViewsEvents {

  /**
   * Identifies \Drupal\eca_views\Event\Access event.
   *
   * @Event
   *
   * @var string
   */
  public const ACCESS = 'eca_views.access';

  /**
   * Identifies \Drupal\eca_views\Event\QuerySubstitutions event.
   *
   * @Event
   *
   * @var string
   */
  public const QUERYSUBSTITUTIONS = 'eca_views.query_substitutions';

  /**
   * Identifies \Drupal\eca_views\Event\PreView event.
   *
   * @Event
   *
   * @var string
   */
  public const PREVIEW = 'eca_views.pre_view';

  /**
   * Identifies \Drupal\eca_views\Event\PreBuild event.
   *
   * @Event
   *
   * @var string
   */
  public const PREBUILD = 'eca_views.pre_build';

  /**
   * Identifies \Drupal\eca_views\Event\PostBuild event.
   *
   * @Event
   *
   * @var string
   */
  public const POSTBUILD = 'eca_views.post_build';

  /**
   * Identifies \Drupal\eca_views\Event\PreExecute event.
   *
   * @Event
   *
   * @var string
   */
  public const PREEXECUTE = 'eca_views.pre_execute';

  /**
   * Identifies \Drupal\eca_views\Event\PostExecute event.
   *
   * @Event
   *
   * @var string
   */
  public const POSTEXECUTE = 'eca_views.post_execute';

  /**
   * Identifies \Drupal\eca_views\Event\PreRender event.
   *
   * @Event
   *
   * @var string
   */
  public const PRERENDER = 'eca_views.pre_render';

  /**
   * Identifies \Drupal\eca_views\Event\PostRender event.
   *
   * @Event
   *
   * @var string
   */
  public const POSTRENDER = 'eca_views.post_render';

  /**
   * Identifies \Drupal\eca_views\Event\QueryAlter event.
   *
   * @Event
   *
   * @var string
   */
  public const QUERYALTER = 'eca_views.query_alter';

}
