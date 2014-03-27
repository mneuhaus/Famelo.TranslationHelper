<?php
namespace Famelo\TranslationHelper\Log;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Core\RequestHandlerInterface;
use TYPO3\Flow\Http\HttpRequestHandlerInterface;
use TYPO3\Flow\Object\ObjectManagerInterface;


/**
 * The default logger of the Flow framework
 *
 * @api
 */
class TranslationLogger extends \TYPO3\Flow\Log\Logger implements TranslationLoggerInterface {

}
