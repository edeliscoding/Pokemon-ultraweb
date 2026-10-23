<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the date select widget for BEF.
 */
#[Group('better_exposed_filters')]
#[RunTestsInSeparateProcesses]
class BetterExposedFiltersDateSelectTest extends BetterExposedFiltersTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $now = new \DateTime('now');
    $this->createNode([
      'title' => 'Node 1',
      'created' => $now->getTimestamp(),
      'type' => 'bef_test',
    ]);

    $oneWeekAgo = new \DateTime('-1 week');
    $this->createNode([
      'title' => 'Node 2',
      'created' => $oneWeekAgo->getTimestamp(),
      'type' => 'bef_test',
    ]);

    $oneMonthAgo = new \DateTime('-1 month');
    $this->createNode([
      'title' => 'Node 3',
      'created' => $oneMonthAgo->getTimestamp(),
      'type' => 'bef_test',
    ]);
  }

  /**
   * Switches page_3 to use bef_date_select with the between operator.
   *
   * @param bool $hide_day
   *   Whether to enable the hide_day option.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function switchToDateSelect(bool $hide_day = FALSE): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('page_3')['display_options']['filters']['created']['operator'] = 'between';
    $view->storage->getDisplay('page_3')['display_options']['filters']['created']['value'] = [
      'min' => '',
      'max' => '',
      'value' => '',
      'type' => 'date',
    ];
    $view->storage->save();

    // Re-fetch after save so BEF options merge against the current state.
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'created' => [
          'plugin_id' => 'bef_date_select',
          'year_start' => '-10',
          'year_end' => '+10',
          'hide_day' => $hide_day,
        ],
      ],
    ], 'page_3');
  }

  /**
   * Tests that day/month/year selects render and filter results correctly.
   *
   * The select group is rendered as a sibling of the original date element,
   * so field names use the '_bef_min' / '_bef_max' prefix pattern.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBetweenDateSelect(): void {
    $this->switchToDateSelect();

    $this->drupalGet('/bef-test-date-picker');
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // All nodes should be present before filtering.
    $session->pageTextContains('Node 1');
    $session->pageTextContains('Node 2');
    $session->pageTextContains('Node 3');

    // Select a min date of 2 days ago and a max date of tomorrow.
    $twoDaysAgo = new \DateTime('-2 days');
    $tomorrow = new \DateTime('+1 day');

    $page->selectFieldOption('created_bef_min[year]', $twoDaysAgo->format('Y'));
    $page->selectFieldOption('created_bef_min[month]', $twoDaysAgo->format('n'));
    $page->selectFieldOption('created_bef_min[day]', $twoDaysAgo->format('j'));
    $page->selectFieldOption('created_bef_max[year]', $tomorrow->format('Y'));
    $page->selectFieldOption('created_bef_max[month]', $tomorrow->format('n'));
    $page->selectFieldOption('created_bef_max[day]', $tomorrow->format('j'));

    $page->pressButton('Apply');
    $this->assertSession()->waitForText('Node 1');

    // Only Node 1 (created now) should be visible.
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->pageTextNotContains('Node 2');
    $this->assertSession()->pageTextNotContains('Node 3');
  }

  /**
   * Tests that hide_day filters by full month range.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBetweenDateSelectHideDay(): void {
    $this->switchToDateSelect(hide_day: TRUE);

    $this->drupalGet('/bef-test-date-picker');
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Verify day select is not present.
    $session->elementNotExists('css', 'select[name="created_bef_min[day]"]');
    $session->elementNotExists('css', 'select[name="created_bef_max[day]"]');

    // Select this month as the only range — both min and max default to day 1,
    // so nodes created on the 1st of this month will match.
    $now = new \DateTime('now');
    $page->selectFieldOption('created_bef_min[year]', $now->format('Y'));
    $page->selectFieldOption('created_bef_min[month]', $now->format('n'));
    $page->selectFieldOption('created_bef_max[year]', $now->format('Y'));
    $page->selectFieldOption('created_bef_max[month]', $now->format('n'));

    $page->pressButton('Apply');
    $this->assertSession()->waitForText('Node 1');

    // Node 1 was created today so it should appear; the others were created
    // last week / last month.
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->pageTextNotContains('Node 3');
  }

}
