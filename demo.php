public function productsTPOut($id=false)
	{
		if(!CModule::IncludeModule("iblock")) return false;
		if(!CModule::IncludeModule("catalog")) return false;
		if(empty($this->iblockIdTP)) return false;

		global $USER;

		$arFilter = array("IBLOCK_ID" =>$this->iblockIdTP, ">TIMESTAMP_X"  => $this->timeback, "MODIFIED_BY" =>  $USER->GetID());
		if($id !== false) {
			$arFilter = '';
			$arFilter = array("ID" => $id, "IBLOCK_ID" => $this->iblockIdTP);
		}
		$arSelect = array(
			'IBLOCK_ID',
			'IBLOCK_SECTION_ID',
			'ID',
			'NAME',
			'CODE',
			'ACTIVE',
			'PROPERTY_E_DATE',
			'TIMESTAMP_X',
			'XML_ID',
			'PROPERTY_ORIGINAL_SEC_ID',
			'PROPERTY_COUNT',
			'PROPERTY_COUNT_OFFER'
			);

		$res = CIBlockElement::GetList(
		    Array("ID" => "ASC"),
		    $arFilter,
		    false,
		    false,
		    $arSelect
		    );
		  
		while($ob = $res->GetNextElement())
		    {
		    	$arFields = $ob->GetFields();

				$tempProd[$arFields['ID']] = $arFields['IBLOCK_SECTION_ID'];
				$arIdProdSKU[$arFields['ID']] = $arFields;
				$arIdProdSKU[$arFields['ID']]['ID'] = $arFields['XML_ID'];
				$arIdProdSKU[$arFields['ID']]['IBLOCK_SECTION_ID'] = $arFields['PROPERTY_ORIGINAL_SEC_ID_VALUE'];
				if(!empty($arFields['PROPERTY_COUNT_VALUE']))
				$count[$arFields['ID']] = $arFields['PROPERTY_COUNT_VALUE'];
				if(!empty($arFields['PROPERTY_COUNT_OFFER_VALUE']))
				$count[$arFields['ID']] = $arFields['PROPERTY_COUNT_OFFER_VALUE'];

		    }

		$this->prodIdsTPOut = array_keys($arIdProdSKU);

		if(!empty($arIdProdSKU)){

		foreach (array_keys($arIdProdSKU) as $vIdProdSKU) {

			    //Опеределяем набор  	
			    $arSetItems = CCatalogProductSet::getAllSetsByProduct(intval($vIdProdSKU), CCatalogProductSet::TYPE_GROUP);
			    if($count[$vIdProdSKU]	== '1 шт.')$arIdProdSKU[$vIdProdSKU]['SET'] = 'itemSet';
			    else $arIdProdSKU[$vIdProdSKU]['SET'] = $arSetItems;

				$mxResult = CCatalogSku::GetProductInfo($vIdProdSKU);
				if (is_array($mxResult))
				{
					$arProdandSKU[$vIdProdSKU] = $mxResult['ID'];
				}


			if(is_array($arIdProdSKU[$vIdProdSKU]['SET'])){
				foreach ($arIdProdSKU[$vIdProdSKU]['SET'] as $kSet => $vSet) {

					foreach ($vSet['ITEMS'] as $iSet) {

						$isSetItemsids[$iSet['ITEM_ID']] = $iSet;
					}

				}
			}

			if($isSetItemsids)$tempIsSetItemsids = array_keys($isSetItemsids);
			if(!empty($tempIsSetItemsids)){
				$arFilter = array("ID" => $tempIsSetItemsids);
				$arSort = array("ID" => "ASC");
				$arSelect = array("ID","XML_ID");

	            $ElList = CIBlockElement::GetList($arSort, $arFilter, false, false, $arSelect);
	            while($arElement = $ElList->GetNext())

	            {
	            	
	                $arIdProdSKU[$vIdProdSKU]['NEWSET'][$arElement['ID']] = ['ORIGINAL_ID'=>$arElement['XML_ID'], 'SET'=>$isSetItemsids[$arElement['ID']]];
	            }
	        }
		}

			if(!empty($arProdandSKU)){
				$arFilter = array("ID"  => $arProdandSKU);
				$arSelect = array(
					'ID',
					'XML_ID'
					);

				$res = CIBlockElement::GetList(
				    Array("ID" => "ASC"),
				    $arFilter,
				    false,
				    false,
				    $arSelect
				    );
				  
				while($ob = $res->GetNextElement())
				    {
				    	$arFieldsProd = $ob->GetFields();
				    	//массив из [ТП] => оригинальный ид товара
				    	foreach ($arProdandSKU as $key => $value) {
				    		if($value == $arFieldsProd['ID']){
				    			$arIdProdSKU[$key]['ORIGINAL_PRODUCT_ID'] = $arFieldsProd['XML_ID'];
				    		}
				    		
				    	}
	
				    }
			}

			$extra = $this->getExtra();
			$measure = $this->getMeasure();

			$db_res = CCatalogProduct::GetList($arSort, array("ID" => array_keys($arIdProdSKU)), false, $pageParams, array("*"));
			while ($ar_res = $db_res->Fetch())
			{
				$ar_res['MEASURE'] = $measure[$ar_res['MEASURE']];

				$arIdProdSKU[$ar_res['ID']]['PRODUCT'] = $ar_res;
			}

			$rsRatios = CCatalogMeasureRatio::getList(array(), array('PRODUCT_ID' => array_keys($arIdProdSKU)), false, false, array('PRODUCT_ID', 'RATIO'));
	        while ($arRatio = $rsRatios->Fetch()) {
	            $arIdProdSKU[$arRatio['PRODUCT_ID']]['PRODUCT']['MEASURE_RATIO'] = $arRatio;
	        }    

	      	$rsPrices = CPrice::GetListEx(array(), array('PRODUCT_ID' => array_keys($arIdProdSKU)), false, false, array('*'));

	        while ($arPrice = $rsPrices->Fetch()) {
	        	$arPrice['EXTRA_ID'] = $extra[$arPrice['EXTRA_ID']]['PERCENTAGE'];
		        	// $arPrice['EXTRA_ID'] = $extra[$arPrice['EXTRA_ID']]['NAME'];
		        	if($arPrice['CATALOG_GROUP_CODE'] == 'BASE'){
		            	$arIdProdSKU[$arPrice['PRODUCT_ID']]['PRODUCT']['PRICE'][1] = $arPrice;
		    		}elseif($arPrice['CATALOG_GROUP_CODE'] == 'RETAIL'){
		    			$arIdProdSKU[$arPrice['PRODUCT_ID']]['PRODUCT']['PRICE'][2] = $arPrice;
		    		}
	        }

			if(!empty($arIdProdSKU)) $ProductsSKU = $this->send("setTP", "products", $arIdProdSKU);

			if(!empty($ProductsSKU)){
				foreach ($ProductsSKU as $key => $value) {
					if($value["ADDID"]){
						// if(empty($arIdProdSKU[$key]['XML_ID'])){
							$PROPERTY_CODE = "ORIGINAL_ID";
							$PROPERTY_VALUE = $value["ADDID"];
							CIBlockElement::SetPropertyValuesEx($key, false, array($PROPERTY_CODE => $PROPERTY_VALUE));

							$PROPERTY_CODE = "E_DATE";
							$PROPERTY_VALUE = date('d.m.Y H:i:s', time() + 1);
							//CIBlockElement::SetPropertyValuesEx($key, false, array($PROPERTY_CODE => $PROPERTY_VALUE));
						// }
						$arLoadProductArray = array(
						  "NAME"           => htmlspecialchars_decode($arIdProdSKU[$key]['NAME']),
						  
						  "NOTSYNC"	=> true
						  );
						
						$el = new CIBlockElement;
						$res = $el->Update($key, $arLoadProductArray);
					}
				}
			}
		}

			  $pathtemp = $_SERVER["DOCUMENT_ROOT"].'/temp/';

			  $existsDir =  Directory::isDirectoryExists($pathtemp);

			  if($existsDir == true) Directory::deleteDirectory($pathtemp);
		  	
			$result = 'Выгружен элемент каталога на '.$this->siteURL;
			$errors = 'Что то пошло не так, при выгрузке элемента каталога на '.$this->siteURL;

		if(!empty($ProductsSKU)) return $ProductsSKU;/* else return $errors;*/


	}
