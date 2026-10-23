<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Functional;

use Drupal\Tests\better_exposed_filters\Traits\BetterExposedFiltersTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test that the reset button works.
 */
#[Group('better_exposed_filters')]
#[RunTestsInSeparateProcesses]
class ResetButtonTest extends BrowserTestBase {
  use BetterExposedFiltersTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bef_test',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Reset button should work on an autocomplete field with an invalid value.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testResetWorksWithAutocomplete(): void {
    // Setup: Prepare invalid data and expected error message used in this test.
    $invalidData = 'Wwww';
    $expectedErrorMessage = \sprintf('There are no taxonomy terms matching "%s".', $invalidData);

    // Setup: Change the field_bef_location exposed filter to autocomplete.
    $view = Views::getView('bef_test');
    $view->setDisplay();
    $filters = $view->displayHandlers->get('default')->getOption('filters');
    $filters['field_bef_location_target_id']['type'] = 'textfield';
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);
    $view->storage->enable()->save();

    // Setup: Enable the BEF reset button.
    $this->setBetterExposedOptions($view, ['reset_button' => TRUE]);

    // Setup: Navigate to the view. Submit an invalid value in the autocomplete.
    $this->drupalGet('/bef-test');
    $this->submitForm([
      'field_bef_location_target_id' => $invalidData,
    ], 'Apply');

    // Assert: There is an error message about the invalid value; and the
    // invalid value is present in the autocomplete field.
    $this->assertSession()->statusMessageContains($expectedErrorMessage, 'error');
    $this->assertSession()->fieldValueEquals('field_bef_location_target_id', $invalidData);

    // SUT: Click the reset button.
    $this->submitForm([], 'Reset');

    // Assert: There is no longer an error message about the invalid value. The
    // invalid value is no longer present in the autocomplete field.
    $this->assertSession()->statusMessageNotContains($expectedErrorMessage, 'error');
    $this->assertSession()->fieldValueEquals('field_bef_location_target_id', '');
  }

  /**
   * A filter flagged "Do not reset" keeps its value on reset.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNoResetPreservesFilter(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'status' => ['advanced' => ['no_reset' => TRUE]],
      ],
    ]);

    // Both filters render as selects. "status" is preserved; submit "0" (No),
    // which differs from its configured default of "1" so a reset would
    // otherwise change it. "field_bef_integer_value" is the control: its
    // "- Any -" option uses the value "All".
    $this->drupalGet('/bef-test');
    $this->submitForm([
      'status' => '0',
      'field_bef_integer_value' => '5',
    ], 'Apply');
    $this->assertSession()->fieldValueEquals('status', '0');
    $this->assertSession()->fieldValueEquals('field_bef_integer_value', '5');

    // After reset the preserved filter keeps its value while the control filter
    // returns to its default.
    $this->submitForm([], 'Reset');
    $this->assertSession()->fieldValueEquals('status', '0');
    $this->assertSession()->fieldValueEquals('field_bef_integer_value', 'All');
  }

  /**
   * An exposed sort flagged "Do not reset" keeps its value on reset.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNoResetPreservesSort(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'sort' => ['advanced' => ['no_reset' => TRUE]],
    ]);

    // Change the sort order away from its default (DESC) and add a filter.
    $this->drupalGet('/bef-test');
    $this->submitForm([
      'sort_order' => 'ASC',
      'field_bef_integer_value' => '5',
    ], 'Apply');
    $this->assertSession()->fieldValueEquals('sort_order', 'ASC');
    $this->assertSession()->fieldValueEquals('field_bef_integer_value', '5');

    // Reset keeps the exposed sort but clears the filter.
    $this->submitForm([], 'Reset');
    $this->assertSession()->fieldValueEquals('sort_order', 'ASC');
    $this->assertSession()->fieldValueEquals('field_bef_integer_value', 'All');
  }

  /**
   * Input in a "Do not reset" field does not show the reset button.
   *
   * Extends core's "hide the reset button when there is no exposed input" rule
   * so that input belonging to a "Do not reset" filter is ignored.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testResetButtonIgnoresNoResetInput(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'status' => ['advanced' => ['no_reset' => TRUE]],
      ],
    ]);

    // Input only in the preserved filter: nothing to reset, button hidden.
    // Assert the form still renders so this is not a false pass.
    $this->drupalGet('/bef-test', ['query' => ['status' => '0']]);
    $this->assertSession()->fieldExists('status');
    $this->assertNull($this->getSession()->getPage()->findButton('Reset'));

    // Input in a resettable filter: button appears as usual.
    $this->drupalGet('/bef-test', ['query' => ['field_bef_integer_value' => '5']]);
    $this->assertNotNull($this->getSession()->getPage()->findButton('Reset'));
  }

}
