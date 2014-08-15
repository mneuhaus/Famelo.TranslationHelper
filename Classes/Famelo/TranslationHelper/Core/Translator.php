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
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\I18n\Exception\InvalidLocaleIdentifierException;
use TYPO3\Flow\I18n\Locale;

/**
 * A class for translating messages
 *
 * Messages (labels) can be translated in two modes:
 * - by original label: untranslated label is used as a key
 * - by ID: string identifier is used as a key (eg. user.noaccess)
 *
 * Correct plural form of translated message is returned when $quantity
 * parameter is provided to a method. Otherwise, or on failure just translated
 * version is returned (eg. when string is translated only to one form).
 *
 * When all fails, untranslated (original) string or ID is returned (depends on
 * translation method).
 *
 * Placeholders' resolving is done when needed (see FormatResolver class).
 *
 * Actual translating is done by injected TranslationProvider instance, so
 * storage format depends on concrete implementation.
 *
 * @Flow\Scope("singleton")
 * @api
 * @see \TYPO3\Flow\I18n\FormatResolver
 * @see \TYPO3\Flow\I18n\TranslationProvider\TranslationProviderInterface
 * @see \TYPO3\Flow\I18n\Cldr\Reader\PluralsReader
 */
class Translator extends \TYPO3\Flow\I18n\Translator {

	/**
	 * @var \TYPO3\Flow\Configuration\ConfigurationManager
	 * @Flow\Inject
	 */
	protected $configurationManager;

	/**
	 *
	 * An identifier of locale to use (NULL for use the default locale)
	 *
	 * @var \TYPO3\Flow\I18n\Locale
	 */
	protected $locale;

	/**
	 * Target package key. If not set, the current package key will be used
	 *
	 * @var string
	 */
	protected $packageKey;

	/**
	 * Name of file with translations
	 *
	 * @var string
	 */
	protected $sourceName = 'Main';

	/**
	 * A number to find plural form for (float or int), NULL to not use plural forms
	 *
	 * @var string
	 */
	protected $quantity;

	/**
	 * @var boolean
	 */
	protected $translationFound;

	/**
	 * Translates the message given as $originalLabel.
	 *
	 * Searches for a translation in the source as defined by $sourceName
	 * (interpretation depends on concrete translation provider used).
	 *
	 * If any arguments are provided in the $arguments array, they will be inserted
	 * to the translated string (in place of corresponding placeholders, with
	 * format defined by these placeholders).
	 *
	 * If $quantity is provided, correct plural form for provided $locale will
	 * be chosen and used to choose correct translation variant.
	 *
	 * If no $locale is provided, default system locale will be used.
	 *
	 * @param string $originalLabel Untranslated message
	 * @param array $arguments An array of values to replace placeholders with
	 * @param mixed $quantity A number to find plural form for (float or int), NULL to not use plural forms
	 * @param \TYPO3\Flow\I18n\Locale $locale Locale to use (NULL for default one)
	 * @param string $sourceName Name of file with translations, base path is $packageKey/Resources/Private/Locale/Translations/
	 * @param string $packageKey Key of the package containing the source file
	 * @return string Translated $originalLabel or $originalLabel itself on failure
	 * @api
	 */
	public function translateByOriginalLabel($originalLabel, array $arguments = array(), $quantity = NULL, \TYPO3\Flow\I18n\Locale $locale = NULL, $sourceName = 'Main', $packageKey = 'TYPO3.Flow') {
		if ($locale === NULL) {
			$locale = $this->localizationService->getConfiguration()->getCurrentLocale();
		}
		$pluralForm = $this->getPluralForm($quantity, $locale);

		$translatedMessage = $this->translationProvider->getTranslationByOriginalLabel($originalLabel, $locale, $pluralForm, $sourceName, $packageKey);

		if ($translatedMessage === FALSE) {
			$translatedMessage = $originalLabel;
			$this->setTranslationFound(FALSE);
		} else {
			$this->setTranslationFound(TRUE);
		}

		if (!empty($arguments)) {
			$translatedMessage = $this->formatResolver->resolvePlaceholders($translatedMessage, $arguments, $locale);
		}

		return $translatedMessage;
	}

	/**
	 * Returns translated string found under the $labelId.
	 *
	 * Searches for a translation in the source as defined by $sourceName
	 * (interpretation depends on concrete translation provider used).
	 *
	 * If any arguments are provided in the $arguments array, they will be inserted
	 * to the translated string (in place of corresponding placeholders, with
	 * format defined by these placeholders).
	 *
	 * If $quantity is provided, correct plural form for provided $locale will
	 * be chosen and used to choose correct translation variant.
	 *
	 * @param string $labelId Key to use for finding translation
	 * @param array $arguments An array of values to replace placeholders with
	 * @param mixed $quantity A number to find plural form for (float or int), NULL to not use plural forms
	 * @param \TYPO3\Flow\I18n\Locale $locale Locale to use (NULL for default one)
	 * @param string $sourceName Name of file with translations, base path is $packageKey/Resources/Private/Locale/Translations/
	 * @param string $packageKey Key of the package containing the source file
	 * @return string Translated message or $labelId on failure
	 * @api
	 * @see \TYPO3\Flow\I18n\Translator::translateByOriginalLabel()
	 */
	public function translateById($labelId, array $arguments = array(), $quantity = NULL, \TYPO3\Flow\I18n\Locale $locale = NULL, $sourceName = 'Main', $packageKey = 'TYPO3.Flow') {
		if ($locale === NULL) {
			$locale = $this->localizationService->getConfiguration()->getCurrentLocale();
		}
		$pluralForm = $this->getPluralForm($quantity, $locale);

		$translatedMessage = $this->translationProvider->getTranslationById($labelId, $locale, $pluralForm, $sourceName, $packageKey);

		$this->setTranslationFound(TRUE);
		if ($translatedMessage === FALSE) {
			$this->setTranslationFound(FALSE);
			return $labelId;
		} elseif (!empty($arguments)) {
			return $this->formatResolver->resolvePlaceholders($translatedMessage, $arguments, $locale);
		}
		return $translatedMessage;
	}

	public function reset() {
		$this->locale = NULL;
		$this->packageKey = NULL;
		$this->sourceName = 'Main';
		$this->quantity = NULL;
		return $this;
	}

	public function setLocale($locale) {
		$this->locale = new Locale($locale);
		return $this;
	}

	public function getLocale() {
		if ($this->locale === NULL) {
			return $this->localizationService->getConfiguration()->getCurrentLocale();
		}
		return $this->locale;
	}

	public function setPackageKey($packageKey) {
		$this->packageKey = $packageKey;
		return $this;
	}

	public function getPackageKey() {
		return $this->packageKey;
	}

	public function getPackageKeys() {
		$fallbacks = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow.i18n.translationFallbacks');
		if (isset($fallbacks[$this->packageKey])) {
			return $fallbacks[$this->packageKey];
		}

		return array($this->packageKey);
	}

	public function setSourceName($sourceName) {
		$this->sourceName = $sourceName;
		return $this;
	}

	public function getSourceName() {
		return $this->sourceName;
	}

	public function setQuantity($quantity) {
		$this->quantity = $quantity;
		return $this;
	}

	public function getQuantity() {
		return $this->quantity;
	}

	public function setTranslationFound($translationFound) {
		$this->translationFound = $translationFound;
	}

	public function getTranslationFound() {
		return $this->translationFound;
	}

	/**
	 * Returns the translation by id or original label
	 *
	 * @param string $labelId Id to use for finding translation (trans-unit id in XLIFF)
	 * @param string $originalLabel Original label to be returned if no translation is found
	 * @param array $arguments Numerically indexed array of values to be inserted into placeholders
	 * @return string Translated label or source label / ID key
	 * @throws InvalidLocaleIdentifierException
	 */
	public function translate($labelId = NULL, $originalLabel = NULL, array $arguments = array()) {
		foreach ($this->getPackageKeys() as $packageKey) {
			try {
				if ($labelId === NULL) {
					$translation = $this->translateByOriginalLabel($originalLabel, $arguments, $this->quantity, $this->getLocale(), $this->sourceName, $packageKey);
					if ($this->translationFound === TRUE) {
						return $translation;
					}
				} else {
					$labelId = strval($labelId);
					$translation = $this->translateById($labelId, $arguments, $this->quantity, $this->getLocale(), $this->sourceName, $packageKey);
					if ($translation !== $labelId) {
						return $translation;
					}
				}
			} catch(\TYPO3\Flow\I18n\Exception $exception) {
				// keep on trying
			}
		}
		throw new TranslationNotFoundException('Not translation could be found for the id ' . $labelId . ' or the original label ' . $originalLabel . ' for the package ' . $this->packageKey . ' using the source ' . $this->sourceName . ' in the locale ' . $this->getLocale()->getLanguage() . '.', 1395880028);
	}

	/**
	 * Get the plural form to be used.
	 *
	 * If $quantity is numeric and non-NULL, the plural form for provided $locale will be
	 * chosen according to it.
	 *
	 * In all other cases, NULL is returned.
	 *
	 * @param mixed $quantity
	 * @param \TYPO3\Flow\I18n\Locale $locale
	 * @return string
	 */
	protected function getPluralForm($quantity, Locale $locale) {
		if (!is_numeric($quantity)) {
			return NULL;
		} else {
			return $this->pluralsReader->getPluralForm($quantity, $locale);
		}
	}
}
