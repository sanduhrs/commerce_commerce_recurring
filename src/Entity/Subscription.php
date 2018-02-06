<?php

namespace Drupal\commerce_recurring\Entity;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the subscription entity.
 *
 * @ContentEntityType(
 *   id = "commerce_subscription",
 *   label = @Translation("Subscription"),
 *   label_collection = @Translation("Subscriptions"),
 *   label_singular = @Translation("subscription"),
 *   label_plural = @Translation("subscriptions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count subscription",
 *     plural = "@count subscription",
 *   ),
 *   bundle_label = @Translation("Subscription type"),
 *   bundle_plugin_type = "commerce_subscription_type",
 *   handlers = {
 *     "event" = "Drupal\commerce_recurring\Event\SubscriptionEvent",
 *     "list_builder" = "Drupal\commerce_recurring\SubscriptionListBuilder",
 *     "storage" = "\Drupal\commerce_recurring\SubscriptionStorage",
 *     "form" = {
 *       "default" = "\Drupal\commerce_recurring\Form\SubscriptionForm",
 *       "edit" = "\Drupal\commerce_recurring\Form\SubscriptionForm",
 *       "delete" = "\Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "commerce_subscription",
 *   admin_permission = "administer commerce_subscription",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "subscription_id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/subscriptions/{commerce_subscription}",
 *     "add-page" = "/admin/commerce/subscriptions/add",
 *     "add-form" = "/admin/commerce/subscriptions/{type}/add",
 *     "edit-form" = "/admin/commerce/subscriptions/{commerce_subscription}/edit",
 *     "delete-form" = "/admin/commerce/subscriptions/{commerce_subscription}/delete",
 *     "collection" = "/admin/commerce/subscriptions",
 *   },
 * )
 */
class Subscription extends ContentEntityBase implements SubscriptionInterface {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return sprintf('#%s (%s)', $this->id(), $this->getTitle());
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    $subscription_type_manager = \Drupal::service('plugin.manager.commerce_subscription_type');
    return $subscription_type_manager->createInstance($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getStore() {
    return $this->get('store_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoreId() {
    return $this->get('store_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getBillingSchedule() {
    return $this->get('billing_schedule')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setBillingSchedule(BillingScheduleInterface $billing_schedule) {
    $this->set('billing_schedule', $billing_schedule);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomer() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomer(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setCustomerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethod() {
    return $this->get('payment_method')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethod(PaymentMethodInterface $payment_method) {
    $this->set('payment_method', $payment_method);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodId() {
    return $this->get('payment_method')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setPaymentMethodId($payment_method_id) {
    $this->set('payment_method', $payment_method_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPurchasedEntity() {
    return !$this->get('purchased_entity')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasedEntity() {
    return $this->get('purchased_entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasedEntityId() {
    return $this->get('purchased_entity')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setPurchasedEntity(PurchasableEntityInterface $purchased_entity) {
    $this->set('purchased_entity', $purchased_entity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantity() {
    return (string) $this->get('quantity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuantity($quantity) {
    $this->set('quantity', (string) $quantity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnitPrice() {
    if (!$this->get('unit_price')->isEmpty()) {
      return $this->get('unit_price')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUnitPrice(Price $unit_price) {
    $this->set('unit_price', $unit_price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getState() {
    return $this->get('state')->first();
  }

  /**
   * {@inheritdoc}
   */
  public function setState($state_id) {
    $this->set('state', $state_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialOrder() {
    return $this->get('initial_order')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setInitialOrder(OrderInterface $initial_order) {
    $this->set('initial_order', $initial_order);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialOrderId() {
    return $this->get('initial_order')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderIds() {
    $order_ids = [];
    foreach ($this->get('orders') as $field_item) {
      $order_ids[] = $field_item->target_id;
    }
    return $order_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrders() {
    return $this->get('orders')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function setOrders(array $orders) {
    $this->set('orders', $orders);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addOrder(OrderInterface $order) {
    if (!$this->hasOrder($order)) {
      $this->get('orders')->appendItem($order);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeOrder(OrderInterface $order) {
    $index = $this->getOrderIndex($order);
    if ($index !== FALSE) {
      $this->get('orders')->offsetUnset($index);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasOrder(OrderInterface $order) {
    return $this->getOrderIndex($order) !== FALSE;
  }

  /**
   * Gets the index of the given recurring order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The recurring order.
   *
   * @return int|bool
   *   The index of the given recurring order, or FALSE if not found.
   */
  protected function getOrderIndex(OrderInterface $order) {
    $values = $this->get('orders')->getValue();
    $order_ids = array_map(function ($value) {
      return $value['target_id'];
    }, $values);

    return array_search($order->id(), $order_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenewedTime() {
    return $this->get('renewed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRenewedTime($timestamp) {
    $this->set('renewed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartTime() {
    return $this->get('starts')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStartTime($timestamp) {
    $this->set('starts', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndTime() {
    return $this->get('ends')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEndTime($timestamp) {
    $this->set('ends', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate() {
    return DrupalDateTime::createFromTimestamp($this->getStartTime());
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate() {
    if ($end_time = $this->getEndTime()) {
      return DrupalDateTime::createFromTimestamp($end_time);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (['store_id', 'billing_schedule', 'uid', 'title', 'unit_price'] as $field) {
      if ($this->get($field)->isEmpty()) {
        throw new EntityMalformedException(sprintf('Required subscription field "%s" is empty.', $field));
      }
    }

    $state = $this->getState()->value;
    $original_state = isset($this->original) ? $this->original->getState()->value : '';
    if ($state === 'active' && $original_state !== 'active') {
      if (empty($this->getStartTime())) {
        $this->setStartTime(\Drupal::time()->getRequestTime());
      }
    }
    elseif ($state == 'expired' && $original_state != 'expired') {
      $this->getType()->onSubscriptionExpire($this);
    }
    elseif ($state == 'canceled' && $original_state != 'canceled') {
      $this->getType()->onSubscriptionCancel($this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['store_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Store'))
      ->setDescription(t('The store to which the subscription belongs.'))
      ->setCardinality(1)
      ->setRequired(TRUE)
      ->setSetting('target_type', 'commerce_store')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'commerce_entity_select',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['billing_schedule'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Billing schedule'))
      ->setDescription(t('The billing schedule.'))
      ->setSetting('target_type', 'commerce_billing_schedule')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Customer'))
      ->setDescription(t('The subscribed customer.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['payment_method'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Payment method'))
      ->setDescription(t('The payment method.'))
      ->setSetting('target_type', 'commerce_payment_method')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setReadOnly(TRUE);

    $fields['purchased_entity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Purchased entity'))
      ->setDescription(t('The purchased entity.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -1,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The subscription title.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 512,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['quantity'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('The subscription quantity.'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('min', 0)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['unit_price'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Unit price'))
      ->setDescription(t('The subscription unit price.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_price_default',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['state'] = BaseFieldDefinition::create('state')
      ->setLabel(t('State'))
      ->setDescription(t('The subscription state.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setSetting('workflow', 'subscription_default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['initial_order'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Initial order'))
      ->setDescription(t('The non-recurring order which started the subscription.'))
      ->setSetting('target_type', 'commerce_order')
      ->setSetting('handler', 'default')
      ->setSetting('display_description', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['orders'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Orders'))
      ->setDescription(t('The recurring orders.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'commerce_order')
      ->setSetting('handler', 'default');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the subscription was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ]);

    $fields['renewed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Renewed'))
      ->setDescription(t('The time when the subscription was last renewed.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ]);

    $fields['starts'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Starts'))
      ->setDescription(t('The time when the subscription starts.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['ends'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Ends'))
      ->setDescription(t('The time when the subscription ends.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface $subscription_type */
    $subscription_type_manager = \Drupal::service('plugin.manager.commerce_subscription_type');
    $subscription_type = $subscription_type_manager->createInstance($bundle);
    $purchasable_entity_type = $subscription_type->getPurchasableEntityTypeId();
    $fields = [];
    /** @var \Drupal\Core\Field\BaseFieldDefinition $original */
    $original = $base_field_definitions['purchased_entity'];
    // The field definition is recreated instead of cloned to get around
    // core issue #2346329 (fixed in 8.5.x but not 8.4.x).
    $fields['purchased_entity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel($original->getLabel())
      ->setDescription($original->getDescription())
      ->setConstraints($original->getConstraints())
      ->setDisplayOptions('view', $original->getDisplayOptions('view'))
      ->setDisplayOptions('form', $original->getDisplayOptions('form'))
      ->setDisplayConfigurable('view', $original->isDisplayConfigurable('view'))
      ->setDisplayConfigurable('form', $original->isDisplayConfigurable('form'))
      ->setRequired($original->isRequired());

    if ($purchasable_entity_type) {
      $fields['purchased_entity']->setSetting('target_type', $purchasable_entity_type);
    }
    else {
      // This order item type won't reference a purchasable entity. The field
      // can't be removed here, or converted to a configurable one, so it's
      // hidden instead. https://www.drupal.org/node/2346347#comment-10254087.
      $fields['purchased_entity']->setRequired(FALSE);
      $fields['purchased_entity']->setDisplayOptions('form', [
        'type' => 'hidden',
      ]);
      $fields['purchased_entity']->setDisplayConfigurable('form', FALSE);
      $fields['purchased_entity']->setDisplayConfigurable('view', FALSE);
      $fields['purchased_entity']->setReadOnly(TRUE);
    }

    return $fields;
  }

}
