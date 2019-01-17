<?php

namespace Drupal\Tests\commerce_recurring\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\commerce_price\Price;
use Drupal\commerce_recurring\Entity\Subscription;

/**
 * @coversDefaultClass \Drupal\commerce_recurring\Cron
 * @group commerce_recurring
 */
class CronTest extends RecurringKernelTestBase {

  /**
   * The recurring order manager.
   *
   * @var \Drupal\commerce_recurring\RecurringOrderManagerInterface
   */
  protected $recurringOrderManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->recurringOrderManager = $this->container->get('commerce_recurring.order_manager');
  }

  /**
   * Test run for recurring orders.
   *
   * @covers ::run
   */
  public function testRun() {
    $first_subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'payment_method' => $this->paymentMethod,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'active',
      'starts' => strtotime('2017-02-24 17:00'),
    ]);
    $first_subscription->save();
    $this->recurringOrderManager->ensureOrder($first_subscription);

    $second_subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'payment_method' => $this->paymentMethod,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'active',
      'starts' => strtotime('2017-02-25 17:00:00'),
    ]);
    $second_subscription->save();
    $this->recurringOrderManager->ensureOrder($second_subscription);

    // Rewind time to the end of the first subscription.
    // Confirm that only the first subscription's order was queued.
    $this->rewindTime(strtotime('2017-02-24 19:00'));
    $this->container->get('commerce_recurring.cron')->run();

    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = Queue::load('commerce_recurring');
    $counts = array_filter($queue->getBackend()->countJobs());
    $this->assertEquals([Job::STATE_QUEUED => 2], $counts);

    $job1 = $queue->getBackend()->claimJob();
    $job2 = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['order_id' => '1'], $job1->getPayload());
    $this->assertEquals('commerce_recurring_order_close', $job1->getType());
    $this->assertArraySubset(['order_id' => '1'], $job2->getPayload());
    $this->assertEquals('commerce_recurring_order_renew', $job2->getType());
  }

  /**
   * Test run for pending subscriptions.
   *
   * @covers ::run
   */
  public function testPendingSubscriptionsRun() {
    // Pending subscription should be activated.
    $first_subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'payment_method' => $this->paymentMethod,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'pending',
      'starts' => strtotime('2017-02-24 17:00'),
    ]);
    $first_subscription->save();

    // Canceled subscription should not be activated.
    $second_subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'payment_method' => $this->paymentMethod,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'canceled',
      'starts' => strtotime('2017-02-24 17:00:00'),
    ]);
    $second_subscription->save();

    // Confirm that only the first subscription was queued.
    $this->container->get('commerce_recurring.cron')->run();

    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = Queue::load('commerce_recurring');
    $counts = array_filter($queue->getBackend()->countJobs());
    $this->assertEquals([Job::STATE_QUEUED => 1], $counts);

    $job = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['subscription_id' => '1'], $job->getPayload());
    $this->assertEquals('commerce_subscription_activate', $job->getType());
  }

}
