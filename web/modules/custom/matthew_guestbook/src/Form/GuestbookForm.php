<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a custom Guestbook form.
 */
class GuestbookForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'guestbook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add form fields.
    $form['#id'] = $this->getFormId();

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('Enter your full name. Must be at least 2 characters long.'),
      '#required' => TRUE,
      '#maxlength' => 100,
      '#attributes' => [
        'pattern' => '.{2,}',
      ],
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateNameAjax',
      ],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#description' => $this->t('Enter a valid email address.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateEmailAjax',
      ],
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone Number'),
      '#description' => $this->t('Enter your phone number. Only digits are allowed and it should not exceed 15 characters.'),
      '#required' => TRUE,
      '#attributes' => [
        'pattern' => '/(7|8|9)\d{9}/',
        'maxlength' => 12,
      ],
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validatePhoneAjax',
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Enter your message or feedback.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateMessageAjax',
      ],
    ];

    $form['review'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review'),
      '#description' => $this->t('Enter your review.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateReviewAjax',
      ],
    ];

    $form['avatar'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Avatar'),
      '#description' => $this->t('Upload your avatar. Allowed formats: jpeg, jpg, png. Max file size: 2MB.'),
      '#allowed_bundles' => ['image'],
      '#required' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpeg jpg png'],
        'file_validate_size' => [2 * 1024 * 1024],
      ],
      '#ajax' => [
        'callback' => '::validateAvatarAjax',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Validating...'),
        ],
      ],
    ];

    $form['review_image'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Review Image'),
      '#description' => $this->t('Upload an image for your review. Allowed formats: jpeg, jpg, png. Max file size: 5MB.'),
      '#allowed_bundles' => ['image'],
      '#required' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpeg jpg png'],
        'file_validate_size' => [5 * 1024 * 1024],
      ],
      '#ajax' => [
        'callback' => '::validateReviewImageAjax',
        'event' => 'change',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Validating...'),
        ],
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
      ],
    ];

    $form['#attached'] = [
      'library' => [
        'matthew_guestbook/media_library_styles',
      ],
    ];

    return $form;
  }

  /**
   * Validates the input and adds AJAX commands to the response.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response.
   * @param string $message
   *   The validation message.
   * @param string $selector
   *   The CSS selector.
   * @param bool $is_valid
   *   The validation status.
   */
  protected function addValidationResponse(
    AjaxResponse $response,
    string $message,
    string $selector,
    bool $is_valid,
  ): void {
    $response->addCommand(new MessageCommand($this->t('@message', ['@message' => $message]), NULL, ['type' => $is_valid ? 'status' : 'error']));
    $response->addCommand(new CssCommand($selector, ['border' => $is_valid ? '1px solid green' : '1px solid red']));
  }

  /**
   * AJAX callback to validate the name.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validateNameAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $name = $form_state->getValue('name');
    if (mb_strlen($name, 'UTF-8') < 2) {
      $this->addValidationResponse($response, $this->t('The name must be at least 2 characters long.'), '[name="name"]', FALSE);
    }
    else {
      $this->addValidationResponse($response, $this->t('The name is valid.'), '[name="name"]', TRUE);
    }
    return $response;
  }

  /**
   * AJAX callback to validate the email.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validateEmailAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $email = $form_state->getValue('email');
    $email_pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

    if (empty($email)) {
      $this->addValidationResponse($response, $this->t('The email is required.'), '[name="email"]', FALSE);
    }
    elseif (!preg_match($email_pattern, $email)) {
      $this->addValidationResponse($response, $this->t('The email is not valid.'), '[name="email"]', FALSE);
    }
    else {
      $this->addValidationResponse($response, $this->t('The email is valid.'), '[name="email"]', TRUE);
    }

    return $response;
  }

  /**
   * AJAX callback to validate the phone number.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validatePhoneAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $phone = $form_state->getValue('phone');

    // Regex pattern for validating phone number.
    $pattern = '/(7|8|9)\d{9}/';

    if (!preg_match($pattern, $phone)) {
      $this->addValidationResponse($response, $this->t('The phone number must contain only digits and not exceed 12 characters.'), '[name="phone"]', FALSE);
    }
    else {
      $this->addValidationResponse($response, $this->t('The phone number is valid.'), '[name="phone"]', TRUE);
    }

    return $response;
  }

  /**
   * AJAX callback to validate the message.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validateMessageAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $message = $form_state->getValue('message');
    if (empty($message)) {
      $this->addValidationResponse($response, $this->t('The message cannot be empty.'), '[name="message"]', FALSE);
    }
    else {
      $this->addValidationResponse($response, $this->t('The message is valid.'), '[name="message"]', TRUE);
    }
    return $response;
  }

  /**
   * AJAX callback to validate the review.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validateReviewAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $message = $form_state->getValue('review');
    if (empty($message)) {
      $this->addValidationResponse($response, $this->t('The message cannot be empty.'), '[name="review"]', FALSE);
    }
    else {
      $this->addValidationResponse($response, $this->t('The message is valid.'), '[name="review"]', TRUE);
    }
    return $response;
  }

  /**
   * AJAX callback to validate the avatar.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validateAvatarAjax(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $avatar = $form_state->getValue('avatar');

    if (!empty($avatar)) {
      $file = $this->entityTypeManager->getStorage('file')->load($avatar[0]);
      if ($file) {
        $errors = file_validate($file, $form['avatar']['#upload_validators']);
        if (!empty($errors)) {
          $error_message = reset($errors);
          $this->addValidationResponse($ajax_response, $error_message, '[data-drupal-selector="edit-avatar-wrapper"]', FALSE);
        }
        else {
          $this->addValidationResponse($ajax_response, $this->t('Avatar is valid.'), '[data-drupal-selector="edit-avatar-wrapper"]', TRUE);
        }
      }
    }
    else {
      $this->addValidationResponse($ajax_response, $this->t('No avatar selected.'), '[data-drupal-selector="edit-avatar-wrapper"]', TRUE);
    }

    return $ajax_response;
  }

  /**
   * AJAX callback to validate the review image.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function validateReviewImageAjax(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $review_image = $form_state->getValue('review_image');

    if (!empty($review_image)) {
      $file = $this->entityTypeManager->getStorage('file')->load($review_image[0]);
      if ($file) {
        $errors = file_validate($file, $form['review_image']['#upload_validators']);
        if (!empty($errors)) {
          $error_message = reset($errors);
          $this->addValidationResponse($ajax_response, $error_message, '[data-drupal-selector="edit-review-image-wrapper"]', FALSE);
        }
        else {
          $this->addValidationResponse($ajax_response, $this->t('Review image is valid.'), '[data-drupal-selector="edit-review-image-wrapper"]', TRUE);
        }
      }
    }
    else {
      $this->addValidationResponse($ajax_response, $this->t('No review image selected.'), '[data-drupal-selector="edit-review-image-wrapper"]', TRUE);
    }

    return $ajax_response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Validate name.
    if (strlen($form_state->getValue('name')) < 2) {
      $this->addValidationResponse($response, $this->t('The name must be at least 2 characters long.'), '#edit-cat-name', FALSE);
    }

    // Validate email.
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $this->addValidationResponse($response, $this->t('Invalid email address.'), '#edit-cat-name', FALSE);
    }

    // Validate phone.
    if (!ctype_digit($form_state->getValue('phone'))) {
      $this->addValidationResponse($response, $this->t('The phone number must contain only digits.'), '#edit-cat-name', FALSE);
    }

    // Validate message.
    if (empty($form_state->getValue('message'))) {
      $this->addValidationResponse($response, $this->t('The message cannot be empty.'), '#edit-cat-name', FALSE);
    }

    // Validate review.
    if (empty($form_state->getValue('review'))) {
      $form_state->setErrorByName('review', $this->t('The review cannot be empty.'));
    }

    $avatar = $form_state->getValue('avatar');
    if (!empty($avatar)) {
      $file = $this->entityTypeManager->getStorage('file')->load($avatar[0]);
      if ($file) {
        $errors = file_validate($file, $form['avatar']['#upload_validators']);
        if (!empty($errors)) {
          $error_message = reset($errors);
          $this->addValidationResponse($response, $error_message, '#edit-avatar', FALSE);
        }
        else {
          $this->addValidationResponse($response, $this->t('Avatar is valid.'), '#edit-avatar', TRUE);
        }
      }
    }
    else {
      $this->addValidationResponse($response, $this->t('No avatar selected.'), '#edit-avatar', TRUE);
    }

    // Validate review image.
    $review_image = $form_state->getValue('review_image');
    if (!empty($review_image)) {
      $file = $this->entityTypeManager->getStorage('file')->load($review_image[0]);
      if ($file) {
        $errors = file_validate($file, $form['review_image']['#upload_validators']);
        if (!empty($errors)) {
          $error_message = reset($errors);
          $this->addValidationResponse($response, $error_message, '#edit-review-image', FALSE);
        }
        else {
          $this->addValidationResponse($response, $this->t('Review image is valid.'), '#edit-review-image', TRUE);
        }
      }
    }
    else {
      $this->addValidationResponse($response, $this->t('No review image selected.'), '#edit-review-image', TRUE);
    }

    return $response;
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
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $this->validateForm($form, $form_state);
    if ($form_state->hasAnyErrors()) {
      foreach ($form_state->getErrors() as $name => $error) {
        $this->addValidationResponse($response, $error, '[name="' . $name . '"]', FALSE);
      }
      return $response;
    }

    $connection = Database::getConnection();
    $fields = [
      'name' => $form_state->getValue('name'),
      'email' => $form_state->getValue('email'),
      'phone' => $form_state->getValue('phone'),
      'message' => $form_state->getValue('message'),
      'review' => $form_state->getValue('review'),
      'created' => time(),
    ];

    // Handle avatar.
    $avatar = $form_state->getValue('avatar');
    $media_entity = $this->entityTypeManager->getStorage('media')->load($avatar);
    $file_id = $media_entity->get('field_media_image')->target_id;
    $file = $this->entityTypeManager->getStorage('file')->load($file_id);
    $fields['avatar_fid'] = $file->id();

    // Handle image.
    $image = $form_state->getValue('review_image');
    $media_entity = $this->entityTypeManager->getStorage('media')->load($image);
    $file_id = $media_entity->get('field_media_image')->target_id;
    $file = $this->entityTypeManager->getStorage('file')->load($file_id);
    $fields['review_image_fid'] = $file->id();

    // Insert into database.
    $connection->insert('guestbook_entries')
      ->fields($fields)
      ->execute();

    // Display success message.
    $response->addCommand(new MessageCommand(
      $this->t('%name, your entry has been saved.', [
        '%name' => $form_state->getValue('name'),
      ]),
      NULL,
      ['type' => 'status']
    ));

    // Reset form state and rebuild the form.
    $form_state->setRebuild();
    $form_state->setValues([]);
    $form_state->setUserInput([]);

    // Rebuild and replace the form.
    $rebuilt_form = $this->formBuilder->rebuildForm($this->getFormId(), $form_state, $form);
    $response->addCommand(new ReplaceCommand('#' . $this->getFormId(), $rebuilt_form));

    return $response;
  }

}
