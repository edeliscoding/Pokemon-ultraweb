<?php

declare(strict_types=1);

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

/**
 * Base class for date-based filter widgets.
 */
abstract class DateFilterWidgetBase extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    return (is_a($filter, 'Drupal\views\Plugin\views\filter\Date') || !empty($filter->date_handler))
      && !$filter->isAGroup();
  }

  /**
   * Returns a reference to the date filter element, handling wrapper nesting.
   *
   * Views wraps between/range filters in a container keyed
   * "{$field_id}_wrapper". This helper centralizes the two-level lookup so
   * subclasses do not need to repeat it.
   *
   * @param array $form
   *   The exposed form array (by reference).
   * @param string $field_id
   *   The exposed-filter identifier.
   *
   * @return array
   *   Reference to the resolved element.
   */
  protected function &resolveDateElement(array &$form, string $field_id): array {
    $wrapper_id = $field_id . '_wrapper';
    if (!isset($form[$field_id]) && isset($form[$wrapper_id])) {
      // Parent::exposedFormAlter() may double-wrap the element.
      if (isset($form[$wrapper_id][$wrapper_id][$field_id])) {
        return $form[$wrapper_id][$wrapper_id][$field_id];
      }
      return $form[$wrapper_id][$field_id];
    }
    return $form[$field_id];
  }

  /**
   * Detects the structural variant of a date filter element.
   *
   * @param array $element
   *   The resolved date filter element.
   *
   * @return array
   *   Associative array with boolean values:
   *   - is_single: TRUE when the element itself is a single input.
   *   - is_double: TRUE when min/max sub-elements are present (between).
   *   - has_value_sub_element: TRUE when a 'value' sub-element is present
   *     (exposed operator case).
   */
  protected function detectDateElementStructure(array $element): array {
    $has_min_max = isset($element['min']['#type']) && isset($element['max']['#type']);
    $has_value_sub = isset($element['value']['#type']);
    return [
      // A container with min/max children is a between group, not a single
      // input, even when it also has '#type' set on the container itself.
      'is_single' => isset($element['#type']) && !$has_min_max && !$has_value_sub,
      'is_double' => $has_min_max,
      'has_value_sub_element' => $has_value_sub,
    ];
  }

  /**
   * Inserts new form elements immediately after a given key.
   *
   * Preserves array insertion order so the new elements render adjacent to
   * the original field rather than at the end of the form.
   *
   * @param array $form
   *   The form array (by reference).
   * @param string $after_key
   *   The existing key to insert after.
   * @param array $new_elements
   *   Associative array of new key => element pairs to insert.
   */
  protected function insertAfterKey(array &$form, string $after_key, array $new_elements): void {
    $result = [];
    $inserted = FALSE;
    foreach ($form as $key => $value) {
      $result[$key] = $value;
      if ($key === $after_key) {
        foreach ($new_elements as $new_key => $new_value) {
          $result[$new_key] = $new_value;
        }
        $inserted = TRUE;
      }
    }
    if (!$inserted) {
      foreach ($new_elements as $new_key => $new_value) {
        $result[$new_key] = $new_value;
      }
    }
    $form = $result;
  }

}
