<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @return string
   */
  public function getFormId() {
    return 'guestbook_form';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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
        'pattern' => '\d*',
        'maxlength' => 15,
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

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
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
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function validateNameAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $name = $form_state->getValue('name');
    if (mb_strlen($name, 'UTF-8') < 2) {
      $this->addValidationResponse($response, $this->t('The name must be at least 2 characters long.'), '[name="name"]', FALSE);
    } else {
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
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function validatePhoneAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $phone = $form_state->getValue('phone');
    if (!ctype_digit($phone)) {
      $this->addValidationResponse($response, $this->t('The phone number must contain only digits.'), '[name="phone"]', FALSE);
    } else {
      $this->addValidationResponse($response, $this->t('The phone number is valid.'), '[name="phone"]', TRUE);
    }
    return $response;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function validateMessageAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $message = $form_state->getValue('message');
    if (empty($message)) {
      $this->addValidationResponse($response, $this->t('The message cannot be empty.'), '[name="message"]', FALSE);
    } else {
      $this->addValidationResponse($response, $this->t('The message is valid.'), '[name="message"]', TRUE);
    }
    return $response;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function validateForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Validate name
    if (strlen($form_state->getValue('name')) < 2) {
      $this->addValidationResponse($response, $this->t('The name must be at least 2 characters long.'), '#edit-cat-name', FALSE);
    }

    // Validate email
    if (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $this->addValidationResponse($response, $this->t('Invalid email address.'), '#edit-cat-name', FALSE);
    }

    // Validate phone
    if (!ctype_digit($form_state->getValue('phone'))) {
      $this->addValidationResponse($response, $this->t('The phone number must contain only digits.'), '#edit-cat-name', FALSE);
    }

    // Validate message
    if (empty($form_state->getValue('message'))) {
      $this->addValidationResponse($response, $this->t('The message cannot be empty.'), '#edit-cat-name', FALSE);
    }

    return $response;
  }


  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return void
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This function can be left empty as we are handling submission via AJAX.
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   * @throws \Exception
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $this->validateForm($form, $form_state);
    if (count($form_state->getErrors()) > 0) {
      foreach ($form_state->getErrors() as $name => $error) {
        $this->addValidationResponse($response, $error, '[name="' . $name . '"]', FALSE);
      }
      return $response;
    }

    $connection = Database::getConnection();
    $connection->insert('guestbook_entries')
      ->fields([
        'name' => $form_state->getValue('name'),
        'email' => $form_state->getValue('email'),
        'phone' => $form_state->getValue('phone'),
        'message' => $form_state->getValue('message'),
        'created' => time(),
      ])
      ->execute();

    // Display success message.
    $response->addCommand(new MessageCommand(
      $this->t('%name your entry has been saved.', [
        '%name' => $form_state->getValue('name'),
      ]),
      NULL,
      ['type' => 'status']));

    // Reset form state and rebuild the form.
    $form_state->setRebuild();
    $form_state->setValues([]);
    $form_state->setUserInput([]);

    // Use the FormBuilder service to rebuild the form.
    $rebuilt_form = $this->formBuilder->rebuildForm($this->getFormId(), $form_state, $form);

    // Replace the old form with the new one.
    $response->addCommand(new ReplaceCommand('#' . $this->getFormId(), $rebuilt_form));

    return $response;
  }
}
