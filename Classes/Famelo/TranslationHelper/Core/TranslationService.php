<?php
namespace Famelo\TranslationHelper\Core;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Famelo\TranslationHelper\Core\TranslationNotFoundException;
use Famelo\TranslationHelper\Core\XliffModel;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Utility\Files;

/**
 */
class TranslationService {

	/**
	 * @var \TYPO3\Flow\I18n\Service
	 * @Flow\Inject
	 */
	protected $localizationService;

	/**
	 * @var \Famelo\TranslationHelper\Log\TranslationLoggerInterface
	 * @Flow\Inject
	 */
	protected $translationLogger;

	/**
	 * @var \TYPO3\Flow\Cache\CacheManager
	 * @Flow\Inject
	 */
	protected $cacheManager;

	/**
	 * @var array
	 * @Flow\Inject(setting="autoCreationWhitelist")
	 */
	protected $whitelist = array();

	/**
	 * @var array
	 * @Flow\Inject(setting="debugWrapTranslations")
	 */
	protected $debugWrapTranslations = FALSE;

	/**
	 * An absolute path to the directory where translation files reside.
	 *
	 * @var string
	 */
	protected $xliffBasePath = 'Private/Translations/';

	/**
	 * @var string
	 */
	protected $runtimeCache = array();

	/**
	 * Returns a XliffModel instance representing desired XLIFF file.
	 *
	 * Will return existing instance if a model for given $sourceName was already
	 * requested before. Returns FALSE when $sourceName doesn't point to existing
	 * file.
	 *
	 * @param string $packageKey Key of the package containing the source file
	 * @param string $sourceName Relative path to existing CLDR file
	 * @param \TYPO3\Flow\I18n\Locale $locale Locale object
	 * @return \TYPO3\Flow\I18n\Xliff\XliffModel New or existing instance
	 * @throws \TYPO3\Flow\I18n\Exception
	 */
	protected function getXlfFileName($packageKey, $sourceName, \TYPO3\Flow\I18n\Locale $locale) {
		$sourcePath = \TYPO3\Flow\Utility\Files::concatenatePaths(array(
			'resource://' . $packageKey,
			$this->xliffBasePath
		));

		$possibleXliffFilename = Files::concatenatePaths(array($sourcePath, $locale->getLanguage(), $sourceName . '.xlf'));
		return $possibleXliffFilename;
	}

	/**
	 *
	 * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current joinpoint
	 * @return mixed The result of the target method if it has not been intercepted
	 */
	public function translateJoinPoint(\TYPO3\Flow\Aop\JoinPointInterface $joinPoint) {
		$packageKeys = $joinPoint->getProxy()->getPackageKeys();
		$autocreatePackageKeys = array_intersect($this->whitelist, $packageKeys);

		if (count($autocreatePackageKeys) == 0) {
			$packageKey = $joinPoint->getProxy()->getPackageKey();
			$this->translationLogger->log(sprintf(
				'Skipping %s (%s) because it\'s not whitelisted (%s)',
				$packageKey,
				implode(', ', $packageKeys),
				implode(', ', $this->whitelist)
			));
			return $joinPoint->getAdviceChain()->proceed($joinPoint);
		}
		$locale = $joinPoint->getProxy()->getLocale();
		if ($locale === NULL) {
			$locale = $this->localizationService->getConfiguration()->getCurrentLocale();
		}
		$sourceName = $joinPoint->getProxy()->getSourceName();
		$labelId = $joinPoint->getMethodArgument('labelId');
		$originalLabel = $joinPoint->getMethodArgument('originalLabel');
		$packageKey = current($autocreatePackageKeys);
		$fileName = $this->getXlfFileName($packageKey, $sourceName, $locale);

		try {
			$result = $joinPoint->getAdviceChain()->proceed($joinPoint);
		} catch (TranslationNotFoundException $exception) {
			if ($labelId === NULL && $originalLabel !== NULL) {
				$labelId = trim(strtolower('slug.' . preg_replace('/[^A-Za-z0-9-]+/', '-', $originalLabel)), '-');
			}

			$model = new XliffModel($fileName, $locale);
			$model->initializeObject();

			if (in_array($labelId, $this->runtimeCache)) {
				return $this->wrapString($originalLabel, $locale);
			}

			if (!$model->hasLabel($labelId)) {
				$model->add($labelId, $originalLabel);
				$this->translationLogger->log(sprintf(
					'Added new translation %s to "%s:%s" (locale: %s)',
					str_pad('"' . $labelId . '"', 80, ' '),
					$packageKey,
					$sourceName,
					$locale->getLanguage()
				));
				$this->flushI18nCaches();
			}

			$this->runtimeCache[] = $labelId;
			return $this->wrapString($originalLabel, $locale);
		} catch (\TYPO3\Flow\I18n\Exception $exception) {
			switch ($exception->getCode()) {
				case 1334759591:
					// Missing xlf file
					$model = new XliffModel($fileName, $locale);
					$model->initializeObject();
					$model->add($labelId, $originalLabel);
					$this->translationLogger->log(sprintf(
						'Added new xlf for "%s" file to "%s" (locale: %s)',
						$packageKey,
						$sourceName,
						$locale->getLanguage()
					));
					$this->flushI18nCaches();
					break;

				default:
					throw $exception;
					break;
			}
		}

		return $this->wrapString($result, $locale);
	}

	public function wrapString($string, $locale) {
		if ($this->debugWrapTranslations === FALSE) {
			return $string;
		}
		return '❪' . $string . '❫';
	}

	public function flushI18nCaches() {
		$this->cacheManager->getCache('Flow_I18n_XmlModelCache')->flush();
	}

}

?>