<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include('./include/auth.php');

define('MAX_DISPLAY_PAGES', 21);

$actions = array('install' => 'Install',
	'enable' => 'Enable',
	'disable' => 'Disable',
	'uninstall' => 'Uninstall',
//	'check' => 'Check'
);

$status_names = array(
	-2 => 'Disabled',
	-1 => 'Active',
	0 => 'Not Installed',
	1 => 'Active',
	2 => 'Awaiting Configuration',
	3 => 'Awaiting Upgrade',
	4 => 'Installed'
);

/* get the comprehensive list of plugins */
$pluginslist = retrieve_plugin_list();

/* Check to see if we are installing, etc... */
$modes = array('installold', 'uninstallold', 'install', 'uninstall', 'disable', 'enable', 'check', 'moveup', 'movedown');

if (isset($_REQUEST['mode']) && in_array($_REQUEST['mode'], $modes)  && isset($_REQUEST['id'])) {
	input_validate_input_regex(get_request_var_request('id'), '^([a-zA-Z0-9]+)$');

	$mode = $_REQUEST['mode'];
	$id   = sanitize_search_string($_REQUEST['id']);

	switch ($mode) {
		case 'installold':
			if (!in_array($id, $plugins_integrated)) {
				api_plugin_install_old($id);
			}
			header('Location: plugins.php');
			exit;
			break;
		case 'uninstallold':
			api_plugin_uninstall_old($id);
			header('Location: plugins.php');
			exit;
			break;
		case 'install':
			if (!in_array($id, $plugins_integrated)) {
				api_plugin_install($id);
			}

			if ($_SESSION['sess_plugins_state'] != '-3') {
				header('Location: plugins.php?state=5');
			}else{
				header('Location: plugins.php');
			}
			exit;
			break;
		case 'uninstall':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_uninstall($id);
			header('Location: plugins.php');
			exit;
			break;
		case 'disable':
			if (!in_array($id, $pluginslist)) break;
			api_plugin_disable($id);
			header('Location: plugins.php');
			exit;
			break;
		case 'enable':
			if (!in_array($id, $pluginslist)) break;
			if (!in_array($id, $plugins_integrated)) {
				api_plugin_enable($id);
			}
			header('Location: plugins.php');
			exit;
			break;
		case 'check':
			if (!in_array($id, $pluginslist)) break;
			break;
		case 'moveup':
			if (!in_array($id, $pluginslist)) break;
			if (in_array($id, $plugins_integrated)) break;
			api_plugin_moveup($id);
			header('Location: plugins.php');
			exit;
			break;
		case 'movedown':
			if (!in_array($id, $pluginslist)) break;
			if (in_array($id, $plugins_integrated)) break;
			api_plugin_movedown($id);
			header('Location: plugins.php');
			exit;
			break;
	}
}

function retrieve_plugin_list () {
	$pluginslist = array();
	$temp = db_fetch_assoc('SELECT directory FROM plugin_config ORDER BY name');
	foreach ($temp as $t) {
		$pluginslist[] = $t['directory'];
	}
	return $pluginslist;
}

top_header();

update_show_current();

bottom_footer();

function api_plugin_install_old ($plugin) {
	global $config;
	if (!file_exists($config['base_path'] . "/plugins/$plugin/setup.php")) {
		return false;
	}
	$oldplugins = read_config_option('oldplugins');
	if (strlen(trim($oldplugins))) {
	$oldplugins = explode(',', $oldplugins);
	}else{
		$oldplugins = array();
	}
	if (!in_array($plugin, $oldplugins)) {
		include_once($config['base_path'] . "/plugins/$plugin/setup.php");
		$function = 'plugin_init_' . $plugin;
		if (function_exists($function)){
			$oldplugins[] = $plugin;
			$oldplugins   = implode(',', $oldplugins);
			set_config_option('oldplugins', $oldplugins);
			unset($_SESSION['sess_config_array']['oldplugins']);
			return true;
		} else {
			return false;
		}
	}
	return false;
}

function api_plugin_uninstall_old ($plugin) {
	global $config;
	$oldplugins = read_config_option('oldplugins');
	if (strlen(trim($oldplugins))) {
	$oldplugins = explode(',', $oldplugins);
	}else{
		$oldplugins = array();
	}
	if (!empty($oldplugins)) {
		if (in_array($plugin, $oldplugins)) {
			for ($a = 0; $a < count($oldplugins); $a++) {
				if ($oldplugins[$a] == $plugin) {
					unset($oldplugins[$a]);
					break;
				}
			}
			$oldplugins = implode(',', $oldplugins);
			set_config_option('oldplugins', $oldplugins);
			unset($_SESSION['sess_config_array']['oldplugins']);
			return true;
		}
	}
	return false;
}

function plugins_temp_table_exists($table) {
	return sizeof(db_fetch_row("SHOW TABLES LIKE '$table'"));
}

function plugins_load_temp_table() {
	global $config, $plugins, $plugins_integrated;

	$pluginslist = retrieve_plugin_list();

	if (isset($_SESSION['plugin_temp_table'])) {
		$table = $_SESSION['plugin_temp_table'];
	}else{
		$table = 'plugin_temp_table_' . rand();
	}
	$x = 0;
	while ($x < 30) {
		if (!plugins_temp_table_exists($table)) {
			$_SESSION['plugin_temp_table'] = $table;
			db_execute("CREATE TEMPORARY TABLE IF NOT EXISTS $table LIKE plugin_config");
			db_execute("TRUNCATE $table");
			db_execute("INSERT INTO $table SELECT * FROM plugin_config");
			break;
		}else{
			$table = 'plugin_temp_table_' . rand();
		}
		$x++;
	}

	$path = $config['base_path'] . '/plugins/';
	$dh = opendir($path);
	if ($dh !== false) {
		while (($file = readdir($dh)) !== false) {
			if (is_dir("$path/$file") && file_exists("$path/$file/setup.php") && !in_array($file, $pluginslist) && !in_array($file, $plugins_integrated)) {
				include_once("$path/$file/setup.php");
				if (!function_exists('plugin_' . $file . '_install') && function_exists($file . '_version')) {
					$function = $file . '_version';
					$cinfo[$file] = $function();
					if (!isset($cinfo[$file]['author']))   $cinfo[$file]['author']   = 'Unknown';
					if (!isset($cinfo[$file]['homepage'])) $cinfo[$file]['homepage'] = 'Not Stated';
					if (isset($cinfo[$file]['webpage']))   $cinfo[$file]['homepage'] = $cinfo[$file]['webpage'];
					if (!isset($cinfo[$file]['longname'])) $cinfo[$file]['longname'] = ucfirst($file);
					$cinfo[$file]['status'] = -2;
					if (in_array($file, $plugins)) {
						$cinfo[$file]['status'] = -1;
					}
					db_execute("REPLACE INTO $table (directory, name, status, author, webpage, version)
						VALUES ('" .
							$file . "', '" .
							$cinfo[$file]['longname'] . "', '" .
							$cinfo[$file]['status']   . "', '" .
							$cinfo[$file]['author']   . "', '" .
							$cinfo[$file]['homepage'] . "', '" .
							$cinfo[$file]['version']  . "')");
					$pluginslist[] = $file;
				} elseif (function_exists('plugin_' . $file . '_install') && function_exists('plugin_' . $file . '_version')) {
					$function               = $file . '_version';
					$cinfo[$file]           = $function();
					$cinfo[$file]['status'] = 0;
					if (!isset($cinfo[$file]['author']))   $cinfo[$file]['author']   = 'Unknown';
					if (!isset($cinfo[$file]['homepage'])) $cinfo[$file]['homepage'] = 'Not Stated';
					if (isset($cinfo[$file]['webpage']))   $cinfo[$file]['homepage'] = $cinfo[$file]['webpage'];
					if (!isset($cinfo[$file]['longname'])) $cinfo[$file]['homepage'] = ucfirst($file);

					/* see if it's been installed as old, if so, remove from oldplugins array and session */
					$oldplugins = read_config_option('oldplugins');
					if (substr_count($oldplugins, $file)) {
						$oldplugins = str_replace($file, '', $oldplugins);
						$oldplugins = str_replace(',,', ',', $oldplugins);
						$oldplugins = trim($oldplugins, ',');
						set_config_option('oldplugins', $oldplugins);
						$_SESSION['sess_config_array']['oldplugins'] = $oldplugins;
					}

					db_execute("REPLACE INTO $table (directory, name, status, author, webpage, version)
						VALUES ('" .
							$file . "', '" .
							$cinfo[$file]['longname'] . "', '" .
							$cinfo[$file]['status'] . "', '" .
							$cinfo[$file]['author'] . "', '" .
							$cinfo[$file]['homepage'] . "', '" .
							$cinfo[$file]['version'] . "')");
					$pluginslist[] = $file;
				}
			}
		}
		closedir($dh);
	}

	return $table;
}

function update_show_current () {
	global $plugins, $pluginslist, $config, $status_names, $actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('state'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_plugins_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_plugins_state');
		kill_session_var('sess_plugins_sort_column');
		kill_session_var('sess_plugins_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['state']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		$_REQUEST['page'] = 1;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('filter', 'sess_plugins_filter', '');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('state', 'sess_plugins_state', '-3');
	load_current_session_value('sort_column', 'sess_plugins_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_plugins_sort_direction', 'ASC');
	load_current_session_value('page', 'sess_plugins_current_page', '1');

	$table = plugins_load_temp_table();

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'plugins.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&state='+$('#state').val()+'&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'plugins.php?clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_plugins').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>Plugin Management</strong> (Cacti Version: ' . $config['cacti_version'] . ')', '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class="noprint">
		<form id="form_plugins" method="get" action="plugins.php">
			<table cellpadding="2" cellspacing="0">
				<tr class="noprint">
					<td width="50">
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print get_request_var_request('filter');?>">
					</td>
					<td>
						Status
					</td>
					<td>
						<select id='state' name="state" onChange="applyFilter()">
							<option value="-3"<?php if (get_request_var_request('state') == '-3') {?> selected<?php }?>>All</option>
							<option value="1"<?php if (get_request_var_request('state') == '1') {?> selected<?php }?>>Active</option>
							<option value="4"<?php if (get_request_var_request('state') == '4') {?> selected<?php }?>>Installed</option>
							<option value="5"<?php if (get_request_var_request('state') == '5') {?> selected<?php }?>>Active/Installed</option>
							<option value="0"<?php if (get_request_var_request('state') == '0') {?> selected<?php }?>>Not Installed</option>
							<option value="-1"<?php if (get_request_var_request('state') == '-1') {?> selected<?php }?>>Legacy Installed</option>
							<option value="-2"<?php if (get_request_var_request('state') == '-2') {?> selected<?php }?>>Legacy Not Intalled</option>
						</select>
					</td>
					<td>
						Plugins
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="button" id='refresh' value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='plugins.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST['filter'])) {
		$sql_where = "WHERE ($table.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}

	if ($_REQUEST['state'] > -3) {
		if ($_REQUEST['state'] == 5) {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' status IN(1,4)';
		}else{
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' status=' . $_REQUEST['state'];
		}
	}

	if (get_request_var_request('sort_column') == 'version') {
		$sortc = 'version+0';
	}else{
		$sortc = get_request_var_request('sort_column');
	}

	if (get_request_var_request('sort_column') == 'id') {
		$sortd = 'ASC';
	}else{
		$sortd = get_request_var_request('sort_direction');
	}

	if ($_REQUEST['rows'] == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var_request('rows');
	}

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM $table
		$sql_where");

	$plugins = db_fetch_assoc("SELECT *
		FROM $table
		$sql_where
		ORDER BY " . $sortc . ' ' . $sortd . '
		LIMIT ' . ($rows*(get_request_var_request('page')-1)) . ',' . $rows);

	db_execute("DROP TABLE $table");

	$nav = html_nav_bar('plugins.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 8, 'Plugins', 'page', 'main');

	print $nav;

	$display_text = array(
		'nosort' => array('display' => 'Actions', 'align' => 'left', 'sort' => '', 'tip' => 'Actions available include "Install", "Activate", "Disable", "Enable", "Uninstall".'),
		'directory' => array('display' => 'Plugin Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name for this Plugin.  The name is controlled by the direcotry it resides in.'),
		'id' => array('display' => 'Load Order', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The load order of the Plugin.  You can change the load order by first sorting by it, then moving a Plugin either up or down.'),
		'name' => array('display' => 'Plugin Description', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'A description that the Plugins author has given to the Plugin.'),
		'version' => array('display' => 'Version', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The version of this Plugin.'),
		'status' => array('display' => 'Status', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The status of this Plugin.'),
		'author' => array('display' => 'Author', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The author of this Plugin.'));

	html_header_sort($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), 1);

	$i = 0;
	if (sizeof($plugins)) {
		$j = 0;
		foreach ($plugins as $plugin) {
			if ((isset($plugins[$j+1]) && $plugins[$j+1]['status'] < 0) || (!isset($plugins[$j+1]))) {
				$last_plugin = true;
			}else{
				$last_plugin = false;
			}
			if ($plugin['status'] <= 0 || (get_request_var_request('sort_column') != 'id')) {
				$load_ordering = false;
			}else{
				$load_ordering = true;
			}

			form_alternate_row('', true);
			print format_plugin_row($plugin, $last_plugin, $load_ordering);
			$i++;

			$j++;
		}

		print $nav;
	}else{
		print '<tr><td><em>No Plugins Found</em></td></tr>';
	}

	html_end_box(false);

	print "</form>\n";
}

function format_plugin_row($plugin, $last_plugin, $include_ordering) {
	global $status_names, $config;
	static $first_plugin = true;

	$row = plugin_actions($plugin);

	$row .= "<td><a href='" . htmlspecialchars($plugin['webpage']) . "' target='_blank'><strong>" . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", ucfirst($plugin['directory'])) : ucfirst($plugin['directory'])) . '</strong></a>' . (is_dir($config['base_path'] . '/plugins/' . $plugin['directory']) ? '':' (<span class="txtErrorText">ERROR: Directory Missing</span>)') . '</td>';

	if ($include_ordering) {
		$row .= "<td style='white-space:nowrap;'>";
		if (!$first_plugin) {
			$row .= "<a href='" . htmlspecialchars('plugins.php?mode=moveup&id=' . $plugin['directory']) . "' title='Order Before Prevous Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/move_up.gif'></a>";
		}else{
			$row .= "<a href='#' title='Can NOT Reduce Load Order' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'></a>";
		}
		if (!$last_plugin) {
			$row .= "<a href='" . htmlspecialchars('plugins.php?mode=movedown&id=' . $plugin['directory']) . "' title='Order After Next Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/move_down.gif'></a>";
		}else{
			$row .= "<a href='#' title='Can Increase Load Order' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'></a>";
		}
		$row .= "</td>\n";
	}else{
		$row .= "<td></td>\n";
	}

	$row .= "<td style='white-space:nowrap;'>" . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $plugin['name']) : $plugin['name']) . "</td>\n";
	$row .= '<td>' . $plugin['version'] . "</td>\n";
	$row .= "<td style='white-space:nowrap;'>" . $status_names[$plugin['status']] . "</td>\n";
	$row .= "<td style='white-space:nowrap;'>" . $plugin['author'] . "</td>\n";
	$row .= "</tr>\n";

	if ($include_ordering) {
		$first_plugin = false;
	}

	return $row;
}

function plugin_actions($plugin) {
	$link = '<td>';
	switch ($plugin['status']) {
		case '-2': // Old PA Not Installed
			$link .= "<a href='" . htmlspecialchars('plugins.php?mode=installold&id=' . $plugin['directory']) . "' title='Install Old Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/install_icon.png'></a>";
			$link .= "<img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'>";
			break;
		case '-1':	// Old PA Currently Active
			$oldplugins = read_config_option('oldplugins');
			if (strlen(trim($oldplugins))) {
				$oldplugins = explode(',', $oldplugins);
			}else{
				$oldplugins = array();
			}
			if (in_array($plugin['directory'], $oldplugins)) {
				$link .= "<a href='" . htmlspecialchars('plugins.php?mode=uninstallold&id=' . $plugin['directory']) . "' title='Uninstall Old Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/uninstall_icon.gif'></a>";
			} else {
				$link .= "<a href='#' title='Please Uninstall from config.php' class='linkEditMain'><img style='padding:1px;' align='absmiddle' border='0' src='images/install_icon_disabled.png'></a>";
			}
			$link .= "<img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'>";
			break;
		case '0': // Not Installed
			$link .= "<a href='" . htmlspecialchars('plugins.php?mode=install&id=' . $plugin['directory']) . "' title='Install Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/install_icon.png'></a>";
			$link .= "<img style='padding:1px;' border='0' align='absmiddle' src='images/view_none.gif'>";
			break;
		case '1':	// Currently Active
			$link .= "<a href='" . htmlspecialchars('plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='Uninstall Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/uninstall_icon.gif'></a>";
			$link .= "<a href='" . htmlspecialchars('plugins.php?mode=disable&id=' . $plugin['directory']) . "' title='Disable Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/disable_icon.png'></a>";
			break;
		case '4':	// Installed but not active
			$link .= "<a href='" . htmlspecialchars('plugins.php?mode=uninstall&id=' . $plugin['directory']) . "' title='Uninstall Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/uninstall_icon.gif'></a>";
			$link .= "<a href='" . htmlspecialchars('plugins.php?mode=enable&id=' . $plugin['directory']) . "' title='Enable Plugin' class='linkEditMain'><img style='padding:1px;' border='0' align='absmiddle' src='images/enable_icon.png'></a>";
			break;
		default: // Old PIA
			$link .= "<a href='#' title='Please Install/Uninstall from config.php' class='linkEditMain'><img style='padding:1px;' align='absmiddle' border='0' src='images/install_icon_disabled.png'></a>";
			$link .= "<a href='#' title='Enabling from the UI is not supported' class='linkEditMain'><img style='padding:1px;' align='absmiddle' border='0' src='images/enable_icon_disabled.png'></a>";
			break;
	}
	$link .= '</td>';

	return $link;
}



