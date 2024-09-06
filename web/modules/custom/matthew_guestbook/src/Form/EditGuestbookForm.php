<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a custom Guestbook edit form.
 */
class EditGuestbookForm extends AddGuestbookForm {

  /**
   * The ID of the cat record.
   *
   * @var int
   */
  protected int $id;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'guestbook_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): EditGuestbookForm|AddGuestbookForm|static {
    return new static(
      $container->get('matthew.guestbook_service'),
      $container->get('logger.channel.default'),
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    // Set the ID of the cat record to be edited.
    $this->id = $id;

    // Add form fields.
    $form = parent::buildForm($form, $form_state);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    // Check if $id is available.
    if (!empty($this->id)) {
      // Load the record from the database.
      $record = $this->guestbookService->getGuestbookEntries(['id' => $this->id], [], TRUE);

      // Set default values for form elements.
      if ($record) {
        $form['name']['#default_value'] = $record->name;
        $form['email']['#default_value'] = $record->email;
        $form['phone']['#default_value'] = $record->phone;
        $form['message']['#default_value'] = $record->message;
        $form['review']['#default_value'] = $record->review;

        // If there is a avatar_mid,
        // load the file entity and set it as the default value.
        if (!empty($record->avatar_mid)) {
          $form['avatar']['#default_value'] = $record->avatar_mid;
        }

        // If there is a review_image_mid,
        // load the file entity and set it as the default value.
        if (!empty($record->review_image_mid)) {
          $form['review_image']['#default_value'] = $record->review_image_mid;
        }
      }
    }

    return $form;
  }

  /**
   * AJAX form submission handler.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Prepare new data.
    $avatar_media_id = $form_state->getValue('avatar');
    $review_image_media_id = $form_state->getValue('review_image');

    $fields = [
      'name' => $form_state->getValue('name'),
      'email' => $form_state->getValue('email'),
      'phone' => $form_state->getValue('phone'),
      'message' => $form_state->getValue('message'),
      'review' => $form_state->getValue('review'),
      'avatar_mid' => !empty($avatar_media_id) ? $avatar_media_id : NULL,
      'review_image_mid' => !empty($review_image_media_id) ? $review_image_media_id : NULL,
    ];

    try {
      // Update cat record.
      $this->guestbookService->updateGuestbookEntry($this->id, $fields);

      // Display a status message and redirect to the cats list.
      $this->messenger()->addStatus($this->t('The guestbook entry has been updated.'));

      // Redirect to the guestbook page.
      $form_state->setRedirect('matthew_guestbook.page')->disableRedirect(FALSE)->setRebuild(FALSE);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update guestbook entry with ID @id. Error: @message', [
        '@id' => $this->id,
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Failed to update the guestbook entry. Please try again later.'));
    }

  }

}
