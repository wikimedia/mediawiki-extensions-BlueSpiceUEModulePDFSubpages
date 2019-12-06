<?php
/**
 * UniversalExport PDF Module extension for BlueSpice
 *
 * Enables MediaWiki to export pages with subpages into PDF format.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit https://bluespice.com
 *
 * @author     Robert Vogel <vogel@hallowelt.com>
 * @author     Tobias Weichart <weichart@hallowelt.com>
 * @package    UEModulePDFSubpages
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */

/**
 * Base class for UniversalExport PDF Module extension
 * @package BlueSpice_Extensions
 * @subpackage UEModulePDFRecursive
 */
class UEModulePDFSubpages extends BsExtensionMW {

	/**
	 * Initialization of UEModulePDFSubpages extension
	 */
	protected function initExt() {
		// Hooks
		$this->setHook( 'SkinTemplateOutputPageBeforeExec' );
		$this->setHook( 'BSUEModulePDFBeforeAddingContent' );
	}

	/**
	 * Hook handler to add menu
	 * @param SkinTemplate &$oSkin
	 * @param QuickTemplate &$oTemplate
	 * @return bool Always true to keep hook running
	 */
	public function onSkinTemplateOutputPageBeforeExec( &$oSkin, &$oTemplate ) {
		if ( $oSkin->getTitle()->isContentPage() === false ) {
			return true;
		}
		if ( !$oSkin->getTitle()->userCan( 'uemodulepdfsubpages-export' ) ) {
			return true;
		}

		$oTemplate->data['bs_export_menu'][] = $this->buildContentAction();

		return true;
	}

	/**
	 * Builds the ContentAction Array fort the current page
	 * @return array - The ContentAction Array
	 */
	private function buildContentAction() {
		$aCurrentQueryParams = $this->getRequest()->getValues();
		if ( isset( $aCurrentQueryParams['title'] ) ) {
			$sTitle = $aCurrentQueryParams['title'];
		} else {
			$sTitle = '';
		}
		$sSpecialPageParameter = BsCore::sanitize( $sTitle, '', BsPARAMTYPE::STRING );
		$oSpecialPage = SpecialPage::getTitleFor( 'UniversalExport', $sSpecialPageParameter );
		if ( isset( $aCurrentQueryParams['title'] ) ) {
			unset( $aCurrentQueryParams['title'] );
		}
		$aCurrentQueryParams['ue[module]'] = 'pdf';
		$aCurrentQueryParams['ue[subpages]'] = '1';

		return [
			'id' => 'pdf-subpages',
			'href' => $oSpecialPage->getLinkUrl( $aCurrentQueryParams ),
			'title' => wfMessage( 'bs-uemodulepdfsubpages-widgetlink-subpages-title' )->text(),
			'text' => wfMessage( 'bs-uemodulepdfsubpages-widgetlink-subpages-text' )->text(),
			'class' => 'bs-ue-export-link',
			'iconClass' => 'icon-file-pdf bs-ue-export-link'
		];
	}

	/**
	 *
	 * @param array &$aTemplate
	 * @param array &$aContents
	 * @param \stdClass $oCaller
	 * @param array &$aParams
	 * @return bool Always true to keep hook running
	 */
	public function onBSUEModulePDFBeforeAddingContent( &$aTemplate, &$aContents, $oCaller,
		&$aParams = [] ) {
		global $wgRequest;
		$aParams = $oCaller->aParams;
		if ( !isset( $aParams['subpages'] ) ) {
			$aUEParams = $wgRequest->getArray( 'ue' );
			$aParams['subpages'] = isset( $aUEParams['subpages'] ) ? $aUEParams['subpages'] : 0;
		}
		if ( $aParams['subpages'] == 0 ) { return true;
		}
		$aTitles = $oCaller->oRequestedTitle->getSubpages();
		if ( count( $aTitles ) < 1 ) { return true;
		}
		$aPages = [];
		foreach ( $aTitles as $key => $oTitle ) {
			if ( $oTitle == null ) { continue;
			}
			$aPageNames[] = $oTitle->getPrefixedText();
		}
		natcasesort( $aPageNames );
		$aPageNamesSorted = array_values( $aPageNames );
		$iContentBefore = count( $aContents['content'] );
		$iPagesBefore = count( $aPages );
		// TODO: Security: check if user can read subpages
		foreach ( $aTitles as $oTitle ) {
			if ( $oTitle == null ) { continue;
			}
			$arrkey = array_search( $oTitle->getPrefixedText(), $aPageNamesSorted );
			$oPageContentProvider = new BsPageContentProvider();
			$oDOMDocument = $oPageContentProvider->getDOMDocumentContentFor( $oTitle );
			if ( !$oDOMDocument instanceof DOMDocument ) { continue;
			}
			$aContents['content'][$iContentBefore + $arrkey] = $oDOMDocument->documentElement;
			$aPages[$iPagesBefore + $arrkey] = $oTitle->getPrefixedText();
		}
		ksort( $aContents['content'] );
		ksort( $aPages );
		\Hooks::run( 'UEModulePDFSubpagesAfterContent', [ $this, &$aContents ] );

		return true;
	}
}
