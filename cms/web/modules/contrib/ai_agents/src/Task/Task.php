<?php

namespace Drupal\ai_agents\Task;

use Drupal\user\Entity\User;

/**
 * The general input task.
 */
class Task implements TaskInterface {

  /**
   * The title.
   *
   * @var string
   */
  protected string $title = '';

  /**
   * The description.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * The files.
   *
   * @var array
   */
  protected array $files = [];

  /**
   * The author.
   *
   * @var \Drupal\user\Entity\User|null
   */
  protected User|NULL $author = NULL;

  /**
   * The comments.
   *
   * @var array
   */
  protected array $comments = [];

  /**
   * Constructor.
   *
   * @param string $description
   *   The description.
   */
  public function __construct($description) {
    $this->description = $description;
  }

  /**
   * {@inheritDoc}
   */
  public function setTitle(string $title) {
    $this->title = $title;
  }

  /**
   * {@inheritDoc}
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * {@inheritDoc}
   */
  public function setDescription(string $description) {
    $this->description = $description;
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritDoc}
   */
  public function setFiles(array $files) {
    $this->files = $files;
  }

  /**
   * {@inheritDoc}
   */
  public function getFiles(): array {
    return $this->files;
  }

  /**
   * {@inheritDoc}
   */
  public function setAuthor(User $author) {
    $this->author = $author;
  }

  /**
   * {@inheritDoc}
   */
  public function getAuthor(): User|NULL {
    return $this->author;
  }

  /**
   * {@inheritDoc}
   */
  public function setComments(array $comments) {
    $this->comments = $comments;
  }

  /**
   * {@inheritDoc}
   */
  public function getComments(): array {
    return $this->comments;
  }

  /**
   * {@inheritDoc}
   */
  public function getAuthorsUsername(): string {
    return !is_null($this->author) ? $this->author->get('name')->value : '';
  }

  /**
   * {@inheritDoc}
   */
  public function getCommentCount(): int {
    return count($this->comments);
  }

}
