<?php

namespace Drupal\matthew_guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling guestbook entries.
 */
class GuestbookController extends ControllerBase {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GuestbookController|static {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Displays the guestbook entries form.
   *
   * @return array
   *   A render array for the guestbook entries form.
   */
  public function content() {
    return $this->formBuilder->getForm('Drupal\matthew_guestbook\Form\GuestbookEntriesForm');
  }

  /**
   * Provides an edit form for a guestbook entry.
   *
   * @param int $id
   *   The ID of the guestbook entry to edit.
   *
   * @return array
   *   A render array for the edit form.
   */
  public function edit($id) {
    return [
      '#markup' => $this->t('Edit functionality for entry ID: @id', ['@id' => $id]),
    ];
  }

  /**
   * Provides a delete form for a guestbook entry.
   *
   * @param int $id
   *   The ID of the guestbook entry to delete.
   *
   * @return array
   *   A render array for the delete form.
   */
  public function delete($id) {
    return [
      '#markup' => $this->t('Delete functionality for entry ID: @id', ['@id' => $id]),
    ];
  }

}
