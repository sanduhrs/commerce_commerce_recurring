<?php

namespace Drupal\Tests\commerce_recurring\Kernel;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Job;
use Drupal\commerce_price\Price;
use Drupal\commerce_recurring\Entity\BillingScheduleInterface;
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
    $first_order = $this->recurringOrderManager->startRecurring($first_subscription);
    // Schedule a cancellation.
    $first_subscription->cancel()->save();

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
    $this->recurringOrderManager->startRecurring($second_subscription);

    // Rewind time to the end of the first subscription.
    // Confirm that only the first subscription's order was queued.
    $this->rewindTime(strtotime('2017-02-24 19:00'));
    $this->container->get('commerce_recurring.cron')->run();

    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = Queue::load('commerce_recurring');
    $counts = array_filter($queue->getBackend()->countJobs());
    // Ensure that no renewal is scheduled when scheduling the subscription for
    // cancellation, the scheduled changes are applied at the end of the billing
    // period, right before attempting to queue the jobs.
    $this->assertEquals([Job::STATE_QUEUED => 1], $counts);

    $job1 = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['order_id' => $first_order->id()], $job1->getPayload());
    $this->assertEquals('commerce_recurring_order_close', $job1->getType());

    $first_order->delete();
    // Remove all the items from the queue.
    $queue->getBackend()->deleteQueue();
    // Re-activate the subscription, with a prepaid billing schedule, and
    // schedule it for cancellation right after the recurring order is created,
    // and ensure the recurring order is not queued for renewal/closing.
    $this->billingSchedule->setBillingType(BillingScheduleInterface::BILLING_TYPE_PREPAID);
    $this->billingSchedule->save();
    $first_subscription->setBillingSchedule($this->billingSchedule);
    $first_subscription->setState('active');
    $first_subscription->save();
    $this->recurringOrderManager->startRecurring($first_subscription);
    $first_subscription->cancel()->save();
    $this->container->get('commerce_recurring.cron')->run();
    $counts = array_filter($queue->getBackend()->countJobs());
    $this->assertEmpty($counts);

    // Assert that 2 jobs are correctly queued, if the subscription isn't
    // scheduled for cancellation.
    $first_subscription->setState('active')->save();
    $recurring_order = $this->recurringOrderManager->startRecurring($first_subscription);
    $this->container->get('commerce_recurring.cron')->run();
    $counts = array_filter($queue->getBackend()->countJobs());
    $this->assertEquals([Job::STATE_QUEUED => 2], $counts);

    $job1 = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['order_id' => $recurring_order->id()], $job1->getPayload());
    $this->assertEquals('commerce_recurring_order_close', $job1->getType());
    $job2 = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['order_id' => $recurring_order->id()], $job2->getPayload());
    $this->assertEquals('commerce_recurring_order_renew', $job2->getType());
  }

  /**
   * Test run for activating subscriptions.
   *
   * @covers ::run
   */
  public function testSubscriptionActivateRun() {
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

    // An ending trial subscription should be activated.
    $trial_subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'payment_method' => $this->paymentMethod,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'trial',
      'trial_starts' => strtotime('2017-02-24 17:00'),
      'trial_ends' => strtotime('2017-02-24 18:00'),
      'starts' => strtotime('2017-02-24 18:00'),
    ]);
    $trial_subscription->save();

    // An ongoing trial subscription shouldn't be activated.
    $trial_subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'payment_method' => $this->paymentMethod,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'trial',
      'trial_starts' => strtotime('2017-02-24 17:00'),
      'trial_ends' => strtotime('2017-02-24 20:00'),
    ]);
    $trial_subscription->save();

    $this->rewindTime(strtotime('2017-02-24 19:00'));
    // Confirm that only the first subscription was queued.
    $this->container->get('commerce_recurring.cron')->run();

    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = Queue::load('commerce_recurring');
    $counts = array_filter($queue->getBackend()->countJobs());
    $this->assertEquals([Job::STATE_QUEUED => 2], $counts);

    $job = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['subscription_id' => '1'], $job->getPayload());
    $job = $queue->getBackend()->claimJob();
    $this->assertArraySubset(['subscription_id' => '3'], $job->getPayload());
    $this->assertEquals('commerce_subscription_activate', $job->getType());
  }

}
