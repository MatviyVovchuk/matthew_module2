<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\matthew_guestbook\Service\GuestbookService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a custom Guestbook form.
 */
class AddGuestbookForm extends FormBase {
  const AVATAR_MAX_SIZE = 2 * 1024 * 1024;
  const REVIEW_IMAGE_MAX_SIZE = 5 * 1024 * 1024;
  const ALLOWED_EXTENSIONS = 'jpeg jpg png';

  /**
   * The guestbook service.
   *
   * @var \Drupal\matthew_guestbook\Service\GuestbookService
   */
  protected $guestbookService;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
   * @param \Drupal\matthew_guestbook\Service\GuestbookService $guestbook_service
   *   The guestbook service to handle database operations.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(GuestbookService $guestbook_service, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->guestbookService = $guestbook_service;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): AddGuestbookForm|static {
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
  public function getFormId(): string {
    return 'guestbook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
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
        'callback' => '::validateNameAjaxCallback',
      ],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#description' => $this->t('Enter a valid email address.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateEmailAjaxCallback',
      ],
    ];

    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone Number'),
      '#description' => $this->t('Enter your phone number. Only digits are allowed and it should not exceed 12 characters.'),
      '#required' => TRUE,
      '#attributes' => [
        'maxlength' => 12,
      ],
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validatePhoneAjaxCallback',
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Enter your message or feedback.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateMessageAjaxCallback',
      ],
    ];

    $form['review'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review'),
      '#description' => $this->t('Enter your review.'),
      '#required' => TRUE,
      '#ajax' => [
        'event' => 'change',
        'callback' => '::validateReviewAjaxCallback',
      ],
    ];

    $form['avatar'] = [
      '#type' => 'media_library',
      '#title' => $this->t('Avatar'),
      '#description' => $this->t('Upload your avatar. Allowed formats: @formats. Max file size: @size.', [
        '@formats' => self::ALLOWED_EXTENSIONS,
        '@size' => format_size(self::AVATAR_MAX_SIZE),
      ]),
      '#allowed_bundles' => ['avatar'],
      '#required' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => [self::ALLOWED_EXTENSIONS],
        'file_validate_size' => [self::AVATAR_MAX_SIZE],
      ],
      '#ajax' => [
        'callback' => '::validateAvatarAjaxCallback',
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
      '#description' => $this->t('Upload an image for your review. Allowed formats: @formats. Max file size: @size.', [
        '@formats' => self::ALLOWED_EXTENSIONS,
        '@size' => format_size(self::REVIEW_IMAGE_MAX_SIZE),
      ]),
      '#allowed_bundles' => ['review_image'],
      '#required' => FALSE,
      '#upload_validators' => [
        'file_validate_extensions' => [self::ALLOWED_EXTENSIONS],
        'file_validate_size' => [self::REVIEW_IMAGE_MAX_SIZE],
      ],
      '#ajax' => [
        'callback' => '::validateReviewImageAjaxCallback',
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
        'callback' => '::submitFormAjaxCallback',
        'event' => 'click',
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
  public function validateNameAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
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
  public function validateEmailAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
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
  public function validatePhoneAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
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
  public function validateMessageAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
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
  public function validateReviewAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
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
   * Validate media file.
   *
   * @param string $media_id
   *   The media entity ID.
   * @param string $field_name
   *   The form field name.
   * @param array $validators
   *   The upload validators.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  protected function validateMediaFile(string $media_id, string $field_name, array $validators): AjaxResponse {
    $ajax_response = new AjaxResponse();
    $media_entity = $this->entityTypeManager->getStorage('media')->load($media_id);

    if ($media_entity) {
      $file_id = $media_entity->get('field_media_image')->target_id;
      if (!empty($file_id)) {
        $file = $this->entityTypeManager->getStorage('file')->load($file_id);
        if ($file) {
          $errors = file_validate($file, $validators);
          if (!empty($errors)) {
            $error_message = reset($errors);
            $this->addValidationResponse($ajax_response, $error_message, "[data-drupal-selector=\"edit-{$field_name}-wrapper\"]", FALSE);
          }
          else {
            $this->addValidationResponse($ajax_response, $this->t('@label is valid.', ['@label' => ucfirst($field_name)]), "[data-drupal-selector=\"edit-{$field_name}-wrapper\"]", TRUE);
          }
        }
      }
    }
    else {
      $this->addValidationResponse($ajax_response, $this->t('No @field_name selected.', ['@field_name' => $field_name]), "[data-drupal-selector=\"edit-{$field_name}-wrapper\"]", TRUE);
    }

    return $ajax_response;
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
  public function validateAvatarAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $avatar = $form_state->getValue('avatar');
    return $this->validateMediaFile($avatar, 'avatar', $form['avatar']['#upload_validators']);
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
  public function validateReviewImageAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $review_image = $form_state->getValue('review_image');
    return $this->validateMediaFile($review_image, 'review_image', $form['review_image']['#upload_validators']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->validateNameAjaxCallback($form, $form_state);
    $this->validateEmailAjaxCallback($form, $form_state);
    $this->validatePhoneAjaxCallback($form, $form_state);
    $this->validateMessageAjaxCallback($form, $form_state);
    $this->validateReviewAjaxCallback($form, $form_state);
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
  public function submitFormAjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    try {
      // Save the data to the database.
      $this->guestbookService->addGuestbookEntry([
        'name' => $form_state->getValue('name'),
        'email' => $form_state->getValue('email'),
        'phone' => $form_state->getValue('phone'),
        'message' => $form_state->getValue('message'),
        'review' => $form_state->getValue('review'),
        'avatar_mid' => $form_state->getValue('avatar'),
        'review_image_mid' => $form_state->getValue('review_image'),
        'created' => time(),
      ]);

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
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to add record. Error: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Failed to add the record. Please try again later.'));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This function can be left empty as we are handling submission via AJAX.
  }

}
