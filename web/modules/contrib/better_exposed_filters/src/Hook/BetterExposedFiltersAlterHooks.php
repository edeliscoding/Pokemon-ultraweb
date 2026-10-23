<?php

declare(strict_types=1);

namespace Drupal\better_exposed_filters\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form-alter hooks for Better Exposed Filters.
 */
class BetterExposedFiltersAlterHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter() for views_ui_config_item_form.
   */
  #[Hook('form_views_ui_config_item_form_alter')]
  public function formViewsUiConfigItemFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!$this->moduleHandler->moduleExists('token')) {
      $text = $this->t('Enable the Token module to allow token replacement in this field.');
      if (empty($form['options']['expose']['description']['#description'])) {
        $form['options']['expose']['description']['#description'] = $text;
      }
      else {
        $form['options']['expose']['description']['#description'] .= " $text";
      }
      return;
    }
    // Adds global token replacements, if available.
    $text = $this->t('Tokens are allowed in this field. Replacement options can be found in the "Global replacement patterns" section, below.');
    if (empty($form['options']['expose']['description']['#description'])) {
      $form['options']['expose']['description']['#description'] = $text;
    }
    else {
      $form['options']['expose']['description']['#description'] .= " $text";
    }
    $form['options']['expose']['global_replacement_tokens'] = [
      '#title' => $this->t('Global replacement patterns (for description field only)'),
      '#type' => 'details',
      '#weight' => 151,
    ];
    $form['options']['expose']['global_replacement_tokens']['list'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [],
    ];
  }

}
