<?php

namespace Drupal\commerce_payment_onsite\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;

/**
 * Provides the interface for the onsite payment gateway.
 *
 * The OnsitePaymentGatewayInterface is the base interface which all on-site
 * gateways implement. The other interfaces signal which additional capabilities
 * the gateway has. The gateway plugin is free to expose additional methods,
 * which would be defined below.
 */
interface OnsiteInterface extends OnsitePaymentGatewayInterface {

}
