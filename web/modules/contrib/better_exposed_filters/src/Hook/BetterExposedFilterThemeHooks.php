<?php

declare(strict_types=1);

namespace Drupal\better_exposed_filters\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Theme, theme-suggestion, and preprocess hooks for Better Exposed Filters.
 */
class BetterExposedFilterThemeHooks {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected RequestStack $requestStack,
    #[Autowire(service: 'token')]
    protected ?Token $token = NULL,
  ) {
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'bef_checkboxes' => [
        'render element' => 'element',
      ],
      'bef_radios' => [
        'render element' => 'element',
      ],
      'bef_links' => [
        'render element' => 'element',
      ],
      'bef_hidden' => [
        'render element' => 'element',
      ],
      'bef_number' => [
        'render element' => 'element',
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_alter().
   */
  #[Hook('theme_suggestions_alter')]
  public function themeSuggestionsAlter(array &$suggestions, array $variables, string $hook): void {
    // Target bef elements.
    if ($hook !== 'form_element' && !str_starts_with($hook, 'bef_')) {
      return;
    }
    $plugin_type = $variables['element']['#context']['#plugin_type'] ?? FALSE;
    if ($plugin_type !== 'bef') {
      return;
    }

    $view_id = $variables['element']['#context']['#view_id'] ?? NULL;
    $display_id = $variables['element']['#context']['#display_id'] ?? NULL;
    $field_name = $variables['element']['#name'] ?? NULL;

    if ($view_id) {
      $suggestions[] = $hook . '__' . $view_id;
      $suggestions[] = $hook . '__' . $view_id . '__' . $field_name;
      if ($display_id) {
        $suggestions[] = $hook . '__' . $view_id . '__' . $display_id;
        $suggestions[] = $hook . '__' . $view_id . '__' . $display_id . '__' . $field_name;
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for views-exposed-form.html.twig.
   */
  #[Hook('preprocess_views_exposed_form')]
  public function preprocessViewsExposedForm(array &$variables): void {
    if (!$this->token) {
      // Token module not installed.
      return;
    }
    // Replaces tokens in description field of the exposed filter.
    foreach ($variables['form']['#info'] as $name => &$info) {
      if (isset($info['description']) && str_starts_with($name, 'filter-') && isset($variables['form'][explode('filter-', $name)[1]]['#description'])) {
        $info['description'] = $this->token->replace($info['description']);
        $variables['form'][explode('filter-', $name)[1]]['#description'] = $info['description'];
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for bef-checkboxes.html.twig.
   */
  #[Hook('preprocess_bef_checkboxes')]
  public function preprocessBefCheckboxes(array &$variables): void {
    $element = &$variables['element'];

    // Create new wrapper attributes since the element attributes will be used
    // on the fieldset (@see template_preprocess_fieldset).
    $variables['wrapper_attributes'] = new Attribute();

    $variables['children'] = Element::children($element);
    $variables['show_select_all_none'] = $element['#bef_select_all_none'] ?? FALSE;
    $variables['show_select_all_none_nested'] = $element['#bef_select_all_none_nested'] ?? FALSE;
    $variables['display_inline'] = $element['#bef_display_inline'] ?? FALSE;

    // Set element name.
    $variables['attributes']['name'] = $element['#name'];

    // Handle nested checkboxes.
    if (!empty($variables['element']['#bef_nested'])) {
      self::preprocessNestedElements($variables);
    }

    // Handle scrollable container.
    if (!empty($element['#bef_scrollable'])) {
      $variables['wrapper_attributes']->addClass('bef-scrollable');
      $variables['wrapper_attributes']->setAttribute('style', 'max-height: ' . (int) $element['#bef_scrollable_height'] . 'px;');
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for bef-radios.html.twig.
   */
  #[Hook('preprocess_bef_radios')]
  public function preprocessBefRadios(array &$variables): void {
    $element = &$variables['element'];

    // Create new wrapper attributes since the element attributes will be used
    // on the fieldset (@see template_preprocess_fieldset).
    $variables['wrapper_attributes'] = new Attribute();

    $variables['children'] = Element::children($element);
    $variables['display_inline'] = $element['#bef_display_inline'] ?? FALSE;

    // Set element name.
    $variables['attributes']['name'] = $element['#name'];

    // Handle nested radio buttons.
    if (!empty($variables['element']['#bef_nested'])) {
      self::preprocessNestedElements($variables);
    }

    // Handle scrollable container.
    if (!empty($element['#bef_scrollable'])) {
      $variables['wrapper_attributes']->addClass('bef-scrollable');
      $variables['wrapper_attributes']->setAttribute('style', 'max-height: ' . (int) $element['#bef_scrollable_height'] . 'px;');
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for bef-number.html.twig.
   */
  #[Hook('preprocess_bef_number')]
  public function preprocessBefNumber(array &$variables): void {
    $element = &$variables['element'];
    $variables['children'] = Element::children($element);
    $variables['display_inline'] = $element['#bef_display_inline'] ?? FALSE;

    if (!isset($element['#name'])) {
      $variables['attributes']['name'] = $element['#parent'][0];
    }
    else {
      $variables['attributes']['name'] = $element['#name'];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for bef-hidden.html.twig.
   */
  #[Hook('preprocess_bef_hidden')]
  public function preprocessBefHidden(array &$variables): void {
    $element = $variables['element'];

    // This theme function is only used for multi-select elements.
    $variables['is_multiple'] = TRUE;
    $variables['selected'] = empty($element['#value']) ? $element['#default_value'] : $element['#value'];
    $variables['hidden_elements'] = [];
    foreach ($element['#options'] as $value => $label) {
      $variables['hidden_elements'][$value] = [
        '#type' => 'hidden',
        '#value' => $value,
        '#name' => $element['#name'] . '[]',
      ];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for bef-links.html.twig.
   */
  #[Hook('preprocess_bef_links')]
  public function preprocessBefLinks(array &$variables): void {
    // Collect some variables before we start tweaking the element.
    $element = &$variables['element'];
    $options = $element['#options'];
    $name = $element['#name'];
    $multiple = $element['#multiple'] ?? FALSE;

    // We remove these keys because bef-links renders as a div making these
    // not needed.
    $keys_to_remove = ['size', 'name', 'multiple'];

    foreach ($keys_to_remove as $key) {
      if (isset($variables['attributes'][$key])) {
        // Add the attribute as a data-* attribute for easier JS access.
        $variables['attributes']["data-$key"] = $variables['attributes'][$key];
      }
    }
    $variables['attributes'] = array_diff_key($variables['attributes'], array_flip($keys_to_remove));

    // Get the query string arguments from the current request.
    $existing_query = $this->requestStack->getCurrentRequest()->query->all();

    // Remove page parameter from query.
    unset($existing_query['page']);

    // Store selected values.
    $selectedValues = $element['#value'];
    if (!is_array($selectedValues)) {
      $selectedValues = [$selectedValues => $selectedValues];
    }
    $variables['selected'] = $selectedValues;

    // Set hidden elements array.
    $hiddens = [];
    foreach ($options as $k => $v) {
      if (!empty($selectedValues[$k])) {
        $hidden_name = $multiple ? $name . '[' . $k . ']' : $name;
        $hiddens[$hidden_name] = $selectedValues[$k];
      }
    }
    $variables['hiddens'] = $hiddens;

    $variables['links'] = [];
    foreach ($options as $optionValue => $optionLabel) {
      // Build a new Url object for each link since the query string changes
      // with each option.
      /** @var \Drupal\Core\Url $url */
      $url = clone $element['#bef_path'];

      // Allow visitors to toggle a filter setting on and off. This is not as
      // simple as setOptions('foo', '') as that still leaves an entry which is
      // rendered rather than removing the entry from the query string
      // altogether. Calling $url->setOption() still leaves a value behind.
      // Instead, we work with the entire options array and remove items from
      // it as needed.
      $urlOptions = $url->getOptions();

      if ($multiple) {
        $newQuery = $existing_query;
        // Unset any empty parameters first.
        if (empty($newQuery[$name])) {
          unset($newQuery[$name]);
        }
        if (!isset($newQuery[$name])) {
          $newQuery[$name] = [];
        }
        if (in_array($optionValue, $selectedValues)) {
          // Allow users to toggle an option using the same link.
          $newQuery[$name] = array_filter($newQuery[$name], function ($value) use ($optionValue) {
            return $value != $optionValue;
          });
        }
        else {
          if (!isset($newQuery[$name]) || !is_array($newQuery[$name])) {
            $newQuery[$name] = [];
          }
          $newQuery[$name][$optionValue] = $optionValue;
        }

        $urlOptions['query'] = $newQuery;
      }
      else {
        if (strval($optionValue) === $element['#value']) {
          // Allow toggle link functionality -- click the same link to turn an
          // option on or off.
          $newQuery = $existing_query;
          unset($newQuery[$name]);
          if (empty($newQuery)) {
            // Remove the query string completely.
            unset($urlOptions['query']);
          }
          else {
            $urlOptions['query'] = $newQuery;
          }
        }
        else {
          $urlOptions['query'] = $existing_query;
          $urlOptions['query'][$name] = $optionValue;
        }
      }

      // Add our updated options to the Url object.
      $url->setOptions($urlOptions);

      // Render as <button>. Anchors would be semantically wrong here: the
      // click triggers a filter update / form submit rather than navigation
      // (see accessibility guidance on button vs. link). The URL travels as
      // data-url so the non-ajax JS handler can perform the actual
      // navigation, and bef_links.css provides visual parity with the old
      // anchor styling.
      $variables['links'][$optionValue] = [
        '#attributes' => [
          'type' => 'button',
          'id' => Html::getUniqueId('edit-' . implode('-', [$name, $optionValue])),
          'data-bef-value' => $optionValue,
          'data-name' => $name . '[' . $optionValue . ']',
          'data-url' => $url->toString(),
          'aria-pressed' => 'false',
          'class' => [
            'bef-link',
            Html::getUniqueId('edit-' . $name),
          ],
        ],
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $optionLabel,
      ];

      /*
       * @see https://stackoverflow.com/questions/13846769/php-in-array-0-value
       */
      if (in_array(strval($optionValue), $selectedValues)) {
        $variables['links'][$optionValue]['#attributes']['class'][] = 'bef-link--selected';
        $variables['links'][$optionValue]['#attributes']['aria-pressed'] = 'true';
      }
    }

    // Handle nested links. But first add the links as children to the element
    // for consistent processing between checkboxes/radio buttons and links.
    $variables['element'] = array_replace($variables['element'], $variables['links']);
    $variables['children'] = Element::children($variables['element']);
    if (!empty($variables['element']['#bef_nested'])) {
      self::preprocessNestedElements($variables);
    }

    // Handle scrollable container.
    if (!empty($element['#bef_scrollable'])) {
      $variables['attributes']['class'][] = 'bef-scrollable';
      $variables['attributes']['style'] = 'max-height: ' . (int) $element['#bef_scrollable_height'] . 'px;';
    }
  }

  /**
   * Add 'is_nested' and 'depth' info for hierarchical form-element rendering.
   *
   * Requires 'children' to be set in $variables before being called.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the exposed form element.
   */
  protected static function preprocessNestedElements(array &$variables): void {
    // Provide a hierarchical info on the element children for the template to
    // render as a nested <ul>. Views prepends '-' characters for each level of
    // depth in the vocabulary. Store that information, but remove the hyphens
    // as we don't want to display them.
    $variables['is_nested'] = TRUE;
    $variables['depth'] = [];
    foreach ($variables['children'] as $child) {
      if ($child === 'All') {
        // For non-required filters, put any/all option at the root.
        $variables['depth'][$child] = 0;
        // And don't change the text as it defaults to "- Any -" and we do not
        // want to remove the leading hyphens.
        continue;
      }

      // Checkboxes/radios carry the label in #title; bef_links buttons carry
      // it in #value (html_tag render element).
      if (isset($variables['element'][$child]['#title'])) {
        $original = $variables['element'][$child]['#title'];
        $variables['element'][$child]['#title'] = ltrim($original, '-');
        $variables['depth'][$child] = strlen($original) - strlen($variables['element'][$child]['#title']);
      }
      else {
        $original = $variables['element'][$child]['#value'];
        $variables['element'][$child]['#value'] = ltrim($original, '-');
        $variables['depth'][$child] = strlen($original) - strlen($variables['element'][$child]['#value']);
      }
    }
  }

}
