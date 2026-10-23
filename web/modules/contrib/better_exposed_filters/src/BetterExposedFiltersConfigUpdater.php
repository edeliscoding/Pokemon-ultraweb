<?php

namespace Drupal\better_exposed_filters;

use Drupal\views\ViewEntityInterface;

/**
 * Provides a BC layer for modules providing old configurations.
 *
 * @internal
 */
class BetterExposedFiltersConfigUpdater {

  /**
   * Add soft_limit_include_children param to views.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function updateSoftLimitIncludeChildrenParam(ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (!isset($display['display_options']['exposed_form']['type'])) {
        continue;
      }
      if ($display['display_options']['exposed_form']['type'] !== 'bef') {
        continue;
      }

      $bef_settings = $display['display_options']['exposed_form']['options']['bef'];
      if (!isset($bef_settings['filter'])) {
        continue;
      }
      foreach ($bef_settings['filter'] as $filter_id => $settings) {
        if (!in_array($settings['plugin_id'], ['bef_links', 'bef'], TRUE)) {
          continue;
        }
        if (isset($settings['soft_limit_include_children'])) {
          continue;
        }
        $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['soft_limit_include_children'] = FALSE;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Add the "no_reset" default on every per-filter and exposed-sort.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function updateAddNoResetKeys(ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (($display['display_options']['exposed_form']['type'] ?? NULL) !== 'bef') {
        continue;
      }

      $bef_settings = &$display['display_options']['exposed_form']['options']['bef'];

      // Per-filter defaults.
      foreach ($bef_settings['filter'] ?? [] as $filter_id => $settings) {
        if (!isset($settings['advanced']) || isset($settings['advanced']['no_reset'])) {
          continue;
        }
        $bef_settings['filter'][$filter_id]['advanced']['no_reset'] = FALSE;
        $changed = TRUE;
      }

      // Exposed sort default.
      if (isset($bef_settings['sort']['advanced']) && !isset($bef_settings['sort']['advanced']['no_reset'])) {
        $bef_settings['sort']['advanced']['no_reset'] = FALSE;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Backfills defaults for the new "preserve URL query params" general keys.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function updateAddPreserveUrlParamsKeys(ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (($display['display_options']['exposed_form']['type'] ?? NULL) !== 'bef') {
        continue;
      }

      $general = &$display['display_options']['exposed_form']['options']['bef']['general'];
      if (!is_array($general)) {
        continue;
      }
      if (!array_key_exists('preserve_url_params', $general)) {
        $general['preserve_url_params'] = FALSE;
        $changed = TRUE;
      }
      if (!array_key_exists('preserve_url_params_list', $general)) {
        $general['preserve_url_params_list'] = '';
        $changed = TRUE;
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

}
