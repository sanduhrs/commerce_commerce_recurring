<?php

namespace Drupal\commerce_recurring\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\commerce_recurring\RecurringOrderManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the job type for activating subscriptions.
 *
 * @AdvancedQueueJobType(
 *   id = "commerce_subscription_activate",
 *   label = @Translation("Activate subscription"),
 * )
 */
class SubscriptionActivate extends JobTypeBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The recurring order manager.
   *
   * @var \Drupal\commerce_recurring\RecurringOrderManagerInterface
   */
  protected $recurringOrderManager;

  /**
   * Constructs a new SubscriptionActivate object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_recurring\RecurringOrderManagerInterface $recurring_order_manager
   *   The recurring order manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RecurringOrderManagerInterface $recurring_order_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->recurringOrderManager = $recurring_order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('commerce_recurring.order_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    $subscription_id = $job->getPayload()['subscription_id'];
    $subscription_storage = $this->entityTypeManager->getStorage('commerce_subscription');
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = $subscription_storage->load($subscription_id);
    if (!$subscription) {
      return JobResult::failure('Subscription not found.');
    }
    if ($subscription->getState()->value != 'pending') {
      return JobResult::failure('Subscription not pending.');
    }
    $transition = $subscription->getState()->getWorkflow()->getTransition('activate');
    $subscription->getState()->applyTransition($transition);
    $this->recurringOrderManager->ensureOrder($subscription);

    return JobResult::success();
  }

}
