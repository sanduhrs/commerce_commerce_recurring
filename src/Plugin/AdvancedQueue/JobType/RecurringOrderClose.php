<?php

namespace Drupal\commerce_recurring\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_recurring\Event\PaymentDeclinedEvent;
use Drupal\commerce_recurring\Event\RecurringEvents;
use Drupal\commerce_recurring\RecurringOrderManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the job type for closing recurring orders.
 *
 * @AdvancedQueueJobType(
 *   id = "commerce_recurring_order_close",
 *   label = @Translation("Close recurring order"),
 * )
 */
class RecurringOrderClose extends JobTypeBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The recurring order manager.
   *
   * @var \Drupal\commerce_recurring\RecurringOrderManagerInterface
   */
  protected $recurringOrderManager;

  /**
   * Constructs a new RecurringOrderClose object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\commerce_recurring\RecurringOrderManagerInterface $recurring_order_manager
   *   The recurring order manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, RecurringOrderManagerInterface $recurring_order_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
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
      $container->get('event_dispatcher'),
      $container->get('commerce_recurring.order_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    $order_id = $job->getPayload()['order_id'];
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $order_storage->load($order_id);
    if (!$order) {
      return JobResult::failure('Order not found.');
    }

    try {
      $this->recurringOrderManager->closeOrder($order);
    }
    catch (DeclineException $exception) {
      // Both hard and soft declines need to be retried.
      // In case of a soft decline, the retry might succeed in charging the
      // same payment method. In case of a hard decline, the customer
      // might have changed their payment method since the last attempt.
      return $this->handleDecline($order, $exception, $job->getNumRetries());
    }

    return JobResult::success();
  }

  /**
   * Handles a declined order payment.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\commerce_payment\Exception\DeclineException $exception
   *   The decline exception.
   * @param int $num_retries
   *   The number of times the job was retried so far.
   *
   * @return \Drupal\advancedqueue\JobResult
   *   The job result.
   */
  protected function handleDecline(OrderInterface $order, DeclineException $exception, $num_retries) {
    /** @var \Drupal\commerce_recurring\Entity\BillingScheduleInterface $billing_schedule */
    $billing_schedule = $order->get('billing_schedule')->entity;
    $schedule = $billing_schedule->getRetrySchedule();
    $max_retries = count($schedule);
    if ($num_retries < $max_retries) {
      $retry_days = $schedule[$num_retries];
      $result = JobResult::failure($exception->getMessage(), $max_retries, 86400 * $retry_days);
    }
    else {
      $retry_days = 0;
      $result = JobResult::success('Dunning complete, recurring order not paid.');

      $transition = $order->getState()->getWorkflow()->getTransition('mark_failed');
      $order->getState()->applyTransition($transition);
      if ($billing_schedule->getUnpaidSubscriptionState() != 'active') {
        $this->updateSubscriptions($order, $billing_schedule->getUnpaidSubscriptionState());
      }
    }
    // Subscribers can choose to send a dunning email.
    $event = new PaymentDeclinedEvent($order, $retry_days, $num_retries, $max_retries);
    $this->eventDispatcher->dispatch(RecurringEvents::PAYMENT_DECLINED, $event);
    $order->save();

    return $result;
  }

  /**
   * Updates the recurring order's subscriptions to the new state.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The recurring order.
   * @param string $new_state_id
   *   The new state.
   */
  protected function updateSubscriptions(OrderInterface $order, $new_state_id) {
    $subscriptions = $this->recurringOrderManager->collectSubscriptions($order);
    foreach ($subscriptions as $subscription) {
      if ($subscription->getState()->value != 'active') {
        // The subscriptions are expected to be active, if one isn't, it
        // might have been canceled in the meantime.
        continue;
      }
      $subscription->setState($new_state_id);
      $subscription->save();
    }
  }

}
