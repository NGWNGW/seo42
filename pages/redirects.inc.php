<?php
$func = rex_request('func', 'string');
$redirect_id = rex_request('redirect_id', 'int');

// delete redirect (when link clicked from rex list)
if($func == 'delete' && $redirect_id > 0) {
	$sql = rex_sql::factory();
	//  $sql->debugsql = true;
	$sql->setTable($REX['TABLE_PREFIX'] . 'redirects');
	$sql->setWhere('id='. $redirect_id . ' LIMIT 1');

	if ($sql->delete()) {
		echo rex_info($I18N->msg('seo42_redirect_deleted'));
	} else {
		echo rex_warning($sql->getErrro());
	}

	seo42_utils::updateRedirectsFile(false);
	
	$func = '';
}

// add or edit redirect (after form submit)
rex_register_extension('REX_FORM_SAVED', function ($params) {
	global $REX;

	$redirectId = seo42_utils::getLastInsertedId($params['sql']);

	$maxAge =  intval($REX['ADDON']['seo42']['settings']['redirects_max_age']);
	$createDate = seo42_utils::getDate();
	$expireDate = seo42_utils::getDate($maxAge);

	if (!seo42_utils::redirectsDoExpire()) {
		$expireDate = 0;
	}

	$sql = rex_sql::factory();
	$sql->setDebug(true);
	$sql->setQuery('UPDATE `' . $REX['TABLE_PREFIX'] . 'redirects` SET create_date = "' . $createDate . '", expire_date = "' . $expireDate . '" WHERE id = ' . $redirectId);

	seo42_utils::updateRedirectsFile(false);
	
	// use exit statement, if you want to debug
	return true;
});

// delete redirect (after form submit)
rex_register_extension('REX_FORM_DELETED', function ($params) {
	global $REX;

	seo42_utils::updateRedirectsFile(false);

	// use exit statement, if you want to debug
	return true;
});

// output
echo '<div class="rex-addon-output-v2">';

if ($func == '') {
	// rex list
	$query = 'SELECT * FROM ' . $REX['TABLE_PREFIX'] . 'redirects ORDER BY id';

	$list = rex_list::factory($query, 9999);
	$list->setNoRowsMessage($I18N->msg('seo42_redirect_no_sytles_available'));
	$list->setCaption($I18N->msg('seo42_redirect_list_of_redirects'));
	$list->addTableAttribute('summary', $I18N->msg('seo42_redirect_list_of_redirects'));

	$list->setColumnLabel('id', $I18N->msg('seo42_redirect_id'));
	$list->setColumnLabel('source_url', $I18N->msg('seo42_redirect_source_url'));
	$list->setColumnLabel('target_url', $I18N->msg('seo42_redirect_target_url'));

	if (seo42_utils::redirectsDoExpire()) {
		$list->addTableColumnGroup(array(40, 40, 300, 300, 100, 80, 80, 80));

		$list->removeColumn('create_date');
		$list->setColumnLabel('expire_date', $I18N->msg('seo42_redirect_expire_date'));
	} else {
		$list->addTableColumnGroup(array(40, 40, 300, 300, 80, 80, 80));

		$list->removeColumn('create_date');
		$list->removeColumn('expire_date');
	}

	if (rex_request('sort') == '') {
		$list->setColumnSortable('id', 'desc');
	} else {
		$list->setColumnSortable('id', 'asc');
	}

	$list->setColumnSortable('source_url', 'asc');
	$list->setColumnSortable('target_url', 'asc');

	$list->setColumnFormat('source_url', 'custom',  create_function(
		'$params',
		'global $REX;

		$list = $params["list"];

		return urldecode($list->getValue("source_url"));'	
	));

	$list->setColumnFormat('target_url', 'custom',  create_function(
		'$params',
		'global $REX;

		$list = $params["list"];

		return urldecode($list->getValue("target_url"));'	
	));

	// icon column
	$thIcon = '<a class="rex-i-element rex-i-generic-add" href="'. $list->getUrl(array('func' => 'add')) .'"><span class="rex-i-element-text">' . $I18N->msg('seo42_redirect_create') . '</span></a>';
	$tdIcon = '<span class="rex-i-element rex-i-generic"><span class="rex-i-element-text">###name###</span></span>';
	$list->addColumn($thIcon, $tdIcon, 0, array('<th class="rex-icon">###VALUE###</th>','<td class="rex-icon">###VALUE###</td>'));
	$list->setColumnParams($thIcon, array('func' => 'edit', 'redirect_id' => '###id###'));

	// functions column spans 2 data-columns
	$funcs = $I18N->msg('seo42_redirect_functions');
	$list->addColumn($funcs, $I18N->msg('seo42_redirect_test'), -1, array('<th colspan="3">###VALUE###</th>','<td>###VALUE###</td>'));
	$list->setColumnFormat($funcs, 'custom', create_function(
		'$params',
		'global $REX, $I18N;

		$list = $params["list"];

		$query = \'SELECT source_url FROM \' . $REX[\'TABLE_PREFIX\'] . \'redirects WHERE id=\' . $list->getValue("id");
		$sql = rex_sql::factory();
		$sql->setQuery($query);

		if ($sql->getRows() == 0) {
			$link = "#";
		} else {
			$link = seo42::getServerProtocol() . "://" . seo42::getServerWithSubDir() . "/" . ltrim($sql->getValue(\'source_url\'), \'/\');
		}

		return "<a href=\"$link\" target=\"_blank\">" . $I18N->msg(\'seo42_redirect_test\') . "</a>";'	
	));

	$edit = 'editCol';
	$list->addColumn($edit, $I18N->msg('seo42_redirect_edit'), -1, array('','<td>###VALUE###</td>'));
	$list->setColumnParams($edit, array('func' => 'edit', 'redirect_id' => $redirect_id, 'redirect_id' => '###id###'));

	$delete = 'deleteCol';
	$list->addColumn($delete, $I18N->msg('seo42_redirect_delete'), -1, array('','<td>###VALUE###</td>'));
	$list->setColumnParams($delete, array('redirect_id' => '###id###', 'func' => 'delete'));
	$list->addLinkAttribute($delete, 'onclick', 'return confirm(\'' . $I18N->msg('seo42_redirect_delete_confirm') . '\');');

	$list->show();
} elseif ($func == 'add' || $func == 'edit' && $redirect_id > 0) {
	// rex form
	if ($func == 'edit') {
		$formLabel = $I18N->msg('seo42_redirect_redirect_edit');
	} elseif ($func == 'add') {
		$formLabel = $I18N->msg('seo42_redirect_redirect_add');
	}

	$form = rex_form::factory($REX['TABLE_PREFIX'] . 'redirects', $formLabel, 'id=' . $redirect_id);
	$form->addErrorMessage(REX_FORM_ERROR_VIOLATE_UNIQUE_KEY, $I18N->msg('seo42_redirect_redirect_exists'));

	// source url
	$field =& $form->addTextField('source_url'); 
	$field->setLabel($I18N->msg('seo42_redirect_source_url'));
	$field->setAttribute('id', 'source-url');

	// target url
	$field =& $form->addTextField('target_url'); 
	$field->setLabel($I18N->msg('seo42_redirect_target_url'));  
	$field->setAttribute('id', 'target-url');

	if (seo42_utils::redirectsDoExpire() && $func == 'edit') {
		$field =& $form->addReadOnlyField('create_date'); 
		$field->setLabel($I18N->msg('seo42_redirect_create_date')); 

		$field =& $form->addTextField('expire_date'); 
		$field->setLabel($I18N->msg('seo42_redirect_expire_date'));  
	}

	if ($func == 'edit') {
		$form->addParam('redirect_id', $redirect_id);
	} elseif ($func == 'add') {
		// do nothing
	}

	// sort params to keep sort settings of user in list mode
	if (rex_request('sort') != '') {
		$form->addParam('sort', rex_request('sort'));
	}

	if (rex_request('sorttype') != '') {
		$form->addParam('sorttype', rex_request('sorttype'));
	}

	$form->show();
}

echo '</div>';
?>

<?php if ($REX['ADDON']['seo42']['settings']['redirects_compact_view']) { ?>
<style type="text/css">
#rex-page-seo42 table {
	width: auto;
	table-layout: fixed;
}

#rex-page-seo42 table td:nth-child(3),
#rex-page-seo42 table td:nth-child(4),
#rex-page-seo42 table td:nth-child(5) {
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	max-width: 180px;
}
</style>
<?php } ?>

<script type="text/javascript">
jQuery(document).ready( function() {
	var cancelClicked = false;

	// focus
	if (jQuery('#source-url').val() === '') {
		jQuery('#source-url').focus();
	}

	jQuery('#rex_redirects_Redirect_anlegen_abort').click(function(e) {
		cancelClicked = true;
	});

	jQuery('#rex-addon-editmode form').submit(function(e) {
		if (cancelClicked) {
			return true;
		}

		var pat = /^https?:\/\//i;
		var sourceUrl = jQuery('#source-url').val();
		var targetUrl = jQuery('#target-url').val();

		if (sourceUrl.charAt(0) == '/') {
			if (pat.test(targetUrl) || targetUrl.charAt(0) == '/') {
				return true;
			} else {
				alert('<?php echo $I18N->msg('seo42_redirect_targeturl_alert'); ?>');
			}
		} else {
			alert('<?php echo $I18N->msg('seo42_redirect_sourceurl_alert'); ?>');
		}

		return false;
	});

	jQuery('#rex-page-seo42 table td:nth-child(3),#rex-page-seo42 table td:nth-child(4)').each(function (index) {
		var $this = jQuery(this);
		var titleVal = $this.text();

		if (titleVal != '') {
			$this.attr('title', jQuery.trim(titleVal));
		}
	});
});
</script>

