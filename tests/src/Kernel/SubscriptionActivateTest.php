<?php

namespace Drupal\Tests\commerce_recurring\Kernel;

use Drupal\advancedqueue\Job;
use Drupal\commerce_price\Price;
use Drupal\commerce_recurring\Entity\Subscription;

/**
 * @coversDefaultClass \Drupal\commerce_recurring\Plugin\AdvancedQueue\JobType\SubscriptionActivate
 * @group commerce_recurring
 */
class SubscriptionActivateTest extends RecurringKernelTestBase {

  /**
   * The recurring order manager.
   *
   * @var \Drupal\commerce_recurring\RecurringOrderManagerInterface
   */
  protected $recurringOrderManager;

  /**
   * The used queue.
   *
   * @var \Drupal\advancedqueue\Entity\QueueInterface
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->recurringOrderManager = $this->container->get('commerce_recurring.order_manager');
    /** @var \Drupal\Core\Entity\EntityStorageInterface $queue_storage */
    $queue_storage = $this->container->get('entity_type.manager')->getStorage('advancedqueue_queue');
    $this->queue = $queue_storage->load('commerce_recurring');
  }

  /**
   * @covers ::process
   */
  public function testActivate() {
    // A subscription that's already active.
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'active',
      'starts' => strtotime('2017-02-24 17:00'),
    ]);
    $subscription->save();

    $job = Job::create('commerce_subscription_activate', [
      'subscription_id' => $subscription->id(),
    ]);
    $this->queue->enqueueJob($job);

    $job = $this->queue->getBackend()->claimJob();
    /** @var \Drupal\advancedqueue\ProcessorInterface $processor */
    $processor = \Drupal::service('advancedqueue.processor');
    $result = $processor->processJob($job, $this->queue);

    // Confirm that the job result is correct.
    $this->assertEquals(Job::STATE_FAILURE, $result->getState());

    // A subscription that's pending.
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('2', 'USD'),
      'state' => 'pending',
      'starts' => strtotime('2017-02-24 17:00'),
    ]);
    $subscription->save();

    $job = Job::create('commerce_subscription_activate', [
      'subscription_id' => $subscription->id(),
    ]);
    $this->queue->enqueueJob($job);
    $job = $this->queue->getBackend()->claimJob();

    $result = $processor->processJob($job, $this->queue);
    // Confirm that the job result is correct.
    $this->assertEquals(Job::STATE_SUCCESS, $result->getState());

    // Confirm that the subscription was activated.
    $subscription = $this->reloadEntity($subscription);
    $this->assertEquals('active', $subscription->getState()->value);

    // Confirm that recurring order was created.
    $this->assertNotEmpty($subscription->getOrders());
    $order = $subscription->getOrders()[0];
    $this->assertEquals(new Price('2', 'USD'), $order->getTotalPrice());

    // Test activating a trial subscription.
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => $this->user,
      'purchased_entity' => $this->variation,
      'title' => $this->variation->getOrderItemTitle(),
      'unit_price' => new Price('3', 'USD'),
      'state' => 'trial',
      'trial_starts' => strtotime('2017-02-24 17:00'),
      'starts' => strtotime('2017-02-24 18:00'),
    ]);
    $subscription->save();

    $job = Job::create('commerce_subscription_activate', [
      'subscription_id' => $subscription->id(),
    ]);

    $this->queue->enqueueJob($job);
    $job = $this->queue->getBackend()->claimJob();

    /** @var \Drupal\advancedqueue\ProcessorInterface $processor */
    $result = $processor->processJob($job, $this->queue);
    // Confirm that the job result is correct.
    $this->assertEquals(Job::STATE_SUCCESS, $result->getState());

    // Confirm that the subscription was activated.
    $subscription = $this->reloadEntity($subscription);
    $this->assertEquals('active', $subscription->getState()->value);

    // Confirm that a recurring order was created.
    $this->assertNotEmpty($subscription->getOrders());
    $order = $subscription->getOrders()[0];
    $this->assertEquals(new Price('3', 'USD'), $order->getTotalPrice());
  }

}
