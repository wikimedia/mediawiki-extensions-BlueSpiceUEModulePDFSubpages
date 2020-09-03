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

use MediaWiki\MediaWikiServices;

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
		$this->setHook(
			'ChameleonSkinTemplateOutputPageBeforeExec',
			'onSkinTemplateOutputPageBeforeExec'
		);
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
		if ( !MediaWikiServices::getInstance()
			->getPermissionManager()
			->userCan(
				'uemodulepdfsubpages-export',
				$oSkin->getUser(),
				$oSkin->getTitle()
			)
		) {
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

		if ( $aParams['subpages'] == 0 ) {
			return true;
		}

		$linkMap = [];

		$newDOM = new DOMDocument();
		$pageDOM = $aContents['content'][0];
		$pageDOM->setAttribute(
			'class',
			$pageDOM->getAttribute( 'class' ) . ' bs-source-page'
		);

		$node = $newDOM->importNode( $pageDOM, true );

		$rootTitle = $oCaller->oRequestedTitle;
		if ( $pageDOM->getElementsByTagName( 'a' )->item( 0 )->getAttribute( 'id' ) === '' ) {
			$pageDOM->getElementsByTagName( 'a' )->item( 0 )->setAttribute(
				'id',
				md5( 'bs-ue-' . $rootTitle->getPrefixedDBKey() )
			);
		}

		$linkMap[ $aTemplate['title-element']->nodeValue ] = $pageDOM->getElementsByTagName( 'a' )
			->item( 0 )->getAttribute( 'id' );

		$newDOM->appendChild( $node );

		$aSubpageList = [];
		$aSubpageList = $oCaller->oRequestedTitle->getSubpages();

		if ( count( $aSubpageList ) < 1 ) {
			return true;
		}

		$aSubpages = [];
		$aSubpageNames = [];
		foreach ( $aSubpageList as $key => $oTitle ) {
			if ( $oTitle == null ) {
				continue;
			}
			$aSubpageNames[] = $oTitle->getPrefixedText();
		}

		natcasesort( $aSubpageNames );
		$aSubpageNamesSorted = array_values( $aSubpageNames );

		$pageProvider = new BsPDFPageProvider();

		foreach ( $aSubpageNamesSorted as $key => $value ) {
			$subpageTitle = \Title::newFromText( $value );
			$pageProviderContent = $pageProvider->getPage( [
				'article-id' => $subpageTitle->getArticleID(),
				'title' => $subpageTitle->getFullText()
			] );

			if ( !isset( $pageProviderContent['dom'] ) ) {
				continue;
			}

			$DOMDocument = $pageProviderContent['dom'];

			$documentLinks = $DOMDocument->getElementsByTagName( 'a' );

			if ( $documentLinks->item( 0 ) instanceof DOMElement ) {
				if ( $documentLinks->item( 0 )->getAttribute( 'id' ) === '' ) {
					$documentLinks->item( 0 )->setAttribute(
						'id',
						md5( 'bs-ue-' . $subpageTitle->getPrefixedDBKey() )
					);
				}
				$linkMap[$subpageTitle->getSubpageText()] = $documentLinks->item( 0 )->getAttribute( 'id' );
			}

			$aContents['content'][] = $DOMDocument->documentElement;
		}

		$documentToc = $this->makeToc( $linkMap );
		foreach ( $aContents['content'] as $oDom ) {
			$this->rewriteLinks( $oDom, $linkMap );
		}

		array_unshift( $aContents['content'], $documentToc->documentElement );

		\Hooks::run( 'UEModulePDFSubpagesAfterContent', [ $this, &$aContents ] );

		return true;
	}

	/**
	 * @param DOMDocument &$domNode
	 * @param array $linkMap
	 */
	protected function rewriteLinks( &$domNode, $linkMap ) {
		$anchors = $domNode->getElementsByTagName( 'a' );
		foreach ( $anchors as $anchor ) {
			$href = null;

			$href  = $anchor->getAttribute( 'href' );
			$class = $anchor->getAttribute( 'class' );

			if ( empty( $href ) ) {
				// Jumplink targets
				continue;
			}

			$classes = explode( ' ', $class );

			if ( in_array( 'external', $classes ) ) {
				continue;
			}

			if ( !( strpos( $href, '/' ) === 0 ) ) {
				// ignore images with link=
				continue;
			}

			$parsedHref = parse_url( $href );
			if ( !isset( $parsedHref['path'] ) ) {
				continue;
			}

			$parser = new \BlueSpice\Utility\UrlTitleParser(
				$href,
				MediaWikiServices::getInstance()->getMainConfig()
			);
			$pathBasename = $parser->parseTitle()->getPrefixedText();

			// Do we have a mapping?
			if ( !isset( $linkMap[$pathBasename] ) ) {
				/*
				 * The following logic is an alternative way of creating internal links
				 * in case of poorly splitted up URLs like mentioned above
				 */
				if ( filter_var( $href, FILTER_VALIDATE_URL ) ) {
					$pathBasename = "";
					$hrefDecoded = urldecode( $href );

					foreach ( $linkMap as $linkKey => $linkValue ) {
						if ( strpos( str_replace( '_', ' ', $hrefDecoded ), $linkKey ) ) {
							$pathBasename = $linkKey;
						}
					}

					if ( empty( $pathBasename ) || strlen( $pathBasename ) <= 0 ) {
						continue;
					}
				}
			}

			$anchor->setAttribute( 'href', '#' . $linkMap[$pathBasename] );
		}
	}

	/**
	 * @param array $linkMap
	 * @return DOMDocument
	 */
	protected function makeTOC( $linkMap ) {
		$tocDocument = new DOMDocument();

		$tocWrapper = $tocDocument->createElement( 'div' );
		$tocWrapper->setAttribute( 'class', 'bs-page-content bs-page-toc' );

		$tocHeading = $tocDocument->createElement( 'h1' );
		$tocHeading->appendChild( $tocDocument->createTextNode( wfMessage( 'toc' )->text() ) );

		$tocWrapper->appendChild( $tocHeading );

		$tocList = $tocDocument->createElement( 'ul' );
		$tocList->setAttribute( 'class', 'toc' );

		$count = 1;
		foreach ( $linkMap as $linkname => $linkHref ) {
			$liClass = 'toclevel-1';
			if ( $count === 1 ) {
				$liClass .= ' bs-source-page';
			}
			$tocListItem = $tocList->appendChild( $tocDocument->createElement( 'li' ) );
			$tocListItem->setAttribute( 'class', $liClass );

			$tocListItemLink = $tocListItem->appendChild( $tocDocument->createElement( 'a' ) );
			$tocListItemLink->setAttribute( 'href', '#' . $linkHref );
			$tocListItemLink->setAttribute( 'class', 'toc-link' );

			$tocLinkSpanNumber = $tocListItemLink->appendChild( $tocDocument->createElement( 'span' ) );
			$tocLinkSpanNumber->setAttribute( 'class', 'tocnumber' );
			$tocLinkSpanNumber->appendChild( $tocDocument->createTextNode( $count . '.' ) );

			$tocListSpanText = $tocListItemLink->appendChild( $tocDocument->createElement( 'span' ) );
			$tocListSpanText->setAttribute( 'class', 'toctext' );
			$tocListSpanText->appendChild( $tocDocument->createTextNode( ' ' . $linkname ) );

			$count++;
		}
		$tocWrapper->appendChild( $tocList );
		$tocDocument->appendChild( $tocWrapper );

		return $tocDocument;
	}
}
