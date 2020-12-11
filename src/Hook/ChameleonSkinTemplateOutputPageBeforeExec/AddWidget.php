<?php

namespace BlueSpice\UEModulePDFSubpages\Hook\ChameleonSkinTemplateOutputPageBeforeExec;

use BlueSpice\Calumma\Hook\ChameleonSkinTemplateOutputPageBeforeExec;

class AddWidget extends ChameleonSkinTemplateOutputPageBeforeExec {
	/**
	 *
	 * @return bool
	 */
	protected function skipProcessing() {
		if ( !$this->getServices()->getSpecialPageFactory()->exists( 'UniversalExport' ) ) {
			return true;
		}
		$userCan = $this->getServices()->getPermissionManager()->userCan(
			'uemodulepdfsubpages-export',
			$this->skin->getUser(),
			$this->skin->getTitle()
		);
		if ( !$userCan ) {
			return true;
		}
		return false;
	}

	protected function doProcess() {
		$currentQueryParams = $this->getContext()->getRequest()->getValues();
		$currentQueryParams['ue[module]'] = 'pdf';
		$currentQueryParams['ue[subpages]'] = '1';

		$title = '';
		if ( isset( $currentQueryParams['title'] ) ) {
			$title = $currentQueryParams['title'];
			unset( $currentQueryParams['title'] );
		}
		$specialPage = $this->getServices()->getSpecialPageFactory()->getPage(
			'UniversalExport'
		);
		$contentActions = [
			'id' => 'pdf-subpages',
			'href' => $specialPage->getPageTitle( $title )->getLinkUrl( $currentQueryParams ),
			'title' => $this->msg( 'bs-uemodulepdfsubpages-widgetlink-subpages-title' )->plain(),
			'text' => $this->msg( 'bs-uemodulepdfsubpages-widgetlink-subpages-text' )->plain(),
			'class' => 'bs-ue-export-link',
			'iconClass' => 'icon-file-pdf bs-ue-export-link'
		];

		$this->template->data['bs_export_menu'][] = $contentActions;
	}

}
