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

use Famelo\TranslationHelper\Core\TranslationService;
use Famelo\TranslationHelper\Core\Translator;
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
	 * @var TranslationService
	 * @Flow\Inject
	 */
	protected $translationService;

	/**
	 * @var Translator
	 * @Flow\Inject
	 */
	protected $translator;

	/**
	 *
	 * @Flow\Around("setting(Famelo.TranslationHelper.autoCreateTranslations) && method(Famelo\TranslationHelper\Core\Translator->translate(*))")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed The result of the target method if it has not been intercepted
	 */
	public function autoCreateIdTranslation(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		return $this->translationService->translateJoinPoint($joinPoint);
	}

	/**
	 *
	 * @Flow\Around("setting(Famelo.TranslationHelper.autoCreateTranslations) && method(TYPO3\Fluid\ViewHelpers\TranslateViewHelper->render(*))")
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed The result of the target method if it has not been intercepted
	 */
	public function replaceTranslateViewHelper(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		$id = $joinPoint->getMethodArgument('id');
		$value = $joinPoint->getMethodArgument('value');
		$arguments = $joinPoint->getMethodArgument('arguments');
		$source = $joinPoint->getMethodArgument('source');
		$package = $joinPoint->getMethodArgument('package');
		$quantity = $joinPoint->getMethodArgument('quantity');
		$locale = $joinPoint->getMethodArgument('locale');

		if ($package === NULL) {
			$reflectionProperty = new \ReflectionProperty(get_class($joinPoint->getProxy()), 'controllerContext');
			$reflectionProperty->setAccessible(TRUE);
			$controllerContext = $reflectionProperty->getValue($joinPoint->getProxy());
			$package = $controllerContext->getRequest()->getControllerPackageKey();
		}

		$originalLabel = $value === NULL ? $joinPoint->getProxy()->renderChildren() : $value;

		try {
			$this->translator->reset()
				->setSourceName($source)
				->setPackageKey($package)
				->setQuantity($quantity);

			if ($locale !== NULL) {
				$this->translator->setLocale($locale);
			}

			return $this->translator->translate($id, $originalLabel, $arguments);
		} catch (TranslationNotFoundException $e) {
			return $originalLabel;
		} catch (InvalidLocaleIdentifierException $e) {
			throw new ViewHelper\Exception('"' . $locale . '" is not a valid locale identifier.', 1279815885);
		}
	}

}

?>