<?php

namespace NineteenNinetyFour\SocialImages\Templates;

use RuntimeException;

/**
 * A template that can't be used: the wrong format, or missing its base
 * layout. The message is written for the person importing it.
 */
class InvalidTemplate extends RuntimeException {}
