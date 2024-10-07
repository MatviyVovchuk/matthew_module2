<?php

namespace Drupal\matthew_guestbook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

class GuestbookController extends ControllerBase {

  public function content() {
    $query = Database::getConnection()->select('guestbook_entries', 'g')
      ->fields('g', ['id', 'name', 'email', 'phone', 'message', 'created'])
      ->orderBy('created', 'DESC');
    $results = $query->execute()->fetchAll();

    return [
      '#theme' => 'guestbook-entries',
      '#entries' => $results,
    ];
  }

}
