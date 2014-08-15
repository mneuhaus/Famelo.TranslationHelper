<?php
namespace Famelo\TranslationHelper\Controller;

use Famelo\TranslationHelper\Core\XliffModel;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\I18n\Locale;
use TYPO3\Flow\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class StandardController extends \TYPO3\Flow\Mvc\Controller\ActionController {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 */
	protected $packageManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\I18n\Service
	 */
	protected $localizationService;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\I18n\Xliff\XliffParser
	 */
	protected $xliffParser;

		/**
	 * Initializes the view before invoking an action method.
	 *
	 * Override this method to solve assign variables common for all actions
	 * or prepare the view in another way before the action is called.
	 *
	 * @param \TYPO3\Flow\Mvc\View\ViewInterface $view The view to be initialized
	 * @return void
	 * @api
	 */
	protected function initializeView(\TYPO3\Flow\Mvc\View\ViewInterface $view) {
		$view->assign('menuItems', $this->createMenu());
	}

	/**
	 * The index action that shows a list of packages that are available for testing
	 *
	 * @return void
	 */
	public function indexAction() {
	}


	/**
	 * @param string $packageKey
	 */
	public function packageAction($packageKey) {
		$this->view->assign('packageKey', $packageKey);
		$package = $this->packageManager->getPackage($packageKey);

		$sources = array();
		$translationPath = $package->getResourcesPath() . 'Private/Translations/';
		if (is_dir($translationPath)) {
			$languages = $this->getLanguages($package);

			$files = Files::readDirectoryRecursively($translationPath, 'xlf');
			foreach ($files as $file) {
				$sourceName = str_replace('.xlf', '', basename($file));
				$sources[$sourceName] = array(
					'name' => $sourceName,
					'languages' => array()
				);
			}

			foreach ($languages as $languageName) {
				$languagePath = $translationPath . $languageName . '/';
				foreach ($sources as $sourceName => $source) {
					$sourcePath = $languagePath . $source['name'] . '.xlf';
					$sources[$sourceName]['languages'][] = array(
						'name' => $languageName,
						'exists' => file_exists($sourcePath)
					);
				}
			}
		}
		$this->view->assign('languages', $languages);
		$this->view->assign('sources', $sources);
	}

	/**
	 * @param string $packageKey
	 * @param string $language
	 */
	public function createLanguageAction($packageKey, $language) {
		$package = $this->packageManager->getPackage($packageKey);
		$languagePath = $package->getResourcesPath() . 'Private/Translations/' . $language;
		if (!is_dir($languagePath)) {
			Files::createDirectoryRecursively($languagePath);
		}
		$this->redirect('package', NULL, NULL, array('packageKey' => $packageKey));
	}

	/**
	 * @param string $packageKey
	 * @param string $source
	 * @param string $language
	 */
	public function createSourceAction($packageKey, $source, $language = NULL) {
		$package = $this->packageManager->getPackage($packageKey);
		$languages = $language === NULL ? $this->getLanguages($package) : array($language);
		foreach ($languages as $language) {
			$sourcePath = $package->getResourcesPath() . 'Private/Translations/' . $language . '/' . $source . '.xlf';
			file_put_contents($sourcePath, '<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
	<file original="" source-language="'.$language.'" target-language="'.$language.'" datatype="plaintext">
		<body>
		</body>
	</file>
</xliff>');
		}
		$this->redirect('package', NULL, NULL, array('packageKey' => $packageKey));
	}

	/**
	 * @param string $packageKey
	 * @param string $source
	 * @param string $language
	 */
	public function editSourceAction($packageKey, $source, $language) {
		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('sourceName', $source);
		$this->view->assign('language', $language);
		$package = $this->packageManager->getPackage($packageKey);
		$sourcePath = $package->getResourcesPath() . 'Private/Translations/' . $language . '/' . $source . '.xlf';
		$xliff = new XliffModel($sourcePath, new Locale($language));

		$units = array();
		foreach ($xliff->getTranslationUnits() as $translationUnitId => $translationUnit) {
			$units[] = array(
				'id' => $translationUnitId,
				'source' => $translationUnit[0]['source'],
				'target' => $translationUnit[0]['target']
			);
		}
		$this->view->assign('units', $units);
		// $this->redirect('package', NULL, NULL, array('packageKey' => $packageKey));
	}

	/**
	 * @param string $packageKey
	 * @param string $sourceName
	 * @param string $language
	 * @param string $id
	 * @param string $source
	 * @param string $target
	 */
	public function saveUnitAction($packageKey, $sourceName, $language, $id, $source, $target) {
		$package = $this->packageManager->getPackage($packageKey);
		$sourcePath = $package->getResourcesPath() . 'Private/Translations/' . $language . '/' . $sourceName . '.xlf';
		$xliff = new XliffModel($sourcePath, new Locale($language));

		$xliff->updateLabel($id, $source, $target);

		return '{result: "success"}';
	}

	/**
	 * @param string $packageKey
	 * @param string $source
	 */
	public function syncAction($packageKey, $source) {
		$package = $this->packageManager->getPackage($packageKey);

		$languages = $this->getLanguages($package);
		$mergedTranslationUnits = array();
		foreach ($languages as $language) {
			$sourcePath = $package->getResourcesPath() . 'Private/Translations/' . $language . '/' . $source . '.xlf';
			$sourceModificationTime = filemtime($sourcePath);
			$xliff = new XliffModel($sourcePath, new Locale($language));
			foreach ($xliff->getTranslationUnits() as $translationUnitId => $translationUnit) {
				if (isset($mergedTranslationUnits[$translationUnitId])
					&& $mergedTranslationUnits[$translationUnitId]['modificationTime'] > $sourceModificationTime) {
					continue;
				}
				$mergedTranslationUnits[$translationUnitId] = $translationUnit;
				$mergedTranslationUnits[$translationUnitId]['modificationTime'] = $sourceModificationTime;
			}
		}

		foreach ($languages as $language) {
			$sourcePath = $package->getResourcesPath() . 'Private/Translations/' . $language . '/' . $source . '.xlf';
			$xliff = new XliffModel($sourcePath, new Locale($language));
			foreach ($mergedTranslationUnits as $translationUnitId => $translationUnit) {
				if ($xliff->hasLabel($translationUnitId) === TRUE) {
					continue;
				}
				$sourceText = $translationUnit[0]['source'];
				$xliff->add($translationUnitId, $sourceText);
			}
		}

		$this->redirect('package', NULL, NULL, array('packageKey' => $packageKey));
	}

	/**
	 * @return array
	 */
	protected function createMenu() {
		$menuItems = array();
		foreach ($this->packageManager->getActivePackages() as $package) {
			$manifest = $package->getComposerManifest();

			if (!isset($manifest->type) || !stristr($manifest->type, 'typo3-flow-package')) {
				continue;
			}

			$menuItem = array(
				'package' => $package,
				'sources' => array()
			);

			$translationPath = $package->getResourcesPath() . 'Private/Translations/';

			// if (is_dir($translationPath)) {
			// 	$files = Files::readDirectoryRecursively($translationPath, 'xlf');
			// 	foreach ($files as $file) {
			// 		$source = str_replace('.xlf', '', basename($file));
			// 		$menuItem['sources'][$source] = $source;
			// 	}
			// }

			$menuItems[$package->getPackageKey()] = $menuItem;
		}

		ksort($menuItems);

		return $menuItems;
	}

	public function getLanguages($package) {
		$translationPath = $package->getResourcesPath() . 'Private/Translations/';
		$languageNames = scandir($translationPath);
		$languages = array();
		foreach ($languageNames as $languageName) {
			if (substr($languageName, 0, 1) == '.') {
				continue;
			}
			$languages[] = $languageName;
		}
		return $languages;
;	}
}

?>