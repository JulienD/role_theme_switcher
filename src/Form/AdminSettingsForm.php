<?php

namespace Drupal\role_theme_switcher\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminSettingsForm.
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
   */
  public function __construct(ConfigFactoryInterface $config_factory, ThemeHandlerInterface $theme_handler) {
    parent::__construct($config_factory);
    $this->themeHandler = $theme_handler;
  }

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

  public function buildForm(array $form, FormStateInterface $form_state) {

    // Gets a list of active themes without hidden ones.
    $themes = $this->getThemeList();

    $form['info'] = array(
      '#markup' => $this->t('This form allows you to assign separate themes for different roles
       (including anonymous) in your system. <br/> Theme will be applied to users following the prioritized roles below.'),
    );

    $form['role_theme_switcher'] = [
      '#tree' => TRUE,
      '#weight' => 50,
    ];

    $form['role_theme_switcher']['role'] = array(
      '#type' => 'table',
      '#title' => $this->t('Role processing order'),
      '#empty' => t('There are no items yet. Add roles.', array()),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'role_theme_switcher-order-weight',
        ),
      ),
      '#theme_wrappers' => ['form_element'],
    );

    // Generates a form element for each role.
    foreach ($this->getUserRoles() as $rid => $role) {

      $form['role_theme_switcher']['role'][$rid]['#attributes']['class'][] = 'draggable';
      $form['role_theme_switcher']['role'][$rid]['#weight'] = $role['weight'];

      $form['role_theme_switcher']['role'][$rid]['role'] = array(
        '#plain_text' => $role['name'],
      );

      $form['role_theme_switcher']['role'][$rid]['theme'] = array(
        '#type' => 'select',
        '#title' => t('Select Theme'),
        '#options' => array_merge(array('' => $this->t('Default theme')), $themes),
        '#default_value' => $role['theme'],
      );

      $form['role_theme_switcher']['role'][$rid]['weight'] = array(
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#default_value' => $role['weight'],
        '#attributes' => array('class' => array('role_theme_switcher-order-weight')),
        '#delta' => 50,
      );
    }

    return parent::buildForm($form, $form_state);
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

    $roles = [];
    foreach (user_role_names() as $id => $role) {
      $roles[$id] = $form_state->getValue('role_theme_switcher')['role'][$id];
    }

    $this->config('role_theme_switcher.settings')->set('roles', $roles)->save();
  }


  /**
   *
   * @return array|mixed|null
   */
  private function getUserRoles () {

    $roles = user_role_names();

    $config = $this->config('role_theme_switcher.settings')->get('roles');

    $values = [];
    foreach ($roles as $rid => $name) {
      $values[$rid] = [
         'rid' => $rid,
         'name' => $name,
         'weight' => $config[$rid]['weight'] ? $config[$rid]['weight'] : 0,
         'theme' => $config[$rid]['theme'] ? $config[$rid]['theme'] : '',
       ];

      uasort($values, function($a, $b) {
        $r =  $a['weight'] - $b['weight'];
        return $r;
      });
    }

    return $values;
  }

  /**
   *
   * @return array|mixed|null
   */
  private function getThemeList() {

    // Gets a list of active themes without hidden ones.
    $themes_list = [];
    foreach ($this->themeHandler->listInfo() as $key => $theme) {
      if (!empty($theme->info['hidden'])) {
        continue;
      }
      $themes_list[$theme->getName()] = $theme->info['name'];
    }

    return $themes_list;
  }

}
