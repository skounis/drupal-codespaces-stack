<?php

namespace Drupal\eca_render;

/**
 * Defines events provided by the ECA Render module.
 */
final class RenderEvents {

  /**
   * Dispatches when an ECA block is being rendered.
   *
   * @Event
   *
   * @var string
   */
  public const BLOCK = 'eca_render.block';

  /**
   * Dispatches when operation links of an entity are being declared.
   *
   * @Event
   *
   * @var string
   */
  public const ENTITY = 'eca_render.entity';

  /**
   * Dispatches when operation links of an entity are being declared.
   *
   * @Event
   *
   * @var string
   */
  public const ENTITY_OPERATIONS = 'eca_render.entity_operations';

  /**
   * Dispatches when contextual links are being rendered.
   *
   * @Event
   *
   * @var string
   */
  public const CONTEXTUAL_LINKS = 'eca_render.contextual_links';

  /**
   * Dispatches when local tasks are being rendered.
   *
   * @Event
   *
   * @var string
   */
  public const LOCAL_TASKS = 'eca_render.local_tasks';

  /**
   * Dispatches when an ECA Views field is being rendered.
   *
   * @Event
   *
   * @var string
   */
  public const VIEWS_FIELD = 'eca_render.views_field';

  /**
   * Dispatches when an extra field is being rendered via ECA.
   *
   * @Event
   *
   * @var string
   */
  public const EXTRA_FIELD = 'eca_render.extra_field';

  /**
   * Dispatches when a lazy ECA element is being rendered.
   *
   * @Event
   *
   * @var string
   */
  public const LAZY_ELEMENT = 'eca_render.lazy_element';

}
