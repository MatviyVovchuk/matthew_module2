<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Form for confirming the deletion of a guestbook entry.
 */
class GuestbookDeleteForm extends ConfirmFormBase {

  /**
   * The ID of the guestbook entry to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'guestbook_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the guestbook entry with ID @id?', ['@id' => $this->id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('matthew_guestbook.page');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = Database::getConnection();

    $query = $connection->select('guestbook_entries', 'g')
      ->fields('g', ['id', 'avatar_fid', 'review_image_fid', 'avatar_mid', 'review_image_mid'])
      ->condition('id', $this->id)
      ->execute();
    $entry = $query->fetchObject();

    if ($entry) {
      $this->deleteMediaAndFile($entry->avatar_mid, $entry->avatar_fid, 'avatar_fid', 'avatar_mid', $connection);
      $this->deleteMediaAndFile($entry->review_image_mid, $entry->review_image_fid, 'review_image_fid', 'review_image_mid', $connection);

      // Delete the entry from the database.
      $connection->delete('guestbook_entries')
        ->condition('id', $this->id)
        ->execute();

      $this->messenger()->addMessage($this->t('The guestbook entry has been deleted.'));
    }
    else {
      $this->messenger()->addError($this->t('The guestbook entry was not found.'));
    }

    // Redirect to the guestbook list.
    $form_state->setRedirectUrl($this->getCancelUrl());
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
  private function deleteMediaAndFile(?int $mid, ?int $fid, string $fid_field, string $mid_field, Connection $connection) {
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
          $media = Media::load($mid);
          if ($media) {
            $media->delete();
          }
        }

        // Delete the file.
        $file = File::load($fid);
        if ($file) {
          $file->delete();
        }
      }
    }
  }

}
