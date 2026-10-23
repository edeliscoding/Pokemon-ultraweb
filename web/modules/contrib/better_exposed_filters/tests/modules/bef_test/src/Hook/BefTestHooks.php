<?php

declare(strict_types=1);

namespace Drupal\bef_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the BEF Test module.
 */
class BefTestHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.bef_test') {
      return NULL;
    }

    $output = '<h3>' . $this->t('About') . '</h3>';
    $output .= '<p>' . $this->t('Test module for Better Exposed Filters') . '</p>';
    return $output;
  }

}
