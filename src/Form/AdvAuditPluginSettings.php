<?php

namespace Drupal\adv_audit\Form;

use Drupal\adv_audit\AuditExecutable;
use Drupal\adv_audit\AuditResultResponseInterface;
use Drupal\adv_audit\Message\AuditMessageCapture;
use Drupal\adv_audit\Message\AuditMessagesStorageInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckInterface;
use Drupal\adv_audit\Plugin\AdvAuditCheckManager;
use Drupal\adv_audit\Renderer\AdvAuditReasonRenderableInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\SubformState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Component\Utility\Html;

/**
 * Provides implementation for the Run form.
 */
class AdvAuditPluginSettings extends FormBase {

  /**
   * Advanced plugin manager.
   *
   * @var \Drupal\adv_audit\Plugin\AdvAuditCheckManager
   */
  protected $advAuditPluginManager;

  /**
   * The Messages storeage service.
   *
   * @var \Drupal\adv_audit\Message\AuditMessagesStorageInterface
   */
  protected $messageStorage;

  /**
   * THe current request object.
   *
   * @var null|\Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The plugin id.
   *
   * @var mixed
   */
  protected $pluginId;

  /**
   * The plugin instance.
   *
   * @var \Drupal\adv_audit\Plugin\AdvAuditCheckBase
   */
  protected $pluginInstance;

  /**
   * AdvAuditPluginSettings constructor.
   */
  public function __construct(AdvAuditCheckManager $manager, AuditMessagesStorageInterface $storage_message, RequestStack $request_stack) {
    $this->advAuditPluginManager = $manager;
    $this->messageStorage = $storage_message;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->pluginId = $request_stack->getCurrentRequest()->attributes->get('plugin_id');
    $this->pluginInstance = $this->advAuditPluginManager->createInstance($this->pluginId);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.adv_audit_check'),
      $container->get('adv_audit.messages'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advanced-audit-edit-plugin';
  }

  /**
   * Get title of config form page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Return TranslatableMarkup object.
   */
  public function getTitle() {
    return $this->t('Configure plugin @label form', ['@label' => $this->pluginInstance->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->pluginInstance->isEnabled(),
    ];

    $form['severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Severity'),
      '#options' => [
        AdvAuditCheckInterface::SEVERITY_CRITICAL => 'Critical',
        AdvAuditCheckInterface::SEVERITY_HIGH => 'High',
        AdvAuditCheckInterface::SEVERITY_LOW => 'Low',
      ],
      '#default_value' => $this->pluginInstance->getSeverityLevel(),
    ];

    if ($this->pluginInstance instanceof PluginFormInterface) {
      $processor_id = $this->pluginId;
      $form['settings'][$processor_id] = [
        '#type' => 'details',
        '#title' => $this->pluginInstance->label(),
        '#open' => TRUE,
        '#group' => 'processor_settings',
        '#parents' => [$processor_id, 'settings'],
        '#attributes' => [
          'class' => [
            'audit-processor-settings-' . Html::cleanCssIdentifier($processor_id),
          ],
        ],
      ];
      $processor_form_state = SubformState::createForSubform($form['settings'][$processor_id], $form, $form_state);
      $form['settings'][$processor_id] += $this->pluginInstance->buildConfigurationForm($form['settings'][$processor_id], $processor_form_state);
    }
    else {
      unset($form['settings'][$processor_id]);
    }

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_DESCRIPTION] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#default_value' => $this->messageStorage->get($this->pluginId, AuditMessagesStorageInterface::MSG_TYPE_DESCRIPTION),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_ACTIONS] = [
      '#type' => 'text_format',
      '#title' => $this->t('Action'),
      '#description' => $this->t('What actions should be provided to fix plugin issue.'),
      '#default_value' => $this->messageStorage->get($this->pluginId, AuditMessagesStorageInterface::MSG_TYPE_ACTIONS),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_IMPACTS] = [
      '#type' => 'text_format',
      '#title' => $this->t('Impact'),
      '#description' => $this->t('Why this issue should be fixed.'),
      '#default_value' => $this->messageStorage->get($this->pluginId, AuditMessagesStorageInterface::MSG_TYPE_IMPACTS),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_FAIL] = [
      '#type' => 'text_format',
      '#title' => $this->t('Fail message'),
      '#description' => $this->t('This text is used in case when verification was failed.'),
      '#default_value' => $this->messageStorage->get($this->pluginId, AuditMessagesStorageInterface::MSG_TYPE_FAIL),
    ];

    $form['messages'][AuditMessagesStorageInterface::MSG_TYPE_SUCCESS] = [
      '#type' => 'text_format',
      '#title' => $this->t('Success message'),
      '#description' => $this->t('This text is used in case when verification was failed.'),
      '#default_value' => $this->messageStorage->get($this->pluginId, AuditMessagesStorageInterface::MSG_TYPE_SUCCESS),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save plugin configuration'),
    ];

    $form['run'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run'),
      '#submit' => ['::runTest'],
      '#attributes' => [
        'class' => ['button--primary'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Iterate over all processors that have a form and are enabled.
    if ($this->pluginInstance instanceof PluginFormInterface) {
      $processor_id = $this->pluginId;
      $processor_form_state = SubformState::createForSubform($form['settings'][$processor_id], $form, $form_state);
      $this->pluginInstance->validateConfigurationForm($form['settings'][$processor_id], $processor_form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->pluginInstance->setPluginStatus($form_state->getValue('status'));
    $this->pluginInstance->setSeverityLevel($form_state->getValue('severity'));
    foreach ($form_state->getValue('messages', []) as $type => $text) {
      $this->messageStorage->set($this->pluginId, $type, $text['value']);
    }

    // Handle plugin config form submit.
    if ($this->pluginInstance instanceof PluginFormInterface) {
      $processor_id = $this->pluginId;
      $processor_form_state = SubformState::createForSubform($form['settings'][$processor_id], $form, $form_state);
      $this->pluginInstance->submitConfigurationForm($form['settings'][$processor_id], $processor_form_state);
    }
  }

  /**
   * Checks if the user has access for edit this plugin.
   */
  public function checkAccess(AccountInterface $account) {
    $id = $this->pluginInstance->getCategoryName();
    return AccessResult::allowedIfHasPermission($account, "adv_audit category $id edit");
  }

  /**
   * Temporary submit handler for run audit and display result.
   */
  public function runTest(array &$form, FormStateInterface $form_state) {
    // Set context action for instance initialize plugin.
    $configuration[AuditExecutable::AUDIT_EXECUTE_RUN] = TRUE;
    $messages = new AuditMessageCapture();
    $executable = new AuditExecutable($this->pluginInstance->id(), $configuration, $messages);

    $test_reason = $executable->performTest();
    if ($test_reason->getStatus() == AuditResultResponseInterface::RESULT_PASS) {
      drupal_set_message($this->t('Audit check is PASSED'), 'status');
    }
    else {
      drupal_set_message($this->t('Audit check is FAILED<br/>Reason:<p>@reason</p>', ['@reason' => $test_reason->getReason()]), 'error');
    }

    // Try to build output from plugin instance.
    if ($this->pluginInstance instanceof AdvAuditReasonRenderableInterface) {
      // If needed you can add call to ::auditReportRender for test.
    }

  }

}
