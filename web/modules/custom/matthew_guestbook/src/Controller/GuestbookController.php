<?php

namespace Drupal\matthew_guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;

class GuestbookController extends ControllerBase {

  public function content2() {
    $query = Database::getConnection()->select('guestbook_entries', 'g')
      ->fields('g', ['id', 'name', 'email', 'phone', 'message', 'created'])
      ->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();

    return [
      '#theme' => 'guestbook-entries',
      '#entries' => $results,
      '#attached' => [
        'library' => [
          'matthew_guestbook/styles',
        ],
      ],
    ];
  }
  public function content() {
    $query = Database::getConnection()->select('guestbook_entries', 'g')
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

    // Process the results to get file URLs.
    foreach ($results as &$result) {
      if ($result->avatar_fid) {
        $file = File::load($result->avatar_fid);
        if ($file) {
          $result->avatar_url = $file->createFileUrl();
        }
      }
      if ($result->review_image_fid) {
        $file = File::load($result->review_image_fid);
        if ($file) {
          $result->review_image_url = $file->createFileUrl();
        }
      }
    }

    return [
      '#theme' => 'guestbook-entries',
      '#entries' => $results,
      '#attached' => [
        'library' => [
          'matthew_guestbook/styles',
        ],
      ],
    ];
  }

}
