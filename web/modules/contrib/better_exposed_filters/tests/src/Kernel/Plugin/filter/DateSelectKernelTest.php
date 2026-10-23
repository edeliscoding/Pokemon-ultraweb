<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Kernel\Plugin\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\DateSelect;
use Drupal\Tests\better_exposed_filters\Kernel\BetterExposedFiltersKernelTestBase;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the DateSelect filter widget.
 *
 * @see \Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\DateSelect
 */
#[Group('better_exposed_filters')]
#[RunTestsInSeparateProcesses]
class DateSelectKernelTest extends BetterExposedFiltersKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['bef_test'];

  /**
   * Returns a base filter config for the node-created date.
   *
   * @param array $overrides
   *   Values to merge into the base config.
   *
   * @return array
   *   Filter config array.
   */
  private function baseFilterConfig(array $overrides = []): array {
    return array_merge([
      'id' => 'created',
      'table' => 'node_field_data',
      'field' => 'created',
      'plugin_id' => 'date',
      'exposed' => TRUE,
      'expose' => ['identifier' => 'created'],
    ], $overrides);
  }

  /**
   * Saves a filter and BEF date select plugin to the view.
   *
   * @param array $filter_config
   *   Filter config to inject into the view display.
   * @param array $bef_config
   *   BEF plugin configuration overrides.
   *
   * @return \Drupal\views\ViewExecutable
   *   Uninitialized view ready for getExposedFormRenderArray().
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function viewWithBef(array $filter_config, array $bef_config = []): ViewExecutable {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['created'] = $filter_config;
    $view->storage->save();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'created' => array_merge(['plugin_id' => 'bef_date_select'], $bef_config),
      ],
    ]);

    return Views::getView('bef_test');
  }

  /**
   * Saves a filter to the view and returns a fully initialized view.
   *
   * @param array $filter_config
   *   Filter config to inject into the view display.
   *
   * @return \Drupal\views\ViewExecutable
   *   Initialized view with handlers loaded.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function initializedViewWithFilter(array $filter_config): ViewExecutable {
    $view = Views::getView('bef_test');
    $display = &$view->storage->getDisplay('default');
    $display['display_options']['filters']['created'] = $filter_config;
    $view->storage->save();

    $view = Views::getView('bef_test');
    $view->initDisplay();
    $view->initHandlers();
    return $view;
  }

  /**
   * Tests isApplicable() returns TRUE for a Date filter.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsApplicable(): void {
    $view = $this->initializedViewWithFilter($this->baseFilterConfig());

    $this->assertTrue(DateSelect::isApplicable($view->filter['created']));
  }

  /**
   * Tests that grouped filters are not applicable.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testIsNotApplicableToGroupedFilters(): void {
    $view = $this->initializedViewWithFilter($this->baseFilterConfig());
    $filter = $view->filter['created'];
    $filter->options['is_grouped'] = TRUE;

    $this->assertFalse(DateSelect::isApplicable($filter));
  }

  /**
   * Tests that non-date filters are not applicable.
   */
  public function testIsNotApplicableToNonDateFilters(): void {
    $mock = $this->createMock(FilterPluginBase::class);
    $mock->expects($this->any())->method('isAGroup')->willReturn(FALSE);

    $this->assertFalse(DateSelect::isApplicable($mock));
  }

  /**
   * Tests a single date element is replaced with day/month/year select.
   *
   * The plugin adds a sibling element keyed 'created_bef_select' and hides
   * the original 'created' element. Selects live under a 'selects' sub-key.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSingleDateRendersSelects(): void {
    $view = $this->viewWithBef($this->baseFilterConfig(['operator' => '>']));

    $output = $this->getExposedFormRenderArray($view);

    $this->assertArrayHasKey('created_bef_select', $output);
    $element = $output['created_bef_select'];
    $selects = $element['selects'];

    $this->assertEquals('container', $element['#type']);
    $this->assertArrayHasKey('year', $selects);
    $this->assertArrayHasKey('month', $selects);
    $this->assertArrayHasKey('day', $selects);
    $this->assertEquals('select', $selects['year']['#type']);
    $this->assertEquals('select', $selects['month']['#type']);
    $this->assertEquals('select', $selects['day']['#type']);
    $this->assertContains('bef-date-select', $element['#attributes']['class']);
  }

  /**
   * Tests between operator renders select siblings for min and max.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBetweenDatesRenderSelects(): void {
    $view = $this->viewWithBef($this->baseFilterConfig(['operator' => 'between']));

    $output = $this->getExposedFormRenderArray($view);

    $this->assertArrayHasKey('created_bef_min', $output);
    $this->assertArrayHasKey('created_bef_max', $output);

    foreach (['created_bef_min', 'created_bef_max'] as $key) {
      $this->assertEquals('container', $output[$key]['#type']);
      $selects = $output[$key]['selects'];
      $this->assertArrayHasKey('year', $selects);
      $this->assertArrayHasKey('month', $selects);
      $this->assertArrayHasKey('day', $selects);
      $this->assertContains('bef-date-select', $output[$key]['#attributes']['class']);
    }
  }

  /**
   * Tests exposed operator with a single-value operator renders select sibling.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedOperatorSingleValueRendersSelects(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => '>',
      'expose' => [
        'identifier' => 'created',
        'use_operator' => TRUE,
        'operator_id' => 'created_op',
      ],
    ]));

    $output = $this->getExposedFormRenderArray($view);

    $this->assertArrayHasKey('created_bef_value', $output);
    $selects = $output['created_bef_value']['selects'];

    $this->assertEquals('container', $output['created_bef_value']['#type']);
    $this->assertArrayHasKey('year', $selects);
    $this->assertArrayHasKey('month', $selects);
    $this->assertArrayHasKey('day', $selects);
  }

  /**
   * Tests exposed between operator renders select siblings for value, min, max.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedOperatorBetweenRendersSelects(): void {
    $view = $this->viewWithBef($this->baseFilterConfig([
      'operator' => 'between',
      'expose' => [
        'identifier' => 'created',
        'use_operator' => TRUE,
        'operator_id' => 'created_op',
      ],
    ]));

    $output = $this->getExposedFormRenderArray($view);

    foreach (['created_bef_value', 'created_bef_min', 'created_bef_max'] as $key) {
      $this->assertArrayHasKey($key, $output, "Expected sibling key '$key' in form output.");
      $this->assertEquals('container', $output[$key]['#type'], "Element '$key' should be a container.");
      $selects = $output[$key]['selects'];
      $this->assertArrayHasKey('year', $selects);
      $this->assertArrayHasKey('month', $selects);
    }
  }

  /**
   * Tests an absolute year range produces the correct number of year options.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAbsoluteYearRange(): void {
    $view = $this->viewWithBef(
      $this->baseFilterConfig(['operator' => '>']),
      ['year_start' => '2020', 'year_end' => '2025'],
    );

    $output = $this->getExposedFormRenderArray($view);
    $selects = $output['created_bef_select']['selects'];

    // 2020–2025 inclusive = 6 years + the empty option.
    $this->assertCount(7, $selects['year']['#options']);
    $this->assertArrayHasKey(2020, $selects['year']['#options']);
    $this->assertArrayHasKey(2025, $selects['year']['#options']);
  }

  /**
   * Tests a relative year range resolves correctly from the current year.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRelativeYearRange(): void {
    $view = $this->viewWithBef(
      $this->baseFilterConfig(['operator' => '>']),
      ['year_start' => '-2', 'year_end' => '+2'],
    );

    $output = $this->getExposedFormRenderArray($view);
    $selects = $output['created_bef_select']['selects'];

    $current_year = (int) date('Y');

    // -2 to +2 = 5 years + the empty option.
    $this->assertCount(6, $selects['year']['#options']);
    $this->assertArrayHasKey($current_year - 2, $selects['year']['#options']);
    $this->assertArrayHasKey($current_year + 2, $selects['year']['#options']);
  }

  /**
   * Tests that hide_day removes the day select and sets correct position flags.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testHideDayRemovesDaySelect(): void {
    $view = $this->viewWithBef(
      $this->baseFilterConfig(['operator' => '>']),
      ['hide_day' => TRUE],
    );

    $output = $this->getExposedFormRenderArray($view);
    $element = $output['created_bef_select'];
    $selects = $element['selects'];

    $this->assertArrayHasKey('year', $selects);
    $this->assertArrayHasKey('month', $selects);
    $this->assertArrayNotHasKey('day', $selects);
    $this->assertTrue($element['#bef_date_select_hide_day']);
  }

  /**
   * Tests hide_day on between operator sets correct positions for min and max.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testHideDayBetweenPositions(): void {
    $view = $this->viewWithBef(
      $this->baseFilterConfig(['operator' => 'between']),
      ['hide_day' => TRUE],
    );

    $output = $this->getExposedFormRenderArray($view);

    $this->assertArrayHasKey('created_bef_min', $output);
    $this->assertArrayHasKey('created_bef_max', $output);

    $this->assertEquals('min', $output['created_bef_min']['#bef_date_select_position']);
    $this->assertEquals('max', $output['created_bef_max']['#bef_date_select_position']);
    $this->assertArrayNotHasKey('day', $output['created_bef_min']['selects']);
    $this->assertArrayNotHasKey('day', $output['created_bef_max']['selects']);
  }

}
