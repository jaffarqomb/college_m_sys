<?php

namespace App\Policies;

/**
 * Payments inherit the finance authorization; recording payments is done
 * through dedicated actions on the invoice screens.
 */
class PaymentPolicy extends FinanceResourcePolicy {}
