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

  public $allowConsumerTargetCreation = TRUE;

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
        '#description' => 'Map the result to an entity\'s field. eg: the title of a node or a custom field like <code>ldap_dn</code>.',
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

  /**
   * extends revokeSingleAuthorization()
   * {@inheritdoc}
   */
  public function revokeSingleAuthorization(&$user, $op, $incoming, $consumer_mapping, &$user_auth_data, $user_save=FALSE, $reset=FALSE) {
  }

  /**
   * extends grantSingleAuthorization()
   * {@inheritdoc}
   */
  public function grantSingleAuthorization(&$user, $op, $incoming, $consumer_mapping, &$user_auth_data, $user_save=FALSE, $reset=FALSE, $create=FALSE) {
    $match = array_shift($incoming);

    $target_definition = array(
      'entity_type' => $consumer_mapping['entity'],
      'type'        => $consumer_mapping['bundle'],
      'match'       => $match,
      'field_match' => $consumer_mapping['field_match'],
      'field_add'   => $consumer_mapping['field_add'],
      $consumer_mapping['field_match'] => $match,
    );

    // Load or create target.
    $entity = $this->getConsumerTarget($target_definition);
    if ( ! $entity && $create ) {
      $entity = $this->createConsumerTarget($this->id, $target_definition);
    }

    // Find a $field in $bundle matching $match
    if ( $entity ) {
      // @TODO decide if the field is an entity reference type
      $field = $entity->get($target_definition['field_add']);
      // Required as we don't have appendItem/removeItem
      $list = $field->getValue();

      // Is the user already attached?
      $found = FALSE;
      foreach ( $list as $index => $value ) {
        if ( $value['target_id'] == $user->id() ) {
          $found = TRUE;
          break;
        }
      }
      if ( ! $found ) {
        $list[] = array('target_id' => $user->id());

        $field->setValue($list);
        $entity->save();
      }
    }
  }

  /**
   * extends getConsumerTarget()
   * {@inheritdoc}
   */
  public function getConsumerTarget($target_definition) {
    $query = \Drupal::entityQuery($target_definition['entity_type'])
      ->condition('type', $target_definition['type'])
      ->condition($target_definition['field_match'] . '.value', $target_definition['match'])
      ;
    $ids = $query->execute();

    if ( $id = array_shift($ids) ) {
      return entity_load($target_definition['entity_type'], $id);
    }
  }

  /**
   * extends createConsumerTarget()
   * {@inheritdoc}
   */
  public function createConsumerTarget($consumer_id, $consumer) {
    // @TODO Don't create duplicates.
    // @TODO If the field_match isn't a string type then nothing works.

    // Populate the title if we're not doing that by default.
    if ( ! array_key_exists('title', $consumer) ) {
      $consumer['title'] = $consumer['match'];
    }

    $entity = \Drupal::entityManager()
      ->getStorage($consumer['entity_type'])
      ->create($consumer);

    if ( $entity->save() ) {
      return $entity;
    }
  }

}
