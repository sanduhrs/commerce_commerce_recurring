<?php

namespace Drupal\commerce_recurring;

use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
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
    if (!$order_ids) {
      return;
    }
    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $recurring_queue */
    $recurring_queue = $queue_storage->load('commerce_recurring');
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $order_storage->loadMultiple($order_ids);

    foreach ($orders as $order) {
      $close_job = Job::create('commerce_recurring_order_close', [
        'order_id' => $order->id(),
      ]);
      $renew_job = Job::create('commerce_recurring_order_renew', [
        'order_id' => $order->id(),
      ]);
      $recurring_queue->enqueueJob($close_job);
      $recurring_queue->enqueueJob($renew_job);
    }
  }

}
