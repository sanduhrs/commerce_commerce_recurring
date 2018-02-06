<?php

namespace Drupal\Tests\commerce_recurring\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_recurring\Entity\Subscription;

/**
 * Tests the subscription lifecycle.
 *
 * @group commerce_recurring
 */
class SubscriptionLifecycleTest extends RecurringKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // An order item type that doesn't need a purchasable entity.
    OrderItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();

    $order_type = OrderType::load('default');
    $order_type->setWorkflowId('order_default_validation');
    $order_type->save();
  }

  /**
   * Tests the initial order lifecycle.
   *
   * Placing an initial order should create subscriptions. Canceling the
   * initial order should cancel the previously created subscriptions.
   */
  public function testInitialLifecycle() {
    $first_order_item = OrderItem::create([
      'type' => 'test',
      'title' => 'I promise not to start a subscription',
      'unit_price' => [
        'number' => '10.00',
        'currency_code' => 'USD',
      ],
      'quantity' => 1,
    ]);
    $first_order_item->save();
    $second_order_item = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $this->variation,
      'unit_price' => [
        'number' => '2.00',
        'currency_code' => 'USD',
      ],
      'quantity' => '3',
    ]);
    $second_order_item->save();
    $initial_order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
      'uid' => $this->user,
      'order_items' => [$first_order_item, $second_order_item],
      'state' => 'draft',
      'payment_method' => $this->paymentMethod,
    ]);
    $initial_order->save();

    $subscriptions = Subscription::loadMultiple();
    $this->assertCount(0, $subscriptions);

    $workflow = $initial_order->getState()->getWorkflow();
    $initial_order->getState()->applyTransition($workflow->getTransition('place'));
    $initial_order->save();

    $subscriptions = Subscription::loadMultiple();
    $this->assertCount(1, $subscriptions);
    /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription */
    $subscription = reset($subscriptions);

    $this->assertEquals($this->store->id(), $subscription->getStoreId());
    $this->assertEquals($this->billingSchedule->id(), $subscription->getBillingSchedule()->id());
    $this->assertEquals($this->user->id(), $subscription->getCustomerId());
    $this->assertEquals($this->paymentMethod->id(), $subscription->getPaymentMethod()->id());
    $this->assertEquals($this->variation->id(), $subscription->getPurchasedEntityId());
    $this->assertEquals($this->variation->getOrderItemTitle(), $subscription->getTitle());
    $this->assertEquals('3', $subscription->getQuantity());
    $this->assertEquals($this->variation->getPrice(), $subscription->getUnitPrice());
    $this->assertEquals('active', $subscription->getState()->value);
    $this->assertEquals($initial_order->id(), $subscription->getInitialOrderId());

    // Confirm that a recurring order is present.
    $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $result = $order_storage->getQuery()
      ->condition('type', 'recurring')
      ->pager(1)
      ->execute();
    $this->assertNotEmpty($result);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $order_storage->load(reset($result));
    $this->assertNotEmpty($order);
    // Confirm that the recurring order has an order item for the subscription.
    $order_items = $order->getItems();
    $this->assertCount(1, $order_items);
    $order_item = reset($order_items);
    $this->assertEquals($subscription->id(), $order_item->get('subscription')->target_id);

    // Test initial order cancellation.
    $initial_order->getState()->applyTransition($workflow->getTransition('cancel'));
    $initial_order->save();
    $subscription = $this->reloadEntity($subscription);
    $this->assertEquals('canceled', $subscription->getState()->value);
  }

}
