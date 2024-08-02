<?php

namespace Drupal\matthew_guestbook\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to display guestbook entries.
 */
class GuestbookEntriesForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GuestbookEntriesForm object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $connection, FileUrlGeneratorInterface $file_url_generator, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->fileUrlGenerator = $file_url_generator;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_url_generator'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'guestbook_entries_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $query = $this->connection->select('guestbook_entries', 'g')
      ->fields('g', [
        'id',
        'name',
        'email',
        'phone',
        'message',
        'review',
        'avatar_fid',
        'review_image_fid',
        'created',
      ])
      ->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();

    // Process the results to get file URLs and format dates.
    foreach ($results as &$result) {
      if ($result->avatar_fid) {
        $file = $this->entityTypeManager->getStorage('file')->load($result->avatar_fid);
        if ($file) {
          $result->avatar_url = $this->fileUrlGenerator->generate($file->getFileUri());
        }
      }
      if ($result->review_image_fid) {
        $file = $this->entityTypeManager->getStorage('file')->load($result->review_image_fid);
        if ($file) {
          $result->review_image_url = $this->fileUrlGenerator->generate($file->getFileUri());
        }
      }
      $result->created_formatted = $this->dateFormatter->format($result->created, 'custom', 'd/m/Y H:i');
    }

    $form['entries'] = [
      '#theme' => 'guestbook-entries',
      '#entries' => $results,
      '#is_admin' => $this->currentUser()->hasPermission('administer site configuration'),
    ];

    $form['#attached'] = [
      'library' => [
        'matthew_guestbook/styles',
      ],
    ];

    $form['#cache'] = [
      'tags' => ['view'],
      'contexts' => ['user'],
      'max-age' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form does not have a submit action.
  }

}
