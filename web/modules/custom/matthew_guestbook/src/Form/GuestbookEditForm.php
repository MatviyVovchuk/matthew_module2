<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Implements a custom Guestbook edit form.
 */
class GuestbookEditForm extends GuestbookForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'guestbook_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add form fields.
    $form = parent::buildForm($form, $form_state);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
    ];

    // Fetch the record ID from the route parameters.
    $route_match = \Drupal::routeMatch();
    $route_parameters = $route_match->getParameters();
    $id = $route_parameters->get('id');

    // Check if $id is available.
    if (!empty($id)) {
      // Load the cat record from the database.
      $record = Database::getConnection()->select('guestbook_entries', 'm')
        ->fields('m')
        ->condition('id', $id)
        ->execute()
        ->fetchAssoc();

      // Set default values for form elements.
      if ($record) {
        $form['name']['#default_value'] = $record['name'];
        $form['email']['#default_value'] = $record['email'];
        $form['phone']['#default_value'] = $record['phone'];
        $form['message']['#default_value'] = $record['message'];
        $form['review']['#default_value'] = $record['review'];

        // If there is a avatar_fid,
        // load the file entity and set it as the default value.
        if (!empty($record['avatar_mid'])) {
          $form['avatar']['#default_value'] = $record['avatar_mid'];
        }

        // If there is a avatar_fid,
        // load the file entity and set it as the default value.
        if (!empty($record['review_image_mid'])) {
          $form['review_image']['#default_value'] = $record['review_image_mid'];
        }
      }
    }

    return $form;
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This function can be left empty as we are handling submission via AJAX.
  }

  /**
   * AJAX form submission handler.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $this->validateForm($form, $form_state);
    if (count($form_state->getErrors()) > 0) {
      foreach ($form_state->getErrors() as $error) {
        $response->addCommand(new MessageCommand($error, NULL, ['type' => 'error']));
      }
      return $response;
    }

    // Fetch the record ID from the route parameters.
    $route_match = \Drupal::routeMatch();
    $route_parameters = $route_match->getParameters();
    $id = $route_parameters->get('id');

    $connection = Database::getConnection();

    // Fetch the current entry.
    $query = $connection->select('guestbook_entries', 'g')
      ->fields('g')
      ->condition('id', $id)
      ->execute();
    $current_entry = $query->fetchObject();

    // Prepare new data.
    $new_data = [
      'name' => $form_state->getValue('name'),
      'email' => $form_state->getValue('email'),
      'phone' => $form_state->getValue('phone'),
      'message' => $form_state->getValue('message'),
      'review' => $form_state->getValue('review'),
    ];

    // Handle avatar.
    $avatar_media_id = $form_state->getValue('avatar');
    if (!empty($avatar_media_id)) {
      $avatar_media = Media::load($avatar_media_id);
      if ($avatar_media) {
        $new_data['avatar_mid'] = $avatar_media->id();
        $new_data['avatar_fid'] = $this->getFileIdFromMedia($avatar_media);
      }
    }
    else {
      $new_data['avatar_mid'] = NULL;
      $new_data['avatar_fid'] = NULL;
    }

    // Handle review image.
    $review_image_media_id = $form_state->getValue('review_image');
    if (!empty($review_image_media_id)) {
      $review_image_media = Media::load($review_image_media_id);
      if ($review_image_media) {
        $new_data['review_image_mid'] = $review_image_media->id();
        $new_data['review_image_fid'] = $this->getFileIdFromMedia($review_image_media);
      }
    }
    else {
      $new_data['review_image_mid'] = NULL;
      $new_data['review_image_fid'] = NULL;
    }

    // Delete old media and files if they're no longer used.
    if (!empty($current_entry->avatar_mid) && $current_entry->avatar_mid != $new_data['avatar_mid']) {
      $this->deleteMediaAndFile($current_entry->avatar_mid, $current_entry->avatar_fid, 'avatar_fid', 'avatar_mid', $connection);
    }
    if (!empty($current_entry->review_image_mid) && $current_entry->review_image_mid != $new_data['review_image_mid']) {
      $this->deleteMediaAndFile($current_entry->review_image_mid, $current_entry->review_image_fid, 'review_image_fid', 'review_image_mid', $connection);
    }

    // Update the entry.
    $connection->update('guestbook_entries')
      ->fields($new_data)
      ->condition('id', $id)
      ->execute();

    // Add a success message.
    $response->addCommand(new MessageCommand($this->t('The guestbook entry has been updated.'), NULL, ['type' => 'status']));

    // Redirect to the guestbook page.
    $url = Url::fromRoute('matthew_guestbook.page');
    $response->addCommand(new RedirectCommand($url->toString()));

    return $response;
  }

}
