<?php

namespace Drupal\commerce_payment_onsite\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the On-site payment gateway.
 *
 * @todo Allow customisation of allowed credit card types.
 *
 * @CommercePaymentGateway(
 *   id = "onsite_encrypted",
 *   label = "On-site, encrypted",
 *   display_label = "Pay with Credit Card",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_payment_onsite\PluginForm\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card_encrypted"},
 *   credit_card_types = {
 *     "amex", "mastercard", "visa",
 *   },
 * )
 */
class OnsiteEncrypted extends OnsitePaymentGatewayBase implements OnsiteInterface {

  /**
   * The encryption profile manager service.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected $encryption_profile_manager;

  /**
   * Constructs a new Onsite object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   The payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   The payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   The encryption profile manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PaymentTypeManager $payment_type_manager,
    PaymentMethodTypeManager $payment_method_type_manager,
    TimeInterface $time,
    EncryptionProfileManagerInterface $encryption_profile_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $payment_type_manager,
      $payment_method_type_manager,
      $time
    );

    $this->encryption_profile_manager = $encryption_profile_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('encrypt.encryption_profile.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'encryption_profile' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get all available encryption profiles.
    $encryption_profiles = $this->encryption_profile_manager->getAllEncryptionProfiles();

    $options = ['_none_' => $this->t('Select an Encryption Profile')];
    foreach ($encryption_profiles as $key => $encryption_profile) {
      $options[$key] = $encryption_profile->label();
    }

    $form['encryption_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption Profile'),
      '#description' => $this->t('The encryption profile that will be used to encrypt the credit card details.'),
      '#options' => $options,
      '#default_value' => $this->configuration['encryption_profile'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    // Encryption profile is mandatory.
    if (empty($values['encryption_profile']) || $values['encryption_profile'] === '_none_') {
      $form_state->setErrorByName(
        'encryption_profile',
        $this->t('An Encryption Profile must be selected.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['encryption_profile'] = $values['encryption_profile'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // Perform the create payment request here.
    // We set the state to completed since all the functionality that this
    // payment gateway provides is to capture the CC details. Store admins are
    // meant to capture the payment independantly.
    $amount = $payment->getAmount();
    $payment->setState('completed');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(
    PaymentMethodInterface $payment_method,
    array $payment_details
  ) {
    // The expected keys are payment gateway specific and usually match the
    // PaymentMethodAddForm form elements. They are expected to be valid.
    // @todod Allow customisation of the required CC fields.
    $required_keys = [
      'type', 'number', 'expiration', 'security_code',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // Perform the create request here.

    // Get CC details from the form.
    $payment_method->encrypted_card_type = $payment_details['type'];
    $payment_method->encrypted_card_number = $payment_details['number'];
    $payment_method->encrypted_card_exp_month = $payment_details['expiration']['month'];
    $payment_method->encrypted_card_exp_year = $payment_details['expiration']['year'];
    $payment_method->encrypted_card_cvv = $payment_details['security_code'];

    // Calculate the expiration time.
    $expires = CreditCard::calculateExpirationTimestamp(
      $payment_details['expiration']['month'],
      $payment_details['expiration']['year']
    );
    $payment_method->setExpiresTime($expires);

    // Set the payment method as not reusable.
    // @todo Allow configuring whether the payment methods should be reusable.
    $payment_method->setReusable(FALSE);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

}
