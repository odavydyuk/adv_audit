<?php

namespace Drupal\adv_audit\Plugin\AdvAuditCheck;

use Drupal\adv_audit\AuditReason;
use Drupal\adv_audit\Message\AuditMessagesStorageInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\adv_audit\Renderer\AdvAuditReasonRenderableInterface;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;

/**
 * Provide imageApi optimize check.
 *
 * @AdvAuditCheck(
 *  id = "imageapi_optimize_check",
 *  label = @Translation("ImageAPI Optimize"),
 *  category = "performance",
 *  severity = "low",
 *  enabled = true,
 *  requirements = {},
 * )
 */
class ImageAPICheck extends AdvAuditCheckBase implements ContainerFactoryPluginInterface, AdvAuditReasonRenderableInterface {

  /**
   * The audit messages storage service.
   *
   * @var \Drupal\adv_audit\Message\AuditMessagesStorageInterface
   */
  protected $messagesStorage;

  /**
   * Interface for working with drupal module system.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new ImageAPICheck object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\adv_audit\Message\AuditMessagesStorageInterface $messages_storage
   *   Interface for the audit messages.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Interface for working with drupal module system.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AuditMessagesStorageInterface $messages_storage, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messagesStorage = $messages_storage;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('adv_audit.messages'),
      $container->get('module_handler')
    );
  }

  /**
   * The actual procedure of carrying out the check.
   *
   * @return \Drupal\adv_audit\AuditReason
   *   Return AuditReason object instance.
   */
  public function perform() {
    // Created placeholder link for messages.
    $url = Url::fromUri('https://www.drupal.org/project/imageapi_optimize', ['attributes' => ['target' => '_blank']]);
    $link = Link::fromTextAndUrl('ImageAPI Optimize', $url);
    $arguments = [
      '%link' => $link->toString(),
    ];
    $message = NULL;

    if (!$this->moduleHandler->moduleExists('imageapi_optimize')) {
      $message = $this->t('The ImageApi module is not installed.');
      return $this->fail($message, $arguments);
    }

    // Check if pipelines were created.
    $pipelines = imageapi_optimize_pipeline_options(FALSE, TRUE);
    $pipeline_keys = array_keys($pipelines);
    if (count($pipeline_keys) === 1 && empty($pipeline_keys[0])) {
      $message = $this->t('ImageApi is installed, but no pipeline is created.');
      return $this->fail($message, $arguments);
    }

    // Check if every image_style uses some pipeline.
    $styles = ImageStyle::loadMultiple();
    $style_names = [];
    foreach ($styles as $style) {
      // Get pipeline for image style.
      // @see Drupal\imageapi_optimize\Entity\ImageStyleWithPipeline::getPipeline().
      $pipeline = $style->getPipeline();

      // Check if image_style's pipeline exist.
      if (!isset($pipelines[$pipeline])) {
        $style_names[] = $style->get('label');
      }
    }
    if (count($style_names)) {
      $arguments['list'] = $style_names;
      $message = $this->t('ImageApi is installed, some image styles are not configured:');
      return $this->fail($message, $arguments);
    }

    return $this->success($arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function auditReportRender(AuditReason $reason, $type) {
    $build = [];
    if ($type === AuditMessagesStorageInterface::MSG_TYPE_FAIL) {
      $arguments = $reason->getArguments();
      $build = [
        '#type' => 'container',
      ];

      // Render image_style list.
      if (isset($arguments['list'])) {
        $build['list'] = [
          '#theme' => 'item_list',
          '#items' => $arguments['list'],
          '#weight' => 1,
        ];
        unset($arguments['list']);
      }

      $build['message'] = [
        '#weight' => 0,
        '#markup' => $reason->getReason(),
      ];

    }

    return $build;
  }

}
