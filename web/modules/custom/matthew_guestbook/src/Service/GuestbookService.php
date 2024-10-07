<?php

namespace Drupal\matthew_guestbook\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\file\FileInterface;

/**
 * Service for managing guestbook record-related operations.
 */
class GuestbookService {

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a GuestbookService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleExtensionList $module_extension_list,
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Add a new guestbook entry.
   *
   * @param array $fields
   *   An associative array of the field values to insert.
   *
   * @return int
   *   The ID of the inserted record.
   */
  public function addGuestbookEntry(array $fields): int {
    // Insert the guestbook entry.
    return $this->database->insert('guestbook_entries')
      ->fields($fields)
      ->execute();
  }

  /**
   * Retrieves a list of records from the database.
   *
   * @param array $conditions
   *   An associative array of conditions to filter the query, with keys as
   *   field names and values as the expected values.
   * @param array $fields
   *   An array of fields to retrieve from the database. Defaults to all fields.
   * @param bool $single
   *   Whether to fetch a single record. Defaults to FALSE.
   * @param string $order_by
   *   The field to order by. Defaults to 'created'.
   * @param string $order
   *   The sort direction ('ASC' or 'DESC'). Defaults to 'DESC'.
   *
   * @return array|object|null
   *   An array of cat records, a single record object,
   *   or NULL if no records found.
   */
  public function getGuestbookEntries(
    array $conditions = [],
    array $fields = [],
    bool $single = FALSE,
    string $order_by = 'created',
    string $order = 'DESC',
  ): object|array|null {
    // Build the query to retrieve records.
    $query = $this->database->select('guestbook_entries', 'g');

    // If no specific fields are provided, select all fields.
    if (empty($fields)) {
      $query->fields('g');
    }
    else {
      $query->fields('g', $fields);
    }

    $query->orderBy($order_by, $order);

    // Apply conditions to the query.
    foreach ($conditions as $field => $value) {
      if (is_array($value)) {
        // Use 'IN' operator for array values.
        $query->condition($field, $value, 'IN');
      }
      else {
        // Use '=' operator for single values.
        $query->condition($field, $value);
      }
    }

    // Execute the query and return the results.
    if ($single) {
      return $query->execute()->fetchObject();
    }
    return $query->execute()->fetchAll();
  }

  /**
   * Deletes a guestbook entry.
   *
   * @param int $id
   *   The ID of the entry to delete.
   *
   * @return int
   *   The number of rows affected.
   */
  public function deleteGuestbookEntry(int $id): int {
    return $this->database->delete('guestbook_entries')
      ->condition('id', $id)
      ->execute();
  }

  /**
   * Updates a guestbook entry.
   *
   * @param int $id
   *   The ID of the entry to update.
   * @param array $fields
   *   An associative array of fields to update.
   *
   * @return int
   *   The number of rows affected.
   */
  public function updateGuestbookEntry(int $id, array $fields): int {
    return $this->database->update('guestbook_entries')
      ->fields($fields)
      ->condition('id', $id)
      ->execute();
  }

  /**
   * Get the file ID from a media entity ID.
   *
   * @param int|string $media_id
   *   The media entity ID.
   *
   * @return int|string|null
   *   The file ID, or NULL if not found.
   */
  public function getFileIdFromMedia(int|string $media_id): int|string|null {
    $media = $this->entityTypeManager->getStorage('media')->load($media_id);
    if ($media) {
      $field_name = $media->getSource()->getConfiguration()['source_field'];
      if ($media->hasField($field_name)) {
        $file = $media->get($field_name)->entity;
        if ($file instanceof FileInterface) {
          return $file->id();
        }
      }
    }
    return NULL;
  }

  /**
   * Delete media and file if they are not used by other entries.
   *
   * @param int $mid
   *   The media ID.
   * @param int $fid
   *   The file ID.
   * @param string $fid_field
   *   The name of the field in the database that stores the file ID.
   * @param string $mid_field
   *   The name of the field in the database that stores the media ID.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteMediaAndFile(?int $mid, ?int $fid, string $fid_field, string $mid_field, Connection $connection): void {
    if ($mid !== NULL && $fid !== NULL) {
      // Check if the file is used by any other entries.
      $count = $connection->select('guestbook_entries', 'g')
        ->condition($fid_field, $fid)
        ->countQuery()
        ->execute()
        ->fetchField();

      // Only this entry uses the file.
      if ($count == 1) {
        // Check if the media is used by any other entries.
        $media_count = $connection->select('guestbook_entries', 'g')
          ->condition($mid_field, $mid)
          ->countQuery()
          ->execute()
          ->fetchField();

        // Only this entry uses the media.
        if ($media_count == 1) {
          // Delete the media entity.
          $media = $this->entityTypeManager->getStorage('media')->load($mid);
          $media?->delete();
        }

        // Delete the file.
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
        $file?->delete();
      }
    }
  }

  /**
   * Get the render array for a given media entity ID.
   *
   * @param mixed $media_id
   *   The ID of the media entity, or null if the field is empty.
   * @param string $field_name
   *   The field name of the media entity.
   * @param string $image_style
   *   The image style to apply.
   *
   * @return array
   *   A render array for the image,
   *   or an empty array if the media entity or file could not be loaded.
   */
  public function getMediaFileRenderArray(mixed $media_id, string $field_name, string $image_style): array {
    if (empty($media_id)) {
      return [];
    }

    $media = $this->entityTypeManager->getStorage('media')->load($media_id);

    if ($media && $media->hasField($field_name) && !$media->get($field_name)->isEmpty()) {
      $file = $media->get($field_name)->entity;
      if ($file) {
        return [
          '#theme' => 'image_style',
          '#style_name' => $image_style,
          '#uri' => $file->getFileUri(),
          '#alt' => $media->label(),
          '#attributes' => [
            'class' => [$field_name === 'field_avatar_image' ? 'entry-avatar' : 'entry-review-image'],
          ],
        ];
      }
    }

    return [];
  }

  /**
   * Get the render array for the default avatar.
   *
   * @param string $name
   *   The name of the entry author.
   *
   * @return array
   *   A render array for the default avatar image.
   */
  public function getDefaultAvatarRenderArray(string $name): array {
    $module_path = $this->moduleExtensionList->getPath('matthew_guestbook');
    $default_avatar_path = $module_path . '/images/default_avatar.jpg';

    return [
      '#theme' => 'image',
      '#uri' => $default_avatar_path,
      '#alt' => $name . "'s default avatar",
      '#attributes' => [
        'class' => ['entry-avatar'],
      ],
    ];
  }

}
