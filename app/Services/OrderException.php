<?php

namespace App\Services;

use RuntimeException;

/** A user-presentable business-rule failure (safe to show to the customer/admin). */
class OrderException extends RuntimeException {}
