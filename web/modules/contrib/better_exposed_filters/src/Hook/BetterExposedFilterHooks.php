<?php

declare(strict_types=1);

namespace Drupal\better_exposed_filters\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * General hook implementations for Better Exposed Filters.
 */
class BetterExposedFilterHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.better_exposed_filters') {
      return NULL;
    }

    $output = '<h3>' . $this->t('About') . '</h3>';
    $output .= '<p>' . $this->t('Better Exposed Filters (BEF) modifies the use of Views by replacing the \'single\' or \'multi\' <em>select boxes</em> with <em>radio buttons or checkboxes</em>. Views offers the ability to expose filters to the end user. When you expose a filter, you allow the user to interact with the view making it easy to build an advanced search. Better Exposed Filters gives you greater control over the rendering of exposed filters. For more information, see the <a href=":online">online documentation for the Better Exposed Filters module</a>.', [':online' => 'https://www.drupal.org/node/766974']) . '</p>';
    $output .= '<h3>' . $this->t('Uses') . '</h3>';
    $output .= '<dt>' . $this->t('Editing or Creating Views') . '</dt>';
    $output .= '<dd>' . $this->t('Better Exposed Filters is used on <a href=":views">Views</a> that use an exposed filter. Views filters are used to reduce the result set of a View to a manageable amount of data. BEF only operates on fields that have a limited number of options such as <a href=":node">Node</a>:Type or <a href=":taxonomy">Taxonomy</a>:TermID.', [
      ':views' => Url::fromRoute('help.page', ['name' => 'views'])->toString(),
      ':node' => Url::fromRoute('help.page', ['name' => 'node'])->toString(),
      ':taxonomy' => $this->moduleHandler->moduleExists('taxonomy')
        ? Url::fromRoute('help.page', ['name' => 'taxonomy'])->toString()
        : '#',
    ]) . '</dd>';
    $output .= '<dt>' . $this->t('Styling Better Exposed Filters') . '</dt>';
    $output .= '<dd>' . $this->t('BEF provides some additional HTML structure to help you style your exposed filters. For some common examples see the <a href=":doco">online documentation</a>.', [':doco' => 'https://www.drupal.org/node/766974']) . '</dd>';

    return $output;
  }

}
