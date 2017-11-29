<?php

namespace Drupal\commerce_recurring;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_recurring\Entity\SubscriptionInterface;

/**
 * Manages recurring orders.
 */
interface RecurringOrderManagerInterface {

  /**
   * Ensures a recurring order for the given subscription.
   *
   * @param \Drupal\commerce_recurring\Entity\SubscriptionInterface $subscription
   *   The subscription.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The recurring order.
   */
  public function ensureOrder(SubscriptionInterface $subscription);

  /**
   * Refreshes the given recurring order.
   *
   * Each subscription's order items will be rebuilt based on the most
   * recent chargers.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The recurring order.
   */
  public function refreshOrder(OrderInterface $order);

  /**
   * Closes the given recurring order.
   *
   * A payment will be created and the order will be placed.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The recurring order.
   *
   * @throws \Drupal\commerce_payment\Exception\HardDeclineException
   *   Thrown when no payment method was found.
   * @throws \Drupal\commerce_payment\Exception\PaymentGatewayException
   *   Thrown when the transaction fails for any reason. This includes
   *   child exceptions such as HardDeclineException and SoftDeclineException.
   */
  public function closeOrder(OrderInterface $order);

  /**
   * Renews the given recurring order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The recurring order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The next recurring order, or NULL if none remain.
   */
  public function renewOrder(OrderInterface $order);

  /**
   * Collects all subscriptions that belong to an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_recurring\Entity\SubscriptionInterface[]
   *   The subscriptions.
   */
  public function collectSubscriptions(OrderInterface $order);

}
