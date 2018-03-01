<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

/**
 * Extend TYPO3 SYSEXT:tstemplate
 */
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Tstemplate\\Controller\\TypoScriptTemplateModuleController'] = array(
	'className' => 'TYPO3\\CMS\\Xtstemplate\\Controller\\TypoScriptTemplateModuleController',
);
