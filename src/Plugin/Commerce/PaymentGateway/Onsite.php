<?php

namespace Drupal\commerce_payment_onsite_gateway\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;

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
 *     "add-payment-method" = "Drupal\commerce_payment_onsite_gateway\PluginForm\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"encrypted_credit_card"},
 *   credit_card_types = {
 *     "amex", "mastercard", "visa",
 *   },
 * )
 */
class Onsite extends OnsitePaymentGatewayBase implements OnsiteInterface {

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
