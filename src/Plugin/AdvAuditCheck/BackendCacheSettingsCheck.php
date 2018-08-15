<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\AuditResultResponseInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;

/**
 * @AdvAuditCheck(
 *  id = "backend_cache_settings",
 *  label = @Translation("Memcache/Redis settings"),
 *  category = "performance",
 *  severity = "critical",
 *  requirements = {},
 *  enabled = true,
 * )
 */
class BackendCacheSettingsCheck extends AdvAuditCheckBase implements  AdvAuditCheckInterface, ContainerFactoryPluginInterface {

  /**
   * The settings object.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * Constructs a new CronSettingsCheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Settings $settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function perform() {
    $cache_settings = $this->settings->get('cache');
    $cache_default = isset($cache_settings['default']) ? $cache_settings['default'] : 'cache.backend.database';

    switch ($cache_default) {
      case 'cache.backend.memcache':
        return $this->memcached_check();
        break;

      case 'cache.backend.redis':
        return $this->redis_check();
        break;

      default:
        return new AuditReason($this->id(),
          AuditResultResponseInterface::RESULT_FAIL);
    }

  }

  /**
   * Check memcached connection.
   */
  private function memcached_check() {
    return new AuditReason($this->id(),
      AuditResultResponseInterface::RESULT_PASS,
      $this->t('Memcached is configured properly')
    );

  }

  /**
   * Check redis connection.
   */
  private function redis_check() {
    return new AuditReason($this->id(),
      AuditResultResponseInterface::RESULT_PASS,
      $this->t('Redis is configured properly'));
  }

}
