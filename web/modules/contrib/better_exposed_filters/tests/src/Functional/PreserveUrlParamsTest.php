<?php

declare(strict_types=1);

namespace Drupal\Tests\better_exposed_filters\Functional;

use Drupal\Tests\better_exposed_filters\Traits\BetterExposedFiltersTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Preserve URL query parameters" general option.
 */
#[Group('better_exposed_filters')]
#[RunTestsInSeparateProcesses]
class PreserveUrlParamsTest extends BrowserTestBase {
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
   * With preserve_url_params on, non-form URL params survive submit + reset.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreserveUrlParams(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'general' => [
        'preserve_url_params' => TRUE,
      ],
    ]);

    // Visit with extraneous query, submit a filter — utm_source survives.
    $this->drupalGet('/bef-test', ['query' => ['utm_source' => 'twitter']]);
    $this->submitForm(['field_bef_integer_value' => '5'], 'Apply');
    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('utm_source=twitter', $url);
    $this->assertStringContainsString('field_bef_integer_value=5', $url);

    // Reset preserves it too.
    $this->submitForm([], 'Reset');
    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('utm_source=twitter', $url);
    $this->assertStringNotContainsString('field_bef_integer_value=5', $url);
  }

  /**
   * With preserve_url_params off (default), the extra param is dropped.
   */
  public function testPreserveOffDropsQuery(): void {
    $this->drupalGet('/bef-test', ['query' => ['utm_source' => 'twitter']]);
    $this->submitForm(['field_bef_integer_value' => '5'], 'Apply');
    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringNotContainsString('utm_source=twitter', $url);
    $this->assertStringContainsString('field_bef_integer_value=5', $url);
  }

  /**
   * A non-empty allowlist restricts which params are preserved.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPreserveUrlParamsAllowlist(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'general' => [
        'preserve_url_params' => TRUE,
        'preserve_url_params_list' => 'utm_source',
      ],
    ]);
    $this->drupalGet('/bef-test', [
      'query' => [
        'utm_source' => 'twitter',
        'custom_param' => 'foo',
      ],
    ]);
    $this->submitForm(['field_bef_integer_value' => '5'], 'Apply');
    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('utm_source=twitter', $url);
    $this->assertStringNotContainsString('custom_param=foo', $url);
  }

  /**
   * Reserved framework/auth keys are never preserved, even if allowlisted.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testReservedKeysAlwaysStripped(): void {
    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'general' => [
        'preserve_url_params' => TRUE,
        'preserve_url_params_list' => "token\npassword",
      ],
    ]);
    $this->drupalGet('/bef-test', [
      'query' => ['token' => 'secret', 'password' => 'x'],
    ]);
    $this->submitForm(['field_bef_integer_value' => '5'], 'Apply');
    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringNotContainsString('token=', $url);
    $this->assertStringNotContainsString('password=', $url);
  }

}
