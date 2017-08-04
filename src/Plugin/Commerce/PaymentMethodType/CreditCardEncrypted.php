<?php

namespace Drupal\commerce_payment_onsite_gateway\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce\BundleFieldDefinition;
use Drupal\commerce_payment\CreditCard as CreditCardHelper;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;

/**
 * Provides the encrypted credit card payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "credit_card_encrypted",
 *   label = @Translation("Credit card"),
 *   create_label = @Translation("New credit card"),
 * )
 */
class CreditCardEncrypted extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    // Get the encryption service so that we can decrypt the CC details - here
    // just for display purposes.
    // @todo Inject encryption services
    $encryption_profile_manager = \Drupal::service('encrypt.encryption_profile.manager');
    // @todo Allow configuring which encryption profile to use
    $encryption_profile = $encryption_profile_manager->getEncryptionProfile('cc_encryption');
    $encrypt = \Drupal::service('encryption');

    $decrypted_card_type = $encrypt->decrypt(
      $payment_method->encrypted_card_type->value,
      $encryption_profile
    );
    $decrypted_card_number = $encrypt->decrypt(
      $payment_method->encrypted_card_number->value,
      $encryption_profile
    );

    $card_type = CreditCardHelper::getType($decrypted_card_type);

    $args = [
      '@card_type' => $card_type->getLabel(),
      '@card_number' => substr($decrypted_card_number, -4),
    ];
    return $this->t('@card_type ending in @card_number', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    // Let's define all fields as string_long, because the exact length of
    // encrypted information is not known and it depends on the selected
    // encryption algorithm as well.
    // We could use a blob field, but we use the existing string_long field
    // instead so that we don't have to define a blob field or depend on another
    // module that provides that.

    $fields['encrypted_card_type'] = BundleFieldDefinition::create('string_long')
      ->setLabel(t('Card type'))
      ->setDescription(t('The credit card type.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', ['\Drupal\commerce_payment\CreditCard', 'getTypeLabels']);

    $fields['encrypted_card_number'] = BundleFieldDefinition::create('string_long')
      ->setLabel(t('Card number'))
      ->setDescription(t('The credit card number.'))
      ->setRequired(TRUE);

    $fields['encrypted_card_exp_month'] = BundleFieldDefinition::create('string_long')
      ->setLabel(t('Card expiration month'))
      ->setDescription(t('The credit card expiration month.'));

    $fields['encrypted_card_exp_year'] = BundleFieldDefinition::create('string_long')
      ->setLabel(t('Card expiration year'))
      ->setDescription(t('The credit card expiration year.'));

    $fields['encrypted_card_cvv'] = BundleFieldDefinition::create('string_long')
      ->setLabel(t('Card verification value'))
      ->setDescription(t('The credit card verification value.'));

    return $fields;
  }

}
