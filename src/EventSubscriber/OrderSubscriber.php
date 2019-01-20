<?php

namespace Drupal\commerce_recurring\EventSubscriber;

use Drupal\commerce_recurring\RecurringOrderManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface {

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
   * Constructs a new OrderSubscriber object.
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
  public static function getSubscribedEvents() {
    $events['commerce_order.place.pre_transition'] = 'onPlace';
    $events['commerce_order.cancel.pre_transition'] = 'onCancel';
    return $events;
  }

  /**
   * Creates subscriptions when the initial order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_recurring\SubscriptionStorageInterface $subscription_storage */
    $subscription_storage = $this->entityTypeManager->getStorage('commerce_subscription');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if ($order->bundle() == 'recurring') {
      return;
    }
    $payment_method = $order->get('payment_method')->entity;
    if (empty($payment_method)) {
      return;
    }
    $start_date = DrupalDateTime::createFromTimestamp($this->time->getRequestTime());

    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity || !$purchased_entity->hasField('subscription_type')) {
        continue;
      }
      $subscription_type_item = $purchased_entity->get('subscription_type');
      $billing_schedule_item = $purchased_entity->get('billing_schedule');
      if ($subscription_type_item->isEmpty() || $billing_schedule_item->isEmpty()) {
        continue;
      }
      /** @var \Drupal\commerce_recurring\Entity\BillingScheduleInterface $billing_schedule */
      $billing_schedule = $billing_schedule_item->entity;
      $subscription = $subscription_storage->createFromOrderItem($order_item, [
        'type' => $subscription_type_item->target_plugin_id,
        'billing_schedule' => $billing_schedule,
        'payment_method' => $payment_method,
      ]);
      if ($billing_schedule->getPlugin()->allowTrials()) {
        $trial_period = $billing_schedule->getPlugin()->generateTrialPeriod($start_date);
        $trial_start = $trial_period->getStartDate()->getTimestamp();
        $trial_end = $trial_period->getEndDate()->getTimestamp();
        $subscription->setState('trial');
        $subscription->setTrialStartTime($trial_start);
        $subscription->setTrialEndTime($trial_end);
        $subscription->setStartTime($trial_end);
        $subscription->save();
      }
      else {
        $subscription->setState('active');
        $subscription->save();
        $this->recurringOrderManager->ensureOrder($subscription);
      }
    }
  }

  /**
   * Cancels subscriptions when the initial order is canceled.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onCancel(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_recurring\SubscriptionStorageInterface $subscription_storage */
    $subscription_storage = $this->entityTypeManager->getStorage('commerce_subscription');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if ($order->bundle() == 'recurring') {
      return;
    }

    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity || !$purchased_entity->hasField('subscription_type')) {
        continue;
      }
      $subscription_type_item = $purchased_entity->get('subscription_type');
      $billing_schedule_item = $purchased_entity->get('billing_schedule');
      if ($subscription_type_item->isEmpty() || $billing_schedule_item->isEmpty()) {
        continue;
      }

      /** @var \Drupal\commerce_recurring\Entity\SubscriptionInterface[] $subscriptions */
      $subscriptions = $subscription_storage->loadByProperties([
        'initial_order' => $order->id(),
        'state' => 'active',
      ]);
      foreach ($subscriptions as $subscription) {
        $subscription->setState('canceled');
        $subscription->save();
      }
    }
  }

}
