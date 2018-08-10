<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\AuditResultResponseInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;
use Drupal\adv_audit\Exception\RequirementsException;
use Drupal\adv_audit\Plugin\RequirementsInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\adv_audit\Message\AuditMessagesStorageInterface;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\hacked\Controller\HackedController;

/**
 * @AdvAuditCheck(
 *   id = "patched_modules_check",
 *   label = @Translation("Patched modules."),
 *   category = "core_and_modules",
 *   requirements = {
 *     "module" = "hacked",
 *   },
 *   enabled = TRUE,
 *   severity = "high"
 * )
 */
class PatchedModulesCheck extends AdvAuditCheckBase implements  AdvAuditCheckInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new ModulesUpdateCheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Process checkpoint review.
   */
  public function perform() {
    $params = [];
    $hacked = new HackedController();
    $hacked = $hacked->hackedStatus();

    $status = AuditResultResponseInterface::RESULT_PASS;
    $reason = NULL;
    $build = ['#theme' => 'hacked_report'];
    $key = '#data';

    foreach ($hacked[$key] as $project) {
      if ($project['counts']['different'] != 0 && $project['project_type'] == 'module') {
        $status = AuditResultResponseInterface::RESULT_FAIL;
        $build[$key][] = $project;
      }
    }

    if ($status == AuditResultResponseInterface::RESULT_FAIL) {
      $params['hacked_modules'] = $build;
    }

    return new AuditReason($this->id(), $status, $reason, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements() {
    // Check whether the current test plugin
    // requirements are met or not.
    if (!($this instanceof RequirementsInterface)) {
      return;
    }

    $reqs = $this->pluginDefinition['requirements'];
    $key_to_check = 'module';

    if (empty($reqs)) {
      // There are no requirements to check.
      return;
    }

    if (isset($reqs[$key_to_check])) {
      foreach ($reqs[$key_to_check] as $module_name) {
        if (!$this->moduleHandler->moduleExists($module_name)) {
          throw new RequirementsException('Module ' . $module_name . ' are not enabled.', $reqs[$key_to_check]);
        }
      }
    }

    $hacked = new HackedController();
    $hacked = $hacked->hackedStatus();
    $is_validated = is_array($hacked) && isset($hacked['#data']);

    if (!$is_validated) {
      $link = Link::fromTextAndUrl('here', Url::fromRoute('hacked.report'));
      throw new RequirementsException($this->t('Hacked report is not generated. You can generate it @link', array('@link' => $link)), $reqs[$key_to_check]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function auditReportRender(AuditReason $reason, $type) {
    if ($type == AuditMessagesStorageInterface::MSG_TYPE_ACTIONS) {
      $arguments = $reason->getArguments();
      if (empty($arguments)) {
        return [];
      }

      $key = 'hacked_modules';

      if (!empty($arguments[$key])) {
        return $arguments[$key];
      }
    }

    return [];
  }

}