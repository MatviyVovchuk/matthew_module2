<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\matthew_guestbook\Service\GuestbookService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for confirming the deletion of a guestbook entry.
 */
class DeleteGuestbookForm extends ConfirmFormBase {

  /**
   * The ID of the guestbook entry to delete.
   *
   * @var int
   */
  protected $id;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The guestbook service.
   *
   * @var \Drupal\matthew_guestbook\Service\GuestbookService
   */
  protected $guestbookService;

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
   * Constructs a new object.
   *
   * @param \Drupal\matthew_guestbook\Service\GuestbookService $guestbook_service
   *   The guestbook service to handle database operations.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(GuestbookService $guestbook_service, LoggerInterface $logger) {
    $this->guestbookService = $guestbook_service;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('matthew.guestbook_service'),
      $container->get('logger.channel.default'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->guestbookService->deleteGuestbookEntry($this->id);

      $this->messenger()->addMessage($this->t('The guestbook entry has been deleted.'));

      // Redirect to the guestbook list.
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to delete record. Error: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Failed to delete the record. Please try again later.'));
    }
  }

}
