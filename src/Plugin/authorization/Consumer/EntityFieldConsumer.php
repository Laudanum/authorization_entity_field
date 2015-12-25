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

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @TODO select an entity and then a field
    $entity_options = array();
    $bundle_options = array();
    $field_options = array();
    $entities = \Drupal::entityManager()->getDefinitions();
    foreach ( $entities as $key=>$entity ) {
      if ( $entity->isSubclassOf('\Drupal\Core\Entity\ContentEntityInterface') ) {
        $entity_options[$key] = $entity->getLabel();
      }
    }
    ksort($entity_options);

    // Populate bundle options
    if ( $entity_type = $this->configuration['entity'] ) {
      $bundles = \Drupal::entityManager()->getBundleInfo($entity_type);
      foreach ( $bundles as $key=>$bundle ) {
        $bundle_options[$key] = $bundle['label'];
      }
    }

    if ( $bundle = $this->configuration['bundle'] ) {
      // getFields
      $entity = \Drupal::entityManager()->getDefinition($entity_type);
      $fields = $entity->getFields();
      $bundles = \Drupal::entityManager()->getBundleInfo($entity_type);
      foreach ( $bundles as $key=>$bundle ) {
        $bundle_options[$key] = $bundle['label'];
      }
    }

    // @TODO an AJAX callback to get the bundles based on the entity
    $form['entity'] = array(
      '#type' => 'select',
      '#title' => t('Entity type'),
      '#options' => $entity_options,
      '#default_value' => $this->configuration['entity'],
      '#required' => TRUE,
      '#description' => 'Choose the type of entity to match.',
      '#ajax' => array(
        'trigger_as' => array('name' => 'entityfieldconsumer_enity'),
        'callback' => '::buildAjaxEntityFieldConsumerConfigForm',
        'wrapper' => 'authorization-consumer-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    // @TODO an AJAX callback to get the fields based on the bundle or entity
    $form['bundle'] = array(
      '#type' => 'select',
      '#title' => t('Bundle type'),
      '#options' => $bundle_options,
      '#default_value' => $this->configuration['bundle'],
      '#description' => 'Choose the type of bundle to match.',
      '#ajax' => array(
        'trigger_as' => array('name' => 'entityfieldconsumer_bundle'),
        'callback' => '::buildAjaxEntityFieldConsumerConfigForm',
        'wrapper' => 'authorization-consumer-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    $form['field_match'] = array(
      '#type' => 'textfield',
      '#title' => t('Match field'),
      '#default_value' => NULL,
      '#description' => 'Map the result to an entity\'s field. eg: the title of a node.',
    );
    $form['field_add'] = array(
      '#type' => 'textfield',
      '#title' => t('Add field'),
      '#default_value' => NULL,
      '#description' => 'Add the user to an entity\'s field. eg: the field_users entity reference field of a node.',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
  }
}
