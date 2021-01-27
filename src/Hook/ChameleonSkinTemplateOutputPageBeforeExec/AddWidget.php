<?php

namespace BlueSpice\UEModulePDFSubpages\Hook\ChameleonSkinTemplateOutputPageBeforeExec;

use BlueSpice\Hook\ChameleonSkinTemplateOutputPageBeforeExec;
use BlueSpice\UniversalExport\ModuleFactory;

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
		/** @var ModuleFactory $moduleFactory */
		$moduleFactory = $this->getServices()->getService(
			'BSUniversalExportModuleFactory'
		);
		$module = $moduleFactory->newFromName( 'pdf' );
		$contentActions = [
			'id' => 'pdf-subpages',
			'href' => $module->getExportLink( $this->getContext()->getRequest(),  [
				'ue[subpages]' => '1',
			] ),
			'title' => $this->msg( 'bs-uemodulepdfsubpages-widgetlink-subpages-title' )->plain(),
			'text' => $this->msg( 'bs-uemodulepdfsubpages-widgetlink-subpages-text' )->plain(),
			'class' => 'bs-ue-export-link',
			'iconClass' => 'icon-file-pdf bs-ue-export-link'
		];

		$this->template->data['bs_export_menu'][] = $contentActions;
	}

}
