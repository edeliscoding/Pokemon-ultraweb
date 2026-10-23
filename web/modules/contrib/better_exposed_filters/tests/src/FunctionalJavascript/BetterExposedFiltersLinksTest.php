<?php

namespace Drupal\Tests\better_exposed_filters\FunctionalJavascript;

use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the links filter widget.
 */
#[Group('better_exposed_filters')]
#[RunTestsInSeparateProcesses]
class BetterExposedFiltersLinksTest extends BetterExposedFiltersTestBase {

  /**
   * Tests the "All" link is present and returns all results.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedLinksAllOption(): void {
    $view = Views::getView('bef_test');

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    $session = $this->assertSession();

    $this->drupalGet('/bef-test');

    // Check the "All" button is present and selected by default.
    $all_link = $session->elementExists('css', 'button.bef-link--selected');
    $this->assertEquals('- Any -', $all_link->getText());

    $page = $this->getSession()->getPage();
    $page->pressButton('One');
    $session->pageTextContains('Page one');
    $session->pageTextNotContains('Page two');
    $session->pageTextNotContains('Page with 0 value');

    // Click the "All" button to return to all results.
    $page->pressButton('- Any -');
    $session->pageTextContains('Page one');
    $session->pageTextContains('Page two');
    $session->pageTextContains('Page with 0 value');
  }

  /**
   * Tests the "All" link is not present when multiple is enabled.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testExposedLinksNoAllOptionWhenMultiple(): void {
    $view = Views::getView('bef_test');
    $view->storage->getDisplay('default')['display_options']['filters']['field_bef_integer_value']['expose']['multiple'] = TRUE;
    $view->storage->save();

    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_integer_value' => [
          'plugin_id' => 'bef_links',
        ],
      ],
    ]);

    $session = $this->assertSession();

    $this->drupalGet('/bef-test');

    $session->elementNotExists('css', '.bef-links button[data-bef-value="All"]');

    // Check the option buttons are still rendered.
    $session->buttonExists('Zero');
    $session->buttonExists('One');
  }

}
