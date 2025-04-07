<?php

namespace Drupal\ai_agents\Task;

use Drupal\user\Entity\User;

/**
 * The task is the actual input the AI Agent will process.
 *
 * This is to make sure that we can have a consistent way of handling tasks.
 * It will be multi modal and since its an interface, we can have multiple
 * implementations for specific agents.
 */
interface TaskInterface {

  /**
   * Set title title.
   *
   * @param string $title
   *   The title.
   */
  public function setTitle(string $title);

  /**
   * Get title.
   *
   * @return string
   *   The title.
   */
  public function getTitle(): string;

  /**
   * Set task description.
   *
   * @param string $description
   *   The description.
   */
  public function setDescription(string $description);

  /**
   * Get task description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string;

  /**
   * Set task files.
   *
   * @param array $files
   *   The files.
   */
  public function setFiles(array $files);

  /**
   * Get task files.
   *
   * @return array
   *   The files.
   */
  public function getFiles(): array;

  /**
   * Set task author.
   *
   * @param \Drupal\user\Entity\User $author
   *   The author.
   */
  public function setAuthor(User $author);

  /**
   * Get task author.
   *
   * @return \Drupal\user\Entity\User
   *   The author.
   */
  public function getAuthor(): User|NULL;

  /**
   * Set task comments.
   *
   * @param array $comments
   *   The comments.
   */
  public function setComments(array $comments);

  /**
   * Get task comments.
   *
   * @return array
   *   The comments.
   */
  public function getComments(): array;

  /**
   * Get author username.
   *
   * @return string
   *   The username.
   */
  public function getAuthorsUsername(): string;

  /**
   * Get comment count.
   *
   * @return int
   *   The count.
   */
  public function getCommentCount(): int;

}
