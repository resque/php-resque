<?php

namespace Resque\Exceptions;

use \Exception;

/**
 * Exception to be thrown if while enqueuing a job it should not be created.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class DoNotCreateException extends Exception
{
}
