<?php

namespace Drupal\matthew_guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\matthew_guestbook\Service\GuestbookService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling guestbook entries.
 */
class GuestbookController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The guestbook service.
   *
   * @var \Drupal\matthew_guestbook\Service\GuestbookService
   */
  protected $guestbookService;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\matthew_guestbook\Service\GuestbookService $guestbook_service
   *   The guestbook service to handle database operations.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    GuestbookService $guestbook_service,
    DateFormatterInterface $date_formatter,
  ) {
    $this->guestbookService = $guestbook_service;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): GuestbookController|static {
    return new static(
      $container->get('matthew.guestbook_service'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Displays the guestbook entries form.
   *
   * @return array
   *   A render array for the guestbook entries form.
   */
  public function content(): array {
    $entries = $this->guestbookService->getGuestbookEntries();

    // Process the results to get file URLs and format dates.
    foreach ($entries as $entry) {
      $avatar_id = $entry->avatar_mid;
      $entry->avatar_render_array = $avatar_id
        ? $this->guestbookService->getMediaFileRenderArray($avatar_id, 'field_avatar_image', 'matthew_guestbook_avatar')
        : $this->guestbookService->getDefaultAvatarRenderArray($entry->name);

      $review_image_id = $entry->review_image_mid;
      $entry->review_image_render_array = $this->guestbookService->getMediaFileRenderArray(
        $review_image_id,
        'field_review_image',
        'matthew_guestbook_review'
      );

      $entry->formatted_created_date = $this->dateFormatter->format(
        $entry->created,
        'matthew_guestbook_date_format'
      );
    }

    return [
      '#theme' => 'guestbook-entries',
      '#entries' => $entries,
      '#is_admin' => $this->currentUser()->hasPermission('administer site configuration'),
      '#attached' => [
        'library' => [
          'matthew_guestbook/guestbook_entries',
        ],
      ],
      '#cache' => [
        'tags' => ['view'],
        'contexts' => ['user'],
        'max-age' => 0,
      ],
    ];
  }

}
