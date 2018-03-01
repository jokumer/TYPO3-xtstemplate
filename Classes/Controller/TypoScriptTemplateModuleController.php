<?php
namespace TYPO3\CMS\Xtstemplate\Controller;

/* TYPO3 CMS v7.6.0 -8.7.99
 *
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\BaseScriptClass;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;

/**
 * Module: TypoScript Tools
 *
 * $TYPO3_CONF_VARS["MODS"]["web_ts"]["onlineResourceDir"]  = Directory of default resources. Eg. "fileadmin/res/" or so.
 */
class TypoScriptTemplateModuleController extends \TYPO3\CMS\Tstemplate\Controller\TypoScriptTemplateModuleController {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Main
	 *
	 * @return void
	 */
    public function main()
    {
        // Template markers
        $markers = array(
            'CSH' => '',
            'FUNC_MENU' => '',
            'CONTENT' => ''
        );

        // Access check...
        // The page will show only if there is a valid page and if this page may be viewed by the user
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $this->access = is_array($this->pageinfo);

        /** @var DocumentTemplate doc */
        $this->doc = GeneralUtility::makeInstance(DocumentTemplate::class);
        $this->moduleTemplate->getPageRenderer()->addCssFile(ExtensionManagementUtility::extRelPath('tstemplate') . 'Resources/Public/Css/styles.css');

        $lang = $this->getLanguageService();

        if ($this->id && $this->access) {
            $urlParameters = array(
                'id' => $this->id,
                'template' => 'all'
            );
            $aHref = BackendUtility::getModuleUrl('web_ts', $urlParameters);
            $this->moduleTemplate->setForm('<form action="' . htmlspecialchars($aHref) . '" method="post" enctype="multipart/form-data" name="editForm" class="form">');

            // JavaScript
            $this->moduleTemplate->addJavaScriptCode(
                'TSTemplateInlineJS', '
                function uFormUrl(aname) {
                    document.forms[0].action = ' . GeneralUtility::quoteJSvalue(($aHref . '#')) . '+aname;
                }
                function brPoint(lnumber,t) {
                    window.location.href = ' . GeneralUtility::quoteJSvalue(($aHref . '&SET[function]=TYPO3\\CMS\\Tstemplate\\Controller\\TypoScriptTemplateObjectBrowserModuleFunctionController&SET[ts_browser_type]=')) . '+(t?"setup":"const")+"&breakPointLN="+lnumber;
                    return false;
                }
                if (top.fsMod) top.fsMod.recentIds["web"] = ' . $this->id . ';
            ');
            $this->moduleTemplate->getPageRenderer()->addCssInlineBlock(
                'TSTemplateInlineStyle', '
                TABLE#typo3-objectBrowser { width: 100%; margin-bottom: 24px; }
                TABLE#typo3-objectBrowser A { text-decoration: none; }
                TABLE#typo3-objectBrowser .comment { color: maroon; font-weight: bold; }
                .ts-typoscript { width: 100%; }
                .tsob-search-submit {margin-left: 3px; margin-right: 3px;}
                .tst-analyzer-options { margin:5px 0; }
            ');
            // Setting up the context sensitive menu:
            $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/ClickMenu');
            // Build the module content
            $this->content = $this->doc->header($lang->getLL('moduleTitle'));
            $this->extObjContent();
            // Setting up the buttons and markers for docheader
            $this->getButtons();
            $this->generateMenu();
        } else {
			// Template pages:
#$this->getDatabaseConnection()->store_lastBuiltQuery = TRUE;
			if (\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword')) {
				$searchWhere = '
					AND (
						sys_template.constants LIKE \'%' . \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword') . '%\' OR
						sys_template.config LIKE \'%' . \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword') . '%\'
					)
				';
			} else {
				$searchWhere = '';
			}
			$records = $this->getDatabaseConnection()->exec_SELECTgetRows(
				'pages.uid
				, count(*) AS count
				, max(sys_template.root) AS root_max_val
				, min(sys_template.root) AS root_min_val
				, sys_template.uid AS TSuid
				, sys_template.pid AS TSpid
				, sys_template.title AS TStitle
				, sys_template.description AS TSdescription
				, sys_template.tstamp AS TStstamp
				, sys_template.hidden AS TShidden
				, sys_template.clear AS TSclear
				, sys_template.include_static_file TSinclude_static_file
				, sys_template.nextLevel TSnextLevel
				, sys_template.basedOn TSbasedOn
				, sys_template.constants AS TSconstants
				, sys_template.config AS TSsetup',
				'pages,sys_template',
				'pages.uid=sys_template.pid'
					. BackendUtility::deleteClause('pages')
					. BackendUtility::versioningPlaceholderClause('pages')
					. BackendUtility::deleteClause('sys_template')
					. BackendUtility::versioningPlaceholderClause('sys_template')
					. $searchWhere,
				'pages.uid, sys_template.uid',
				'pages.pid, pages.sorting'
			);
#echo $this->getDatabaseConnection()->debug_lastBuiltQuery;
			$pArray = array();
			if (!empty($records)) {
				foreach ($records as $record) {
					$this->setInPageArray($pArray, BackendUtility::BEgetRootLine($record['uid'], 'AND 1=1'), $record);
				}	
			}		
			// search form
			$form = '<form method="post">
						<div class="form-group">
							<div class="input-group">
								<input type="hidden" name="cmd" class="form-control" value="search">
								<div class="form-control-clearable">
									<input class="form-control t3js-clearable" type="text" name="sword" value="' . \TYPO3\CMS\Core\Utility\GeneralUtility::_GP('sword') . '">
									<button type="button" class="close" tabindex="-1" aria-hidden="true" style="display: none;">
										<span class="fa fa-times"></span>
									</button>
								</div>
								<span class="input-group-btn">
									<button class="btn btn-default" type="submit">Search</button>
								</span>
							</div>
						</div>
					</form>';
			$table = '<div class="table-fit"><table class="table table-striped table-hover" id="ts-overview">' .
					'<thead>' .
					'<tr>' .
					'<th>' . $lang->getLL('pageName') . '</th>' .
					'<th><span title="Count ' . $lang->getLL('templates') . '">*</span></th>' .
					'<th>Pid</th>' .
					'<th>Uid</th>' .
					'<th>Title</th>' .
					'<th>Hidden</th>' .
					'<th>Clear</th>' .
					'<th>' . $lang->getLL('isRoot') . '</th>' .
					'<th>' . $lang->getLL('isExt') . '</th>' .
					'<th>Tstamp</th>' .
					'<th>Constants</th>' .
					'<th>Setup</th>' .
					'<th>IncludeStaticFile</th>' .
					'<th>NextLevel</th>' .
					'<th>BasedOn</th>' .
					'</tr>' .
					'</thead>' .
					'<tbody>' . implode('', $this->renderExtendedList($pArray)) . '</tbody>' .
					'</table></div>';
			$contentSection = $form . $table;
			$this->content = $this->doc->header($lang->getLL('moduleTitle'));
			$this->content .= $this->doc->section('', '<p class="lead">' . $lang->getLL('overview') . '</p>' . $contentSection);

			// RENDER LIST of pages with templates, END
			// Setting up the buttons and markers for docheader
			$this->getButtons();
		}
	}

	/**
	 * Render the list
	 * VSM special fields
	 *
	 * @param array $pArray
	 * @param array $lines
	 * @param int $c
	 * @return array
	 */
    public function renderExtendedList($pArray, $lines = array(), $c = 0)
    {
        static $i;

        if (!is_array($pArray)) {
            return $lines;
        }
		$statusCheckedIcon = $this->moduleTemplate->getIconFactory()->getIcon('status-status-checked', Icon::SIZE_SMALL)->render();
		foreach ($pArray as $k => $v) {
			if (MathUtility::canBeInterpretedAsInteger($k)) {
				if (isset($pArray[$k . '_'])) {
					$lines[] = '<tr class="' . ($i++ % 2 == 0 ? 'bgColor4' : 'bgColor6') . '">
						<td nowrap><span style="width: 1px; height: 1px; display:inline-block; margin-left: ' . $c * 20 . 'px"></span>' . '<a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('id' => $k))) . '" title="' . htmlspecialchars('ID: ' . $k) . '">' . $this->moduleTemplate->getIconFactory()->getIconForRecord('pages', BackendUtility::getRecordWSOL('pages', $k), Icon::SIZE_SMALL)->render() . GeneralUtility::fixed_lgd_cs($pArray[$k], 30) . '</a></td>
						<td>' . $pArray[($k . '_')]['count'] . '</td>
						<td>' . $pArray[($k . '_')]['TSpid'] . '</td>
						<td>' . $pArray[($k . '_')]['TSuid'] . '</td>
						<td>' . $pArray[($k . '_')]['TStitle'] . '' . ($pArray[$k . '_']['TSdescription'] ? ' <i>...<span title="' . htmlspecialchars($pArray[$k . '_']['TSdescription']) . '">' . substr($pArray[$k . '_']['TSdescription'], 0, 10) . '</span>...</i>' : '') . '</td>
						<td>' . ($pArray[$k . '_']['TShidden'] > 0 ? $statusCheckedIcon : '&nbsp;') . '</td>
						<td>' . ($pArray[$k . '_']['TSclear'] > 0 ? $statusCheckedIcon : '&nbsp;') . '</td>
						<td>' . ($pArray[$k . '_']['root_max_val'] > 0 ? $statusCheckedIcon : '&nbsp;') . '</td>
						<td>' . ($pArray[$k . '_']['root_min_val'] == 0 ? $statusCheckedIcon : '&nbsp;') . '</td>
						<td>' . date('d.m.Y H:i', $pArray[$k . '_']['TStstamp']) . '</td>
						<td><span title="' . htmlspecialchars($pArray[$k . '_']['TSconstants']) . '">' . substr($pArray[$k . '_']['TSconstants'], 0, 20) . '</span></td>
						<td><span title="' . htmlspecialchars($pArray[$k . '_']['TSsetup']) . '">' . substr($pArray[$k . '_']['TSsetup'], 0, 20) . '</span></td>
						<td>' . $pArray[($k . '_')]['TSinclude_static_file'] . '</td>
						<td>' . $pArray[($k . '_')]['TSnextLevel'] . '</td>
						<td>' . $pArray[($k . '_')]['TSbasedOn'] . '</td>
						</tr>';
				} else {
					$lines[] = '<tr class="' . ($i++ % 2 == 0 ? 'bgColor4' : 'bgColor6') . '">
						<td nowrap><span style="width: 1px; height: 1px; display:inline-block; margin-left: ' . $c * 20 . 'px"></span>' . $this->moduleTemplate->getIconFactory()->getIconForRecord('pages', BackendUtility::getRecordWSOL('pages', $k), Icon::SIZE_SMALL)->render() . GeneralUtility::fixed_lgd_cs($pArray[$k], 30) . '</td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						</tr>';
				}
				$lines = $this->renderExtendedList($pArray[$k . '.'], $lines, $c + 1);
			}
		}
		return $lines;
	}
}
