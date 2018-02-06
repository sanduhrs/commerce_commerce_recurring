<?php

namespace Drupal\Tests\commerce_recurring\Kernel\Entity;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_recurring\Entity\Subscription;
use Drupal\commerce_recurring\Plugin\Commerce\SubscriptionType\SubscriptionTypeInterface;
use Drupal\Tests\commerce_recurring\Kernel\RecurringKernelTestBase;

/**
 * Tests the subscription entity.
 *
 * @coversDefaultClass \Drupal\commerce_recurring\Entity\Subscription
 *
 * @group commerce_recurring
 */
class SubscriptionTest extends RecurringKernelTestBase {

  /**
   * @covers ::getType
   * @covers ::getStore
   * @covers ::getStoreId
   * @covers ::getBillingSchedule
   * @covers ::setBillingSchedule
   * @covers ::getCustomer
   * @covers ::setCustomer
   * @covers ::getCustomerId
   * @covers ::setCustomerId
   * @covers ::getPaymentMethod
   * @covers ::setPaymentMethod
   * @covers ::getPaymentMethodId
   * @covers ::getTitle
   * @covers ::setTitle
   * @covers ::getQuantity
   * @covers ::setQuantity
   * @covers ::getUnitPrice
   * @covers ::setUnitPrice
   * @covers ::getState
   * @covers ::setState
   * @covers ::getInitialOrder
   * @covers ::setInitialOrder
   * @covers ::getInitialOrderId
   * @covers ::getOrderIds
   * @covers ::getOrders
   * @covers ::setOrders
   * @covers ::addOrder
   * @covers ::removeOrder
   * @covers ::hasOrder
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getRenewedTime
   * @covers ::setRenewedTime
   * @covers ::getStartTime
   * @covers ::setStartTime
   * @covers ::getEndTime
   * @covers ::setEndTime
   */
  public function testSubscription() {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $initial_order */
    $initial_order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
    ]);
    $initial_order->save();
    $initial_order = $this->reloadEntity($initial_order);

    $subscription = Subscription::create([
      'type' => 'product_variation',
      'store_id' => $this->store->id(),
      'billing_schedule' => $this->billingSchedule,
      'uid' => 0,
      'payment_method' => $this->paymentMethod,
      'title' => 'My subscription',
      'purchased_entity' => $this->variation,
      'quantity' => 2,
      'unit_price' => new Price('2', 'USD'),
      'state' => 'pending',
      'created' => 1507642328,
      'starts' => 1507642328 + 10,
      'ends' => 1507642328 + 50,
    ]);
    $subscription->save();

    $subscription = Subscription::load($subscription->id());
    $this->assertInstanceOf(SubscriptionTypeInterface::class, $subscription->getType());
    $this->assertEquals('product_variation', $subscription->getType()->getPluginId());
    $this->assertEquals($this->store, $subscription->getStore());
    $this->assertEquals($this->store->id(), $subscription->getStoreId());

    $this->assertEquals($this->billingSchedule, $subscription->getBillingSchedule());

    $this->assertEquals($this->paymentMethod, $subscription->getPaymentMethod());
    $this->assertEquals($this->paymentMethod->id(), $subscription->getPaymentMethodId());

    $this->assertTrue($subscription->hasPurchasedEntity());
    $this->assertEquals($this->variation, $subscription->getPurchasedEntity());
    $this->assertEquals($this->variation->id(), $subscription->getPurchasedEntityId());

    $this->assertEquals('My subscription', $subscription->getTitle());
    $subscription->setTitle('My premium subscription');
    $this->assertEquals('My premium subscription', $subscription->getTitle());

    $this->assertEquals('2', $subscription->getQuantity());
    $subscription->setQuantity('3');
    $this->assertEquals('3', $subscription->getQuantity());

    $this->assertEquals(new Price('2', 'USD'), $subscription->getUnitPrice());
    $subscription->setUnitPrice(new Price('3', 'USD'));
    $this->assertEquals(new Price('3', 'USD'), $subscription->getUnitPrice());

    $this->assertEquals('pending', $subscription->getState()->value);
    $subscription->setState('expired');
    $this->assertEquals('expired', $subscription->getState()->value);

    $this->assertNull($subscription->getInitialOrder());
    $subscription->setInitialOrder($initial_order);
    $this->assertEquals($initial_order, $subscription->getInitialOrder());
    $this->assertEquals($initial_order->id(), $subscription->getInitialOrderId());

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'recurring',
      'store_id' => $this->store,
    ]);
    $order->save();
    $order = $this->reloadEntity($order);

    $this->assertEquals([], $subscription->getOrderIds());
    $this->assertEquals([], $subscription->getOrders());
    $subscription->setOrders([$order]);
    $this->assertEquals([$order->id()], $subscription->getOrderIds());
    $this->assertEquals([$order], $subscription->getOrders());
    $this->assertTrue($subscription->hasOrder($order));
    $subscription->removeOrder($order);
    $this->assertEquals([], $subscription->getOrderIds());
    $this->assertEquals([], $subscription->getOrders());
    $this->assertFalse($subscription->hasOrder($order));
    $subscription->addOrder($order);
    $this->assertEquals([$order->id()], $subscription->getOrderIds());
    $this->assertEquals([$order], $subscription->getOrders());
    $this->assertTrue($subscription->hasOrder($order));

    $this->assertEquals(1507642328, $subscription->getCreatedTime());
    $subscription->setCreatedTime(1508002101);
    $this->assertEquals(1508002101, $subscription->getCreatedTime());

    $this->assertEquals(0, $subscription->getRenewedTime());
    $subscription->setRenewedTime(123456);
    $this->assertEquals(123456, $subscription->getRenewedTime());

    $this->assertEquals(1507642328 + 10, $subscription->getStartTime());
    $subscription->setStartTime(1508002120);
    $this->assertEquals(1508002120, $subscription->getStartTime());

    $this->assertEquals(1507642328 + 50, $subscription->getEndTime());
    $subscription->setEndTime(1508002920);
    $this->assertEquals(1508002920, $subscription->getEndTime());
  }

}
