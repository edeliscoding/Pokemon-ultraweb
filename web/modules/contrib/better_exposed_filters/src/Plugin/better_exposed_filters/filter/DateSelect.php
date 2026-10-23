<?php

declare(strict_types=1);

namespace Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Attribute\FiltersWidget;
use Drupal\Core\Form\FormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Date select widget implementation.
 *
 * Renders date filter inputs as day/month/year select dropdowns.
 */
#[FiltersWidget(
  id: 'bef_date_select',
  title: new TranslatableMarkup('Date Select (Experimental)'),
)]
class DateSelect extends DateFilterWidgetBase {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'year_start' => '-10',
      'year_end' => '+10',
      'hide_day' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['year_start'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year range start'),
      '#description' => $this->t('Absolute year (e.g. 1980) or offset from current year (e.g. -10).'),
      '#default_value' => $this->configuration['year_start'],
      '#size' => 10,
    ];

    $form['year_end'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Year range end'),
      '#description' => $this->t('Absolute year (e.g. 2030) or offset from current year (e.g. +10).'),
      '#default_value' => $this->configuration['year_end'],
      '#size' => 10,
    ];

    $form['hide_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide the day dropdown'),
      '#description' => $this->t('When hidden, the day defaults to the 1st of the selected month.'),
      '#default_value' => !empty($this->configuration['hide_day']),
    ];

    $form['partial_input_note'] = [
      '#type' => 'item',
      '#title' => $this->t('Partial input behavior'),
      '#markup' => $this->t('Missing year skips the filter. For between filters, missing month or day widens the min side to the start of the year/month and the max side to the end. For single-value operators, direction is inferred: <code>&gt;</code> and <code>&lt;=</code> widen toward the end of the period (e.g. year 2027 → 2027-12-31), <code>&gt;=</code> and <code>&lt;</code> widen toward the start (e.g. year 2027 → 2027-01-01), and <code>=</code>/<code>!=</code> require a full date.'),
    ];

    return $form;
  }

  /**
   * Resolves a year value to an integer.
   *
   * Supports absolute years (e.g. 1980) and relative offsets (e.g. -10, +5).
   *
   * @param string $value
   *   The configured year value.
   *
   * @return int
   *   The resolved year.
   */
  protected function resolveYear(string $value): int {
    if (preg_match('/^[+-]\d+$/', $value)) {
      return (int) date('Y') + (int) $value;
    }
    return (int) $value;
  }

  /**
   * Builds year options for the year select element.
   *
   * @return array
   *   An options array suitable for a select element.
   */
  protected function buildYearOptions(): array {
    $start = $this->resolveYear($this->configuration['year_start']);
    $end = $this->resolveYear($this->configuration['year_end']);

    [$min, $max] = $start <= $end ? [$start, $end] : [$end, $start];

    $options = ['' => $this->t('Year')];
    for ($year = $min; $year <= $max; $year++) {
      $options[$year] = $year;
    }
    return $options;
  }

  /**
   * Builds month options for the month select element.
   *
   * @return array
   *   An options array suitable for a select element.
   */
  protected static function buildMonthOptions(): array {
    $options = ['' => t('Month')];
    for ($m = 1; $m <= 12; $m++) {
      $options[$m] = \DateTime::createFromFormat('!m', (string) $m)->format('F');
    }
    return $options;
  }

  /**
   * Builds day options for the day select element.
   *
   * @return array
   *   An options array suitable for a select element.
   */
  protected static function buildDayOptions(): array {
    $options = ['' => t('Day')];
    for ($d = 1; $d <= 31; $d++) {
      $options[$d] = $d;
    }
    return $options;
  }

  /**
   * Converts a date string or strtotime offset into day/month/year parts.
   *
   * @param string $value
   *   A Y-m-d date string or a strtotime()-compatible offset (e.g. "-1 day").
   *
   * @return int[]|null[]
   *   Array with keys 'year', 'month', 'day', each an int or NULL if unknown.
   */
  protected function parseDateValue(string $value): array {
    if ($value === '') {
      return ['year' => NULL, 'month' => NULL, 'day' => NULL];
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
      return ['year' => (int) $m[1], 'month' => (int) $m[2], 'day' => (int) $m[3]];
    }
    try {
      $date = new \DateTime($value);
      return [
        'year' => (int) $date->format('Y'),
        'month' => (int) $date->format('m'),
        'day' => (int) $date->format('d'),
      ];
    }
    catch (\Exception $e) {
      $this->getLogger('better_exposed_filters')->log(RfcLogLevel::ERROR, $e->getMessage());
      return ['year' => NULL, 'month' => NULL, 'day' => NULL];
    }
  }

  /**
   * Builds a standalone select-group container for day/month/year.
   *
   * The container is added as a sibling of the original date element rather
   * than as a child, so it does not conflict with any scalar value already
   * stored in form state at the original element's parents path.
   *
   * The container's #element_validate callback assembles the selected values
   * into a Y-m-d string and writes it directly to the target form state path
   * (the original filter element's parents).
   *
   * @param string $position
   *   Context — 'value', 'min', or 'max'. Stored on the element for reference.
   * @param string $default_value
   *   Current default value (Y-m-d string or strtotime-compatible offset).
   * @param array $target_parents
   *   The form state parents path of the original filter element whose value
   *   this group replaces (e.g. ['created'] or ['created', 'min']).
   * @param array $year_options
   *   Pre-built year select options.
   * @param bool $hide_day
   *   Whether to omit the day dropdown.
   * @param string $label
   *   Optional label to display above the select group.
   * @param string $group_key
   *   Optional key to use for the select group.
   * @param string $operator
   *   The filter's operator (e.g. '>', '<=', '=', 'between'); needed for
   *   position='value' groups to infer the fill direction of partial input.
   *
   * @return array
   *   A form render array for the select group.
   */
  protected function buildDateSelectGroup(string $position, string $default_value, array $target_parents, array $year_options, bool $hide_day, string $label = '', string $group_key = '', string $operator = ''): array {
    $parts = $this->parseDateValue($default_value);

    // Enable #tree so that form-state value collection uses the group key as
    // the top-level path. Explicit #parents on each select (see below) further
    // ensure unique name/id attributes even when multiple date filters exist.
    $group = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['bef-date-select']],
      '#bef_date_select_position' => $position,
      '#bef_date_select_hide_day' => $hide_day,
      '#bef_date_select_target_parents' => $target_parents,
      '#bef_date_select_operator' => $operator,
      '#element_validate' => [[static::class, 'validateDateSelect']],
      '#process' => [[static::class, 'processDateSelectStates']],
      '#attached' => ['library' => ['better_exposed_filters/date_select']],
    ];

    if ($label !== '') {
      $group['#attributes']['role'] = 'group';
      $group['#attributes']['aria-label'] = $label;
      $group['filter_label'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label,
        '#attributes' => ['class' => ['bef-date-select__label', 'label', 'form-item__label']],
        '#weight' => -100,
      ];
    }

    // Selects live in a presentational sub-container so the label sits above
    // and the dropdowns appear side-by-side inline. Explicit #parents on each
    // select bypass the 'selects' key, giving HTML names such as
    // created_bef_min[year] instead of created_bef_min[selects][year].
    $selects = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bef-date-select__selects']],
    ];

    if (!$hide_day) {
      $selects['day'] = [
        '#type' => 'select',
        '#title' => $this->t('Day'),
        '#options' => static::buildDayOptions(),
        '#default_value' => $parts['day'],
        '#parents' => array_filter([$group_key, 'day']),
      ];
    }

    $selects['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#options' => static::buildMonthOptions(),
      '#default_value' => $parts['month'],
      '#parents' => array_filter([$group_key, 'month']),
    ];

    $selects['year'] = [
      '#type' => 'select',
      '#title' => $this->t('Year'),
      '#options' => $year_options,
      '#default_value' => $parts['year'],
      '#parents' => array_filter([$group_key, 'year']),
    ];

    $group['selects'] = $selects;

    return $group;
  }

  /**
   * Process callback: applies #states to the container element.
   *
   * @param array $element
   *   The container element.
   *
   * @return array
   *   The processed element.
   */
  public static function processDateSelectStates(array $element): array {
    if (!empty($element['#states'])) {
      FormHelper::processStates($element);
    }
    return $element;
  }

  /**
   * Element validate callback: assembles day/month/year into a Y-m-d string.
   *
   * Writes the result directly to the target form state path so that Views
   * reads the assembled date rather than the hidden original element's value.
   *
   * When the day dropdown is hidden, day defaults to 1 for all positions.
   *
   * @param array $element
   *   The select group being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function validateDateSelect(array &$element, FormStateInterface $form_state): void {
    $selects = $element['selects'];
    $year = $selects['year']['#value'] ?? '';
    $month = $selects['month']['#value'] ?? '';
    $day_visible = isset($selects['day']);
    $day = $day_visible ? ($selects['day']['#value'] ?? '') : '';

    // Without a year anchor a partial input can't resolve to a meaningful
    // boundary, so drop it silently.
    if ($year === '') {
      return;
    }

    $position = $element['#bef_date_select_position'] ?? 'value';

    // For a single-value operator, infer the effective boundary from the
    // operator direction.
    if ($position === 'value') {
      $operator = $element['#bef_date_select_operator'] ?? '';
      $position = match ($operator) {
        '>', '<=' => 'max',
        '>=', '<' => 'min',
        default => 'value',
      };
      if ($position === 'value' && ($month === '' || ($day_visible && $day === ''))) {
        return;
      }
    }

    // Progressive fill for min/max boundaries: missing parts widen to the
    // start (min) or end (max) of the surrounding range.
    if ($month === '') {
      $month = $position === 'max' ? 12 : 1;
    }
    if ($day === '') {
      if ($position === 'max') {
        $last = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $day = $last->format('t');
      }
      else {
        $day = 1;
      }
    }

    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $form_state->setValue($element['#bef_date_select_target_parents'], $date);
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_id = $this->getExposedFilterFieldId();

    // Determine the top-level key so siblings can be inserted at the same
    // position as the original field rather than at the end of the form.
    $wrapper_id = $field_id . '_wrapper';
    $top_key = (!isset($form[$field_id]) && isset($form[$wrapper_id])) ? $wrapper_id : $field_id;

    parent::exposedFormAlter($form, $form_state);

    // Re-establish a reference AFTER parent, which may replace
    // $form[$wrapper_id] with a new container for between/wrapper filters —
    // a reference taken before parent would become orphaned, so mutations
    // like setting #type => hidden would silently not persist.
    $element = &$this->resolveDateElement($form, $field_id);

    [
      'is_single' => $is_single_date,
      'is_double' => $is_double_date,
      'has_value_sub_element' => $has_value_sub_element,
    ] = $this->detectDateElementStructure($element);

    $year_options = $this->buildYearOptions();
    $hide_day = !empty($this->configuration['hide_day']);

    // Capture the exposed filter label from the handler configuration so it
    // remains available regardless of element structure (single vs. between).
    $label = ($this->handler->options['expose']['label'] ?? $element['#title'] ?? '');

    // Operator drives fill direction for partial input on value-position
    // groups (validateDateSelect).
    $operator = ($this->handler->operator ?? '');

    if ($is_single_date) {
      $default = $element['#default_value'] ?? '';
      // Remove the original element rather than hiding it — a hidden input
      // with #states would cascade its visibility to the closest
      // .js-form-wrapper ancestor (the parent fieldset), which core
      // states.js would then hide, taking the operator dropdown with it.
      // #access = FALSE strips it from the render + submit lifecycle
      // entirely, letting validateDateSelect own the form-state value via
      // setValue().
      $element['#access'] = FALSE;
      $group_key = $field_id . '_bef_select';
      $this->insertAfterKey($form, $top_key, [
        $group_key => $this->buildDateSelectGroup('value', $default, [$field_id], $year_options, $hide_day, $label, $group_key, $operator),
      ]);
    }
    elseif ($is_double_date || $has_value_sub_element) {
      $new_elements = [];
      if ($has_value_sub_element) {
        $default = $element['value']['#default_value'] ?? '';
        $group_key = $field_id . '_bef_value';
        $group = $this->buildDateSelectGroup('value', $default, [$field_id, 'value'], $year_options, $hide_day, $label, $group_key, $operator);
        // Mirror #states from the original so exposed-operator toggling
        // shows/hides value vs. min/max the same way it did on the native
        // NumericFilter inputs — avoids duplicate rendering when the
        // operator is exposed with both single-value and between options.
        if (!empty($element['value']['#states'])) {
          $group['#states'] = $element['value']['#states'];
        }
        $new_elements[$group_key] = $group;
        $element['value']['#access'] = FALSE;
      }
      if ($is_double_date) {
        $min_key = $field_id . '_bef_min';
        $max_key = $field_id . '_bef_max';
        $min_group = $this->buildDateSelectGroup('min', $element['min']['#default_value'] ?? '', [
          $field_id,
          'min',
        ], $year_options, $hide_day, $label, $min_key);
        $max_group = $this->buildDateSelectGroup('max', $element['max']['#default_value'] ?? '', [
          $field_id,
          'max',
        ], $year_options, $hide_day, '', $max_key);
        if (!empty($element['min']['#states'])) {
          $min_group['#states'] = $element['min']['#states'];
        }
        if (!empty($element['max']['#states'])) {
          $max_group['#states'] = $element['max']['#states'];
        }
        $new_elements[$min_key] = $min_group;
        $new_elements[$max_key] = $max_group;
        $element['min']['#access'] = FALSE;
        $element['max']['#access'] = FALSE;
      }
      $this->insertAfterKey($form, $top_key, $new_elements);
    }
  }

}
