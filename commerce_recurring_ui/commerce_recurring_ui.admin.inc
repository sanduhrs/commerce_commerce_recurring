<?php

/**
 * Admin UI for recurring entities.
 */

/**
 * Menu callback: display an overview of available types.
 */
function commerce_recurring_ui_types_overview() {
  $header = array(
    t('Entity Type'),
    t('Operations'),
  );

  $rows = array();

  // Loop through all defined recurring entity types.
  foreach (commerce_recurring_types() as $type) {
    $recurring_entity_type = ucfirst(strtr($type, '_', ' '));
    // Build the operation links for the current recurring entity type.
    $links = menu_contextual_links('commerce-recurring-entity-type', 'admin/commerce/recurring-entities/types', array(strtr($type, array('_' => '-'))));

    // Add the recurring entity type's row to the table's rows array.
    $rows[] = array(
      t('@recurring_entity_type', array('@recurring_entity_type' => $recurring_entity_type)),
      theme('links', array('links' => $links, 'attributes' => array('class' => 'links inline operations'))),
    );
  }

  // If no recurring entity types are defined...
  if (empty($rows)) {
    // Add a standard empty row with a link to add a new recurring
    // entity type.
    $rows[] = array(
      array(
        'data' => t('There are no recurring entity types yet.'),
        'colspan' => 2,
      )
    );
  }

  return theme('table', array('header' => $header, 'rows' => $rows));
}

/**
 * Redirects a recurring entity type URL to its fields management page.
 */
function commerce_recurring_ui_entity_type_redirect($type) {
  drupal_goto('admin/commerce/recurring-entities/types/' . strtr($type, '_', '-') . '/fields');
}

/**
* Generates the recurring entity editing form.
*/
function commerce_recurring_edit_product_form($form, &$form_state, $recurring_entity, $op = 'edit') {
  // Alter the date properties.
  $form['start_date'] = array(
    '#type' => 'date_popup',
    '#title' => t('Start date'),
    '#default_value' => !empty($recurring_entity->start_date) ? date('Y-m-d H:i:s', $recurring_entity->start_date) : NULL,
    '#weight' => -3,
    '#required' => TRUE,
    '#date_year_range' => '-0:+10',
    '#date_format' => 'Y-m-d',
  );
  $form['due_date'] = array(
    '#type' => 'date_popup',
    '#title' => t('Due date'),
    '#default_value' => !empty($recurring_entity->due_date) ? date('Y-m-d H:i:s', $recurring_entity->due_date) : NULL,
    '#weight' => -2,
    '#required' => TRUE,
    '#date_year_range' => '-1:+10',
    '#date_format' => 'Y-m-d',
  );
  $form['end_date'] = array(
    '#type' => 'date_popup',
    '#title' => t('End date'),
    '#default_value' => !empty($recurring_entity->end_date) ? date('Y-m-d H:i:s', $recurring_entity->end_date) : NULL,
    '#weight' => -1,
    '#date_format' => 'Y-m-d',
  );

  // User.
  $owner = user_load($recurring_entity->uid);
  $form['uid'] = array(
    '#title' => t('Recurring entity user'),
    '#type' => 'textfield',
    '#autocomplete_path' => 'user/autocomplete',
    '#default_value' => $owner->name,
    '#required' => TRUE,
  );

  // Add the field related form elements.
  $form_state['commerce_recurring'] = $recurring_entity;
  field_attach_form('commerce_recurring', $recurring_entity, $form, $form_state);

  // Status property.
  $form['status'] = array(
    '#type' => 'radios',
    '#title' => t('Status'),
    '#options' => array(0 => t('Disabled'), 1 => t('Enabled')),
    '#default_value' => $recurring_entity->status,
    '#weight' => 35,
    '#required' => TRUE,
  );

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save recurring entity'),
    '#weight' => 40,
  );
  return $form;
}

/**
 * Form API validate callback for the recurring form.
 */
function commerce_recurring_edit_product_form_validate(&$form, &$form_state) {
  $dates = array('start_date', 'due_date', 'end_date');
  // Ensure that the dates stored are timestamps.
  foreach ($dates as $date) {
    if (is_array($form_state['values'][$date])) {
      if (!empty($form_state['values'][$date]['date'])) {
        form_set_value($form[$date], strtotime($form_state['values'][$date]['date']), $form_state);
      }
      else {
        form_set_error($date, t('Date can\'t be null'));
      }
    }
    else {
      form_set_value($form[$date], strtotime($form_state['values'][$date]), $form_state);
    }
  }

  if ($owner = user_load_by_name($form_state['values']['uid'])) {
    form_set_value($form['uid'], $owner->uid, $form_state);
  }
  else {
    form_set_error('uid', t('The specified user doesn\'t exist'));
  }
}

/**
* Form API submit callback for the recurring form.
*/
function commerce_recurring_edit_product_form_submit(&$form, &$form_state) {
  $recurring_entity = entity_ui_form_submit_build_entity($form, $form_state);
  // Save and go back.
  $recurring_entity->save();
  $form_state['redirect'] = 'admin/commerce/recurring-entities';
}

/**
 * Form callback: confirmation form for deleting a recurring entity.
 *
 * @param $recurring_entity
 *   The recurring entity object to be deleted.
 *
 * @see confirm_form()
 */
function commerce_recurring_ui_delete_confirm_form($form, &$form_state, $recurring_entity) {
  $form_state['recurring_entity'] = $recurring_entity;

  $form['#submit'][] = 'commerce_recurring_entity_delete_form_submit';

  $form = confirm_form($form,
    t('Are you sure you want to delete this recurring entity?'),
    '',
    '<p>' . t('Deleting this recurring entity cannot be undone.') . '</p>',
    t('Delete'),
    t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Submit callback for commerce_recurring_ui_delete_confirm_form().
 */
function commerce_recurring_entity_delete_form_submit($form, &$form_state) {
  $recurring_entity = $form_state['recurring_entity'];

  if ($recurring_entity->delete()) {
    drupal_set_message(t('Commerce recurring entity has been deleted.'));
  }
  else {
    drupal_set_message(t('Commerce recurring entity could not be deleted.'), 'error');
  }
  $form_state['redirect'] = 'admin/commerce/recurring-entities';
}
