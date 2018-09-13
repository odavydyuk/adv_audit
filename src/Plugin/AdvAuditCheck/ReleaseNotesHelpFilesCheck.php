<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Release notes & help files.
 *
 * @AdvAuditCheck(
 *   id = "release_notes_help_files",
 *   label = @Translation("Release notes & help files"),
 *   category = "server_configuration",
 *   requirements = {},
 *   enabled = true,
 *   severity = "critical"
 * )
 */
class ReleaseNotesHelpFilesCheck extends AdvAuditCheckBase implements ContainerFactoryPluginInterface {

  /**
   * Default files to be checked.
   */
  const DEFAULT_FILES = [
    'core/CHANGELOG.txt',
    'core/COPYRIGHT.txt',
    'core/INSTALL.mysql.txt',
    'core/INSTALL.pgsql.txt',
    'core/INSTALL.sqlite.txt',
    'core/INSTALL.txt',
    'core/LICENSE.txt',
    'core/MAINTAINERS.txt',
    'README.txt',
    'core/UPGRADE.txt',
    'themes/README.txt',
    'modules/README.txt',
  ];

  /**
   * The State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function configForm() {
    $description = t('Place one filepath per line as relative without preceding slash. i.e path/to/file.');
    $default_value = $this->state->get($this->buildStateConfigKey());
    $default_value = !empty($default_value) ? $default_value : implode("\r\n", self::DEFAULT_FILES);

    $form['files'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Files for checking'),
      '#description' => $description,
      '#default_value' => $default_value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configFormSubmit(array $form, FormStateInterface $form_state) {
    $base = ['additional_settings', 'plugin_config'];
    $value = $form_state->getValue(array_merge($base, ['files']));
    $this->state->set($this->buildStateConfigKey(), $value);
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {

    $config_files = $this->parseLines($this->state->get($this->buildStateConfigKey()));
    $config_files = !empty($config_files) ? $config_files : self::DEFAULT_FILES;

    $remaining_files = [];
    foreach ($config_files as $file) {
      if (file_exists(DRUPAL_ROOT . '/' . $file)) {
        array_push($remaining_files, $file);
      }
    }

    if (!empty($remaining_files)) {
      $issues = [];
      foreach ($remaining_files as $remaining_file) {
        $issues[] = [
          '@issue_title' => 'File on server: @file',
          '@file' => $remaining_file,
        ];
      }
      return $this->fail(NULL, ['issues' => $issues]);
    }

    return $this->success();
  }

  /**
   * Build key string for access to stored value from config.
   *
   * @return string
   *   The generated key.
   */
  private function buildStateConfigKey() {
    return 'adv_audit.plugin.' . $this->id() . '.config.files';
  }

}
