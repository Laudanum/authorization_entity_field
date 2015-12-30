<?php

/**
 * @file
 * Contains \Drupal\authorization_entity_field\Plugin\authorization\consumer\EntityFieldConsumer.
 */

namespace Drupal\authorization_entity_field\Plugin\authorization\consumer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;

use Drupal\authorization\Consumer\ConsumerPluginBase;
/**
 * @AuthorizationConsumer(
 *   id = "authorization_entity_field",
 *   label = @Translation("Entity Field"),
 *   description = @Translation("Add users to an entity's field.")
 * )
 */
class EntityFieldConsumer extends ConsumerPluginBase {

  public function buildRowForm(array $form, FormStateInterface $form_state, $index) {
    $row = array();
    // Gets values from the form_state or from the saved entity
    $mappings = $form_state->getValue('mappings')[$index]['consumer_mappings'] ? $form_state->getValue('mappings')[$index]['consumer_mappings'] : $this->configuration['profile']->getConsumerMappings()[$index];
    // @TODO select an entity and then a field
    $entity_options = array();
    $bundle_options = array();
    $field_match_options = array();
    $field_add_options = array();
    $entities = \Drupal::entityManager()->getDefinitions();
    foreach ( $entities as $key=>$entity ) {
      if ( $entity->isSubclassOf('\Drupal\Core\Entity\ContentEntityInterface') ) {
        $entity_options[$key] = $entity->getLabel();
      }
    }
    ksort($entity_options);
    ksort($bundle_options);
    ksort($field_options);

    $entity_type = $mappings['entity'] ? $mappings['entity'] : 'node';
    // Populate bundle options
    if ( $entity_type ) {
      $bundles = \Drupal::entityManager()->getBundleInfo($entity_type);
      foreach ( $bundles as $key=>$bundle ) {
        $bundle_options[$key] = $bundle['label'];
      }
    }

    if ( $bundle = $mappings['bundle'] ) {
      $fields = \Drupal::entityManager()->getFieldDefinitions($entity_type, $bundle);
      // Only fields of entity reference type that permit user references.
      foreach ( $fields as $key=>$field ) {
        $field_match_options[$key] = $field->getLabel();
        if ( $field->getType() == 'entity_reference' && $field->getSettings()['target_type'] == 'user' ) {
          $field_add_options[$key] = $field->getLabel();
        }
      }
    }

    // @TODO an AJAX callback to get the bundles based on the entity
    $row['entity'] = array(
      '#type' => 'select',
      '#title' => t('Entity type'),
      '#options' => $entity_options,
      '#default_value' => $entity_type,
      '#required' => TRUE,
      '#description' => 'Choose the type of entity to match.',
      '#ajax' => array(
        'trigger_as' => array('name' => 'entityfieldconsumer_enity'),
        'callback' => array(get_class($this), 'buildAjaxEntityFieldConsumerRowForm'),
        'wrapper' => 'authorization-consumer-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    if ( $entity_type ) {
      // @TODO an AJAX callback to get the fields based on the bundle or entity
      $row['bundle'] = array(
        '#type' => 'select',
        '#title' => t('Bundle type'),
        '#options' => $bundle_options,
        '#default_value' => $mappings['bundle'],
        '#description' => 'Choose the type of bundle to match.',
        '#ajax' => array(
          'trigger_as' => array('name' => 'mappings[' . $index . '][consumer_mappings][bundle]'),
          'callback' => array(get_class($this), 'buildAjaxEntityFieldConsumerRowForm'),
          'wrapper' => 'edit-mappings',
          'method' => 'replace',
          'effect' => 'fade',
        ),
      );
    }

    if ( $mappings['bundle'] ) {
      $row['field_match'] = array(
        '#type' => 'select',
        '#title' => t('Match field'),
        '#options' => $field_match_options,
        '#default_value' => $mappings['field_match'],
        '#description' => 'Map the result to an entity\'s field. eg: the title of a node.',
      );
      $row['field_add'] = array(
        '#type' => 'select',
        '#title' => t('Add field'),
        '#options' => $field_add_options,
        '#default_value' => $mappings['field_add'],
        '#description' => 'Add the user to an entity\'s field. eg: the field_users entity reference field of a node.',
      );
    }

    return $row;
  }

  public static function buildAjaxEntityFieldConsumerRowForm(array $form, FormStateInterface $form_state) {
    return $form['mappings'];
  }

}
