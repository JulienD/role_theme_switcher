<?php

namespace Drupal\role_theme_switcher\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the theme switcher configuration form.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a new AdminSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ThemeHandlerInterface $theme_handler) {
    parent::__construct($config_factory);
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'role_theme_switcher.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'role_theme_switcher_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    $form['role_theme_switcher'] = [
      '#type' => 'table',
      '#title' => $this->t('Role processing order'),
      '#header' => [
        $this->t('Role name'),
        $this->t('Selected Theme'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('There are no items yet. Add roles.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'role-order-weight',
        ],
      ],
    ];

    // Generates a form element for each role.
    $roles = $this->getUserRoles();
    foreach ($roles as $rid => $role) {

      $form['role_theme_switcher'][$rid]['#attributes']['class'][] = 'draggable';
      $form['role_theme_switcher'][$rid]['#weight'] = $role['weight'];

      // Role name col.
      $form['role_theme_switcher'][$rid]['role'] =[
        '#plain_text' => $role['name']
      ];

      // Theme col.
      $form['role_theme_switcher'][$rid]['theme'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Theme'),
        '#options' => $this->getThemeList(),
        '#empty_option' => $this->t('Default theme'),
        '#empty_value' => '',
        '#default_value' => $role['theme'],
      ];

      // Weight col.
      $form['role_theme_switcher'][$rid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $role['name']]),
        '#title_display' => 'invisible',
        '#default_value' => $role['weight'],
        '#attributes' => ['class' => ['role-order-weight']],
        '#delta' => 50,
      ];
    }

    $form['actions']['submit']['#tableselect'] = TRUE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $data = [];
    $roles = user_role_names();
    foreach ($roles as $rid => $role) {
      $data[$rid] = $form_state->getValue('role_theme_switcher')[$rid];
    }

    $this->config('role_theme_switcher.settings')->set('roles', $data)->save();
  }

  /**
   * Gets a list of available Roles.
   *
   * @return array
   *   Returns an array of Roles.
   */
  private function getUserRoles() {

    $values = [];

    $config = $this->config('role_theme_switcher.settings')->get('roles');

    $roles = user_role_names();
    foreach ($roles as $rid => $name) {
      $values[$rid] = [
        'rid' => $rid,
        'name' => $name,
        'weight' => $config[$rid]['weight'] ? (int) $config[$rid]['weight'] : 0,
        'theme' => $config[$rid]['theme'] ? $config[$rid]['theme'] : '',
      ];
    }

    uasort($values, function ($a, $b) {
      $r = $a['weight'] - $b['weight'];
      return $r;
    });

    return $values;
  }

  /**
   * Gets a list of active themes without hidden ones.
   *
   * @return array
   *   An array with all active themes.
   */
  private function getThemeList() {

    $themes_list = [];

    $installed_themes = $this->themeHandler->listInfo();
    foreach ($installed_themes as $theme) {
      // Exclude theme defined as hidden.
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      $themes_list[$theme->getName()] = $theme->info['name'];
    }

    return $themes_list;
  }

}
