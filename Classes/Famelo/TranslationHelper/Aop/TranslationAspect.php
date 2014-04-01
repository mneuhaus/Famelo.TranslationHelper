<?php
namespace Famelo\TranslationHelper\Aop;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Famelo\TranslationHelper\Core\XliffModel;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Files;

/**
 * The central security aspect, that invokes the security interceptors.
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class TranslationAspect {

	/**
	 * @var \Famelo\TranslationHelper\Core\TranslationService
	 * @Flow\Inject
	 */
	protected $translationService;

	/**
	 *
	 * @Flow\Around("setting(Famelo.TranslationHelper.autoCreateTranslations) && method(TYPO3\Flow\I18n\Translator->translate(*))")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed The result of the target method if it has not been intercepted
	 */
	public function autoCreateIdTranslation(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		return $this->translationService->translateJoinPoint($joinPoint);
	}

}

?>