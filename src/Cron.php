<?php

namespace Drupal\commerce_recurring;

use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Default cron implementation.
 */
class Cron implements CronInterface {

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
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_recurring\RecurringOrderManagerInterface $recurring_order_manager
   *   The recurring order manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RecurringOrderManagerInterface $recurring_order_manager, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->recurringOrderManager = $recurring_order_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $order_ids = $order_storage->getQuery()
      ->condition('type', 'recurring')
      ->condition('state', 'draft')
      ->condition('billing_period.ends', $this->time->getRequestTime(), '<=')
      ->accessCheck(FALSE)
      ->execute();

    $subscription_storage = $this->entityTypeManager->getStorage('commerce_subscription');
    $subscription_ids = $subscription_storage->getQuery()
      ->condition('state', ['pending', 'trial'], 'IN')
      ->condition('starts', $this->time->getRequestTime(), '<=')
      ->accessCheck(FALSE)
      ->execute();

    if (!$order_ids && !$subscription_ids) {
      return;
    }
    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $recurring_queue */
    $recurring_queue = $queue_storage->load('commerce_recurring');
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $order_storage->loadMultiple($order_ids);

    foreach ($orders as $order) {
      $subscriptions = $this->recurringOrderManager->collectSubscriptions($order);
      if (!$subscriptions) {
        // The recurring order is malformed. The referenced subscription
        // might have been deleted manually.
        $order->set('state', 'canceled');
        $order->save();
        continue;
      }

      $subscription = reset($subscriptions);
      if ($subscription->hasScheduledChanges()) {
        $previous_state = $subscription->getState()->getId();
        $subscription->applyScheduledChanges();
        $subscription->save();
        $new_state = $subscription->getState()->getId();
        // Don't try to activate a trial that was just canceled.
        if ($previous_state == 'trial' && $previous_state != $new_state) {
          if (in_array($subscription->id(), $subscription_ids)) {
            $subscription_ids = array_diff($subscription_ids, [$subscription->id()]);
          }
        }
      }
      // If the subscription was scheduled for cancellation, applying the
      // scheduled changes has resulted in both the subscription and its
      // recurring order being canceled.
      // Canceled orders are considered closed, and don't need to be charged.
      if ($order->getState()->getId() == 'draft') {
        $close_job = Job::create('commerce_recurring_order_close', [
          'order_id' => $order->id(),
        ]);
        $recurring_queue->enqueueJob($close_job);
      }
      // Recurring orders are renewed only if their subscription is active.
      if ($subscription->getState()->getId() == 'active') {
        $renew_job = Job::create('commerce_recurring_order_renew', [
          'order_id' => $order->id(),
        ]);
        $recurring_queue->enqueueJob($renew_job);
      }
    }

    foreach ($subscription_ids as $subscription_id) {
      $activate_job = Job::create('commerce_subscription_activate', [
        'subscription_id' => $subscription_id,
      ]);
      $recurring_queue->enqueueJob($activate_job);
    }
  }

}
