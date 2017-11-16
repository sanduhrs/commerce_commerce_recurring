<?php

namespace Drupal\Tests\commerce_recurring\Plugin\Commerce\BillingSchedule;

use Drupal\commerce_recurring\Plugin\Commerce\BillingSchedule\Fixed;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the fixed billing schedule.
 *
 * @coversDefaultClass \Drupal\commerce_recurring\Plugin\Commerce\BillingSchedule\Fixed
 * @group commerce_recurring
 */
class FixedTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce',
    'commerce_price',
    'commerce_recurring',
  ];

  /**
   * @covers ::generateFirstBillingPeriod
   * @covers ::generateNextBillingPeriod
   */
  public function testGenerate() {
    $plugin = new Fixed([
      'number' => '2',
      'unit' => 'hour',
    ], '', []);
    $start_date = new DrupalDateTime('2017-03-16 10:22:30');
    $billing_period = $plugin->generateFirstBillingPeriod($start_date);
    $this->assertEquals(new DrupalDateTime('2017-03-16 10:00:00'), $billing_period->getStartDate());
    $this->assertEquals(new DrupalDateTime('2017-03-16 12:00:00'), $billing_period->getEndDate());
    $next_billing_period = $plugin->generateNextBillingPeriod($start_date, $billing_period);
    $this->assertEquals(new DrupalDateTime('2017-03-16 12:00:00'), $next_billing_period->getStartDate());
    $this->assertEquals(new DrupalDateTime('2017-03-16 14:00:00'), $next_billing_period->getEndDate());

    $plugin = new Fixed([
      'number' => '1',
      'unit' => 'month',
    ], '', []);
    $start_date = new DrupalDateTime('2017-03-16 10:22:30');
    $billing_period = $plugin->generateFirstBillingPeriod($start_date);
    $this->assertEquals(new DrupalDateTime('2017-03-01 00:00:00'), $billing_period->getStartDate());
    $this->assertEquals(new DrupalDateTime('2017-04-01 00:00:00'), $billing_period->getEndDate());
    $next_billing_period = $plugin->generateNextBillingPeriod($start_date, $billing_period);
    $this->assertEquals(new DrupalDateTime('2017-04-01 00:00:00'), $next_billing_period->getStartDate());
    $this->assertEquals(new DrupalDateTime('2017-05-01 00:00:00'), $next_billing_period->getEndDate());

    $plugin = new Fixed([
      'number' => '1',
      'unit' => 'year',
    ], '', []);
    $start_date = new DrupalDateTime('2017-03-16 10:22:30');
    $billing_period = $plugin->generateFirstBillingPeriod($start_date);
    $this->assertEquals(new DrupalDateTime('2017-01-01 00:00:00'), $billing_period->getStartDate());
    $this->assertEquals(new DrupalDateTime('2018-01-01 00:00:00'), $billing_period->getEndDate());
    $next_billing_period = $plugin->generateNextBillingPeriod($start_date, $billing_period);
    $this->assertEquals(new DrupalDateTime('2018-01-01 00:00:00'), $next_billing_period->getStartDate());
    $this->assertEquals(new DrupalDateTime('2019-01-01 00:00:00'), $next_billing_period->getEndDate());
  }

}
