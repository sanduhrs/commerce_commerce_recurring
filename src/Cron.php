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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
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
      ->execute();

    if (!$order_ids && !$subscription_ids) {
      return;
    }
    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $recurring_queue */
    $recurring_queue = $queue_storage->load('commerce_recurring');

    foreach ($order_ids as $order_id) {
      $close_job = Job::create('commerce_recurring_order_close', [
        'order_id' => $order_id,
      ]);
      $renew_job = Job::create('commerce_recurring_order_renew', [
        'order_id' => $order_id,
      ]);
      $recurring_queue->enqueueJob($close_job);
      $recurring_queue->enqueueJob($renew_job);
    }

    foreach ($subscription_ids as $subscription_id) {
      $activate_job = Job::create('commerce_subscription_activate', [
        'subscription_id' => $subscription_id,
      ]);
      $recurring_queue->enqueueJob($activate_job);
    }
  }

}
