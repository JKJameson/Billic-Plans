<?php
class Plans {
	public $settings = array(
		'name' => 'Plans',
		'admin_menu_category' => 'Ordering',
		'admin_menu_name' => 'Plans',
		'admin_menu_icon' => '<i class="icon-barcode"></i>',
		'description' => 'Create and configure plans which users can then order.',
	);
	function admin_area() {
		global $billic, $db;
		if (isset($_GET['Name'])) {
			$plan = $db->q('SELECT * FROM `plans` WHERE `name` = ?', urldecode($_GET['Name']));
			$plan = $plan[0];
			if (empty($plan)) {
				err('Plan does not exist');
			}
			$plan['billingcycles'] = explode(',', $plan['billingcycles']);
			$plan['options'] = json_decode($plan['options'], true);
			$billic->set_title('Admin/Plan ' . safe($plan['name']));
			echo '<h1>Plan ' . safe($plan['name']) . '</h1>';
			$orderform = $db->q('SELECT * FROM `orderforms` WHERE `id` = ?', $plan['orderform']);
			$orderform = $orderform[0];
			$imported = false;
			if (strlen($orderform['name']) == 128) {
				$imported = true;
			}
			if (isset($_POST['update'])) {
				if ($imported) {
					$_POST['orderform'] = $plan['orderform'];
				}
				if (empty($_POST['name'])) {
					$billic->error('Name can not be empty', 'name');
				} else {
					$plan_name_check = $db->q('SELECT COUNT(*) FROM `plans` WHERE `name` = ? AND `id` != ?', $_POST['name'], $plan['id']);
					if ($plan_name_check[0]['COUNT(*)'] > 0) {
						$billic->error('Name is already in use by a different plan', 'name');
					}
				}
				switch ($_POST['billingmode']) {
					case 'fixed':
						$prorata_day = 0;
					break;
					case 'prorata':
						if ($_POST['prorata_day'] < 1 || $_POST['prorata_day'] > 28) {
							$billic->error('Day must be between 1 and 28', 'prorata_day');
						}
						$prorata_day = $_POST['prorata_day'];
					break;
					default:
						$billic->error('Invalid Billing Mode', 'billingmode');
					break;
				}
				$billingcycles = '';
				if (!empty($_POST['billingcycles'])) {
					foreach ($_POST['billingcycles'] as $billingcycle) {
						$billingcycles.= $billingcycle . ',';
					}
					$billingcycles = substr($billingcycles, 0, -1);
				}
				foreach ($_POST['options'] as $option => $settings) {
					if (isset($settings['autogen']) && $settings['autogen'] == 1 && empty($settings['value'])) {
						$billic->error('Value can not be empty when Autogen is enabled');
					}
				}
				$options = json_encode($_POST['options']);
				if (empty($billic->errors)) {
					$db->q('UPDATE `plans` SET `name` = ?, `orderform` = ?, `price` = ?, `setup` = ?, `billingcycles` = ?, `billingcycledefault` = ?, `prorata_day` = ?, `options` = ?, `tax_group` = ?, `email_template_activated` = ?, `email_template_suspended` = ?, `email_template_terminated` = ?, `email_template_unsuspended` = ? WHERE `id` = ?', $_POST['name'], $_POST['orderform'], $_POST['price'], $_POST['setup'], $billingcycles, $_POST['billingcycledefault'], $prorata_day, $options, $_POST['tax_group'], $_POST['email_template_activated'], $_POST['email_template_suspended'], $_POST['email_template_terminated'], $_POST['email_template_unsuspended'], $plan['id']);
					$billic->redirect('/Admin/Plans/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			if (!$imported) {
				if (empty($orderform['module'])) {
					if (!empty($orderform['name'])) {
						$billic->errors[] = 'Invalid module for the order form. <a href="/Admin/OrderForms/Edit/' . urlencode($orderform['name']) . '/">Click here</a> to change the module.';
					}
					$orderform_vars = array();
				} else {
					$billic->module($orderform['module']);
					$orderform_vars = $billic->modules[$orderform['module']]->settings['orderform_vars'];
				}
			}
			$orderformitems = $db->q('SELECT * FROM `orderformitems` WHERE `parent` = ?', $plan['orderform']);
			$billic->show_errors();
			echo '<form method="POST"><table class="table table-striped"><tr><th colspan="2">Plan Settings</th></td></tr>';
			if (isset($_POST['name'])) {
				$plan['name'] = $_POST['name'];
			}
			echo '<tr><td width="125">Name</td><td><input type="text" class="form-control" name="name" value="' . $plan['name'] . '"></td></tr>';
			echo '<tr><td width="125">Order Form</td><td>';
			if ($imported) {
				echo 'Imported <a href="/Admin/OrderForms/Edit/' . urlencode($orderform['name']) . '/">Edit</a>';
			} else {
				echo '<select class="form-control" name="orderform">';
				$orderforms = $db->q('SELECT `id`, `name` FROM `orderforms` ORDER BY `name` ASC');
				foreach ($orderforms as $r) {
					echo '<option value="' . safe($r['id']) . '"' . ($r['id'] == $plan['orderform'] ? ' selected' : '') . '>' . safe($r['name']) . '</option>';
				}
				echo '</select>';
			}
			echo '</td></tr>';
			if (isset($_POST['price'])) $plan['price'] = $_POST['price'];
			echo '<tr><td width="125">Price</td><td>';
			if (method_exists($billic->modules[$orderform['module']], 'orderprice')) {
				echo 'The module decides the price inside the function ' . $orderform['module'] . '->orderprice($plan)';
			} else {
				echo '<div class="input-group" style="width: 200px"><span class="input-group-addon">' . get_config('billic_currency_prefix') . '</span><input type="text" class="form-control" name="price" value="' . safe($plan['price']) . '"><span class="input-group-addon">' . get_config('billic_currency_suffix') . '</span></div>';
			}
			echo '</td></tr>';
			if (isset($_POST['setup'])) $plan['setup'] = $_POST['setup'];
			echo '<tr><td>Setup</td><td>';
			if (method_exists($billic->modules[$orderform['module']], 'orderprice')) {
				echo 'The module decides the setup inside the function ' . $orderform['module'] . '->orderprice($plan)';
			} else {
				echo '<div class="input-group" style="width: 200px"><span class="input-group-addon">' . get_config('billic_currency_prefix') . '</span><input type="text" class="form-control" name="setup" value="' . safe($plan['setup']) . '"><span class="input-group-addon">' . get_config('billic_currency_suffix') . '</span></div>';
			}
			echo '</td></tr>';
			echo '<tr><td valign="top">Tax Group</td><td><select class="form-control" name="tax_group">';
			$tax_groups = $db->q('SELECT * FROM `tax_groups` ORDER BY `name` ASC');
			echo '<option>None</option>';
			foreach ($tax_groups as $group) {
				echo '<option value="' . $group['name'] . '"' . ($plan['tax_group'] == $group['name'] ? ' selected' : '') . '>' . $group['name'] . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><td valign="top">Billing Cycles</td><td>';
			if ($imported) {
				$billingcycles = $db->q('SELECT `name`,`import_hash` FROM `billingcycles` WHERE `import_hash` = ? ORDER BY `seconds` ASC', $plan['import_hash']);
			} else {
				$billingcycles = $db->q('SELECT `name` FROM `billingcycles` WHERE `import_hash` = \'\' ORDER BY `seconds` ASC');
			}
			foreach ($billingcycles as $billingcycle) {
				echo '<input type="checkbox" name="billingcycles[]" value="' . safe($billingcycle['name']) . '"' . (in_array($billingcycle['name'], $plan['billingcycles']) ? ' checked' : '') . '> ' . ($imported ? '<a href="/Admin/BillingCycles/Name/' . urlencode($billingcycle['name']) . '/ImportHash/' . urlencode($plan['import_hash']) . '/">' : '') . safe($billingcycle['name']) . ($imported ? '</a> [Imported]' : '') . '<br>';
			}
			echo '</td></tr>';
			echo '<tr><td valign="top">Default Billing Cycle</td><td><select class="form-control" name="billingcycledefault">';
			foreach ($plan['billingcycles'] as $billingcycle) {
				echo '<option value="' . $billingcycle . '"' . ($plan['billingcycledefault'] == $billingcycle ? ' selected' : '') . '>' . $billingcycle . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><td>Billing Mode</td><td>';
			if (isset($_POST['billingmode'])) {
				$plan['billingmode'] = $_POST['billingmode'];
			} else if ($plan['prorata_day'] > 0) {
				$plan['billingmode'] = 'prorata';
			}
			echo '<select class="form-control" name="billingmode" id="prorata_day_select"><option value="fixed"' . ($plan['billingmode'] == 'fixed' ? ' selected' : '') . '>Fixed Term</option><option value="prorata"' . ($plan['billingmode'] == 'prorata' ? ' selected' : '') . '>Pro Rata</option></select>';
			echo '</td></tr>';
			if (isset($_POST['prorata_day'])) {
				$plan['prorata_day'] = $_POST['prorata_day'];
			}
			echo '<tr id="prorata_day_row" ' . ($plan['billingmode'] == 'prorata' ? '' : ' style="display:none"') . '><td>Pro Rata Day</td><td><input type="text" class="form-control" name="prorata_day" value="' . $plan['prorata_day'] . '" style="width: 50px"></td></tr>';
			$link = 'http' . (get_config('billic_ssl') == 1 ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/User/Order/Plan/' . urlencode($plan['name']) . '/';
			echo '<tr><td valign="top">Order Link</td><td><a href="' . $link . '">' . $link . '</a></td></tr>';
			echo '</table><br>';
			echo '<table class="table table-striped">';
			echo '<tr><th colspan="2">Email Templates</th></tr>';
			echo '<tr><td width="125">Activated</td><td><select class="form-control" name="email_template_activated">';
			$templates = $db->q('SELECT `id`, `subject` FROM `emailtemplates` ORDER BY `subject` ASC');
			foreach ($templates as $template) {
				echo '<option value="' . $template['id'] . '"' . ($template['id'] == $plan['email_template_activated'] ? ' selected' : '') . '>' . safe($template['subject']) . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><td width="125">Suspended</td><td><select class="form-control" name="email_template_suspended">';
			$templates = $db->q('SELECT `id`, `subject` FROM `emailtemplates` ORDER BY `subject` ASC');
			foreach ($templates as $template) {
				echo '<option value="' . $template['id'] . '"' . ($template['id'] == $plan['email_template_suspended'] ? ' selected' : '') . '>' . safe($template['subject']) . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><td width="125">Terminated</td><td><select class="form-control" name="email_template_terminated">';
			$templates = $db->q('SELECT `id`, `subject` FROM `emailtemplates` ORDER BY `subject` ASC');
			foreach ($templates as $template) {
				echo '<option value="' . $template['id'] . '"' . ($template['id'] == $plan['email_template_terminated'] ? ' selected' : '') . '>' . safe($template['subject']) . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><td width="125">Unsuspended</td><td><select class="form-control" name="email_template_unsuspended">';
			$templates = $db->q('SELECT `id`, `subject` FROM `emailtemplates` ORDER BY `subject` ASC');
			foreach ($templates as $template) {
				echo '<option value="' . $template['id'] . '"' . ($template['id'] == $plan['email_template_unsuspended'] ? ' selected' : '') . '>' . safe($template['subject']) . '</option>';
			}
			echo '</select></td></tr>';
			echo '</table><br>';
?><script>$( "#prorata_day_select" ).change(function() { if ($( "#prorata_day_select" ).value == "prorata") { $( "#prorata_day_row" ).toggle(); } else { $( "#prorata_day_row" ).toggle(); } });</script><?php
			if ($imported) {
				echo '<p>This is an imported plan. <a href="/Admin/OrderForms/Edit/' . urlencode($plan['import_hash']) . '/">Click here to modify the Order Form.</a></p>';
			} else {
				echo '<table class="table table-striped"><tr><th width="1">Show</th><th width="1">Autogen</th><th width="10">Module Variable</th><th width="150">Label</th><th width="150">Value</th></td></tr>';
				if (empty($orderform_vars)) {
					echo '<tr><td colspan="20">There are no order form variables defined in the module "' . $orderform['module'] . '". You can change the module in the Order Form.</td></tr>';
				} else {
					foreach ($orderform_vars as $var) {
						$disabled = false;
						foreach ($orderformitems as $item) {
							if ($item['module_var'] == $var) {
								$disabled = true;
								break;
							}
						}
						echo '<tr' . ($disabled === true ? ' style="opacity:0.6"' : '') . '><td><input type="checkbox" name="options[' . $var . '][show]" value="1"' . ($plan['options'][$var]['show'] == 1 ? ' checked' : '') . ($disabled === true ? ' disabled' : '') . '></td><td><input type="checkbox" name="options[' . $var . '][autogen]" value="1"' . ($plan['options'][$var]['autogen'] == 1 ? ' checked' : '') . ($disabled === true ? ' disabled' : '') . '></td><td>' . $var . '</td>';
						if ($disabled === true) {
							echo '<td colspan="2">Unavailable because this module variable is configured inside the order form.</td>';
						} else {
							echo '<td><input type="text" class="form-control" name="options[' . $var . '][label]" value="' . safe($plan['options'][$var]['label']) . '" style="width: 98%"></td><td><input type="text" class="form-control" name="options[' . $var . '][value]" value="' . safe($plan['options'][$var]['value']) . '" style="width: 98%"></td>';
						}
						echo '</tr>';
					}
					echo '</td></tr>';
				}
			}
			echo '<tr><td colspan="5" align="center"><input type="submit" class="btn btn-success" name="update" value="Update &raquo;"></td></tr></table></form>';
			return;
		}
		if (isset($_GET['New'])) {
			$title = 'New Plan';
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			$license_data = $billic->get_license_data();
			if ($license_data['desc']!='Unlimited') {
				$lic_count = $db->q('SELECT COUNT(*) FROM `plans`');
				if ($lic_count[0]['COUNT(*)'] >= $license_data['plans']) {
					err('Unable to create a new plan because you have reached your limit. Please upgrade your Billic License.');
				}
			}
			$billic->module('FormBuilder');
			$form = array(
				'name' => array(
					'label' => 'Name',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				$plan = $db->q('SELECT * FROM `plans` WHERE `name` = ?', $_POST['name']);
				$plan = $plan[0];
				if (!empty($plan)) {
					$billic->error('A plan with that name already exists');
				}
				if (empty($billic->errors)) {
					// if there are any tax groups, get the first one available and assign it by default
					$tax_group = $db->q('SELECT * FROM `tax_groups` ORDER BY `name` ASC LIMIT 1');
					$tax_group = $tax_group[0];
					$activated_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Activated');
					$activated_id = $activated_id['id'];
					$suspended_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Suspended');
					$suspended_id = $suspended_id['id'];
					$unsuspended_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Unsuspended');
					$unsuspended_id = $unsuspended_id['id'];
					$terminated_id = $db->q('SELECT `id` FROM `emailtemplates` WHERE `default` = ?', 'Service Terminated');
					$terminated_id = $terminated_id['id'];
					$db->insert('plans', array(
						'name' => $_POST['name'],
						'tax_group' => $tax_group['name'],
						'email_template_activated' => $activated_id,
						'email_template_suspended' => $suspended_id,
						'email_template_unsuspended' => $unsuspended_id,
						'email_template_terminated' => $terminated_id,
					));
					$billic->redirect('/Admin/Plans/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Clone'])) {
			$name = urldecode($_GET['Clone']);
			$title = 'Clone Plan ' . safe($name);
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			$license_data = $billic->get_license_data();
			if ($license_data['desc']!='Unlimited') {
				$lic_count = $db->q('SELECT COUNT(*) FROM `plans`');
				if ($lic_count[0]['COUNT(*)'] >= $license_data['plans']) {
					err('Unable to create a new plan because you have reached your limit. Please upgrade your Billic License.');
				}
			}
			$billic->module('FormBuilder');
			$form = array(
				'name' => array(
					'label' => 'New Name',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				$plan = $db->q('SELECT * FROM `plans` WHERE `name` = ?', $_POST['name']);
				$plan = $plan[0];
				if (!empty($plan)) {
					$billic->error('A plan with that name already exists');
				}
				if (empty($billic->errors)) {
					$plan = $db->q('SELECT * FROM `plans` WHERE `name` = ?', $name);
					$plan = $plan[0];
					if (empty($plan)) {
						err('The original plan "' . $name . '" does not exist');
					}
					unset($plan['id']);
					$plan['name'] = $_POST['name'];
					$newid = $db->insert('plans', $plan);
					$billic->redirect('/Admin/Plans/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Import'])) {
			$title = 'Import Plan';
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			$license_data = $billic->get_license_data();
			if ($license_data['desc']!='Unlimited') {
				$lic_count = $db->q('SELECT COUNT(*) FROM `plans`');
				if ($lic_count[0]['COUNT(*)'] >= $license_data['plans']) {
					err('Unable to create a new plan because you have reached your limit. Please upgrade your Billic License.');
				}
			}
			$billic->module('FormBuilder');
			$form = array(
				'domain' => array(
					'label' => 'Billic Domain',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
				'email' => array(
					'label' => 'User Email',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
				'apikey' => array(
					'label' => 'API Key',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				if (isset($_POST['markup'])) {
					if (empty($_POST['plans'])) {
						$billic->error('You did not select any plans to import');
					} else foreach ($_POST['plans'] as $i => $plan) {
						$plan = urldecode($plan);
						$new_name = $_POST['names'][$i];
						if (empty($new_name)) {
							$billic->error('New name not specified for "' . safe($plan) . '"');
							continue;
						}
						if (!empty($billic->errors)) {
							continue;
						}
						// request export from remote billic
						$options = array(
							CURLOPT_URL => 'https://' . $_POST['domain'] . '/api/',
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_HEADER => false,
							CURLOPT_FOLLOWLOCATION => true,
							CURLOPT_ENCODING => "",
							CURLOPT_AUTOREFERER => true,
							CURLOPT_CONNECTTIMEOUT => 10,
							CURLOPT_TIMEOUT => 300,
							CURLOPT_MAXREDIRS => 10,
							CURLOPT_SSL_VERIFYHOST => false,
							CURLOPT_SSL_VERIFYPEER => false,
							CURLOPT_POST => true,
							CURLOPT_POSTFIELDS => array(
								'email' => $_POST['email'],
								'apikey' => $_POST['apikey'],
								'module' => 'Order',
								'request' => 'export_plan',
								'plan' => $plan,
							) ,
						);
						$ch = curl_init();
						curl_setopt_array($ch, $options);
						$data = curl_exec($ch);
						if ($data === false) {
							$billic->error('Curl error: ' . curl_error($ch));
							continue;
						}
						if (empty($billic->errors)) {
							$data = trim($data);
							$test = json_decode($data, true);
							if ($test === null) {
								$billic->error('JSON error: ' . $data);
								continue;
							} else if (isset($test['error'])) {
								$billic->error($test['error']);
								continue;
							}
						}
						if (empty($billic->errors)) {
							$plan = $test;
							unset($test, $data);
							if (empty($plan['hash'])) {
								$billic->error('The plan "' . safe($plan['name']) . '" was not sent with a hash value.');
								continue;
							}
							$importedplan = $db->q('SELECT `name` FROM `plans` WHERE `import_hash` = ?', $plan['hash']);
							$importedplan = $importedplan[0];
							if (!empty($importedplan)) {
								echo 'Warning: The plan "' . safe($plan['name']) . '" has already been imported as "' . safe($importedplan['name']) . '".';
								continue;
							}
							$orderform_title = $plan['orderform']['title'];
							if (empty($orderform_title)) {
								$orderform_title = $plan['orderform']['name'];
							}
							$db->q('DELETE FROM `orderforms` WHERE `name` = ?', $plan['hash']);
							$db->q('DELETE FROM `billingcycles` WHERE `import_hash` = ?', $plan['hash']);
							$orderform_id = $db->insert('orderforms', array(
								'name' => $plan['hash'],
								'module' => 'RemoteBillicService',
								'title' => $orderform_title,
							));
							$import_data = array(
								'domain' => $_POST['domain'],
								'email' => $_POST['email'],
								'apikey' => $_POST['apikey'],
							);
							$billingcycles_list = '';
							foreach ($plan['billingcycles'] as $billingcycle) {
								unset($billingcycle['id']);
								$billingcycle['import_hash'] = $plan['hash'];
								$db->insert('billingcycles', $billingcycle);
								$billingcycles_list.= $billingcycle['name'] . ',';
							}
							$billingcycles_list = substr($billingcycles_list, 0, -1);
							// if there are any tax groups, get the first one available and assign it by default
							$tax_group = $db->q('SELECT * FROM `tax_groups` ORDER BY `name` ASC LIMIT 1');
							$tax_group = $tax_group[0];
							$plan_id = $db->insert('plans', array(
								'name' => $new_name,
								'orderform' => $orderform_id,
								'price' => ($plan['price'] / 100) * (100 + $_POST['markup']),
								'setup' => ($plan['setup'] / 100) * (100 + $_POST['markup']),
								'billingcycles' => $billingcycles_list,
								'billingcycledefault' => $plan['billingcycledefault'],
								'prorata_day' => $plan['prorata_day'],
								'import_hash' => $plan['hash'],
								'import_data' => json_encode($import_data) ,
								'tax_group' => $tax_group['name'],
							));
							if (is_array($plan['items'])) {
								foreach ($plan['items'] as $item) {
									$item_id = $db->insert('orderformitems', array(
										'name' => $item['name'],
										'type' => $item['type'],
										'parent' => $orderform_id,
										'price' => ($item['price'] / 100) * (100 + $_POST['markup']),
										'order' => $item['order'],
										'min' => $item['min'],
										'max' => $item['max'],
										'step' => $item['step'],
										'module_var' => $item['name'],
										'requirement' => $item['requirement'],
									));
									if (is_array($item['options'])) {
										foreach ($item['options'] as $option) {
											$option_id = $db->insert('orderformoptions', array(
												'parent' => $item_id,
												'name' => $option['name'],
												'type' => $option['type'],
												'order' => $option['order'],
												'price' => $option['price'],
												'setup' => $option['setup'],
												'module_var' => $option['name'],
											));
										}
									}
								}
							}
							echo 'The plan "' . safe($plan['name']) . '" was successfully imported as "' . safe($new_name) . '".<br><br>';
						}
					}
					$billic->show_errors();
					unset($billic->errors);
				}
				if (empty($billic->errors)) {
					$options = array(
						CURLOPT_URL => 'https://' . $_POST['domain'] . '/api/',
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HEADER => false,
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_ENCODING => "",
						CURLOPT_AUTOREFERER => true,
						CURLOPT_CONNECTTIMEOUT => 10,
						CURLOPT_TIMEOUT => 300,
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_SSL_VERIFYHOST => false,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_POST => true,
						CURLOPT_POSTFIELDS => array(
							'email' => $_POST['email'],
							'apikey' => $_POST['apikey'],
							'module' => 'Order',
							'request' => 'list_plans',
						) ,
					);
					$ch = curl_init();
					curl_setopt_array($ch, $options);
					$data = curl_exec($ch);
					if ($data === false) {
						$billic->error('Curl error: ' . curl_error($ch));
					}
				}
				if (empty($billic->errors)) {
					$data = trim($data);
					$test = json_decode($data, true);
					if ($test === null) {
						$billic->error('JSON error: ' . $data);
					} else if (isset($test['error'])) {
						$billic->error($test['error']);
					}
				}
				if (empty($billic->errors)) {
					$plans = $test;
					unset($test, $data);
					if (empty($plans)) {
						$billic->error('There are no plans available to import from ' . $_POST['domain']);
					}
				}
				if (empty($billic->errors)) {
					echo '<form method="POST">';
					echo '<input type="hidden" name="domain" value="' . safe($_POST['domain']) . '">';
					echo '<input type="hidden" name="email" value="' . safe($_POST['email']) . '">';
					echo '<input type="hidden" name="apikey" value="' . safe($_POST['apikey']) . '">';
					echo '<table class="table table-striped">';
					echo '<tr><th colspan="4">Select Plans to import from ' . safe($_POST['domain']) . '</th></tr>';
					$i = 0;
					foreach ($plans as $plan) {
						echo '<tr><td valign="top"><input type="checkbox" name="plans[' . $i . ']" value="' . urlencode($plan['name']) . '"' . (isset($_POST['plans'][$i]) ? ' checked' : '') . '></td><td valign="top"><u>Original name</u><br>' . safe($plan['name']) . '<br><br><u>New Name</u><br><input type="text" class="form-control" name="names[' . $i . ']" value="' . safe($_POST['names'][$i]) . '"></td><td valign="top">';
						foreach ($plan['billingcycles'] as $billingcycle) {
							echo $billingcycle['name'] . ' (' . $billingcycle['seconds'] . ')<br>';
						}
						echo '</td><td valign="top">';
						if (is_array($plan['items'])) {
							foreach ($plan['items'] as $item) {
								echo $item['name'] . ' (' . $item['type'] . ') - ' . $item['price'] . ' ('.$item['setup'].' setup)<br>';
								if (is_array($item['options'])) {
									foreach ($item['options'] as $option) {
										echo '&nbsp;&nbsp;&nbsp;&nbsp;&raquo; ' . $option['name'] . ' - ' . $option['price'] . ' - ('.$item['setup'].' setup)<br>';
									}
								}
							}
						}
						echo '</td></tr>';
						$i++;
					}
					echo '<tr><td colspan="4">Markup: <input type="text" class="form-control" name="markup" value="25" style="width: 40px">% <input type="submit" class="btn btn-success" name="Continue" value="Import &raquo;"></td></tr>';
					echo '</table>';
					echo '</form>';
					return;
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Delete'])) {
			$plan = $db->q('SELECT * FROM `plans` WHERE `name` = ?', urldecode($_GET['Delete']));
			$plan = $plan[0];
			if (empty($plan)) {
				err('Plan does not exist');
			}
			$check = $db->q('SELECT COUNT(*) FROM `services` WHERE `packageid` = ? AND `domainstatus` = ?', $plan['id'], 'Active');
			$check = $check[0]['COUNT(*)'];
			if ($check > 0) {
				$billic->error('Unable to delete the plan "' . safe($plan['name']) . '" because it has ' . $check . ' active services');
			}
			$check = $db->q('SELECT COUNT(*) FROM `services` WHERE `packageid` = ? AND `domainstatus` = ?', $plan['id'], 'Suspended');
			$check = $check[0]['COUNT(*)'];
			if ($check > 0) {
				$billic->error('Unable to delete the plan "' . safe($plan['name']) . '" because it has ' . $check . ' suspended services');
			}
			if (empty($billic->errors)) {
				$orderform = $db->q('SELECT `name`, `module` FROM `orderforms` WHERE `id` = ?', $plan['orderform']);
				$orderform = $orderform[0];
				if (strlen($orderform['name']) > 127) {
					// imported order form
					$billic->module('OrderForms');
					$billic->modules['OrderForms']->delete($orderform['name']);
				}
				$db->q('DELETE FROM `plans` WHERE `id` = ?', $plan['id']);
				$billic->status = 'deleted';
			}
		}
		$total = $db->q('SELECT COUNT(*) FROM `plans`');
		$total = $total[0]['COUNT(*)'];
		$pagination = $billic->pagination(array(
			'total' => $total,
		));
		echo $pagination['menu'];
		$plans = $db->q('SELECT * FROM `plans` ORDER BY `name` ASC LIMIT ' . $pagination['start'] . ',' . $pagination['limit']);
		$billic->show_errors();
		$billic->set_title('Admin/Plans');
		echo '<h1><i class="icon-barcode"></i> Plans</h1>';
		echo '<a href="New/" class="btn btn-success"><i class="icon-plus"></i> New Plan</a> <a href="Import/" class="btn btn-success"><i class="icon-exchange"></i> Import Plan</a>';
		echo '<div style="float: right;padding-right: 40px;">Showing ' . $pagination['start_text'] . ' to ' . $pagination['end_text'] . ' of ' . $total . ' Plans</div>';
		echo '<table class="table table-striped"><tr><th>Name</th><th>Show</th><th>Module</th><th>Order Form</th><th style="width:20%">Actions</th></tr>';
		if (empty($plans)) {
			echo '<tr><td colspan="20">No Plans matching filter.</td></tr>';
		}
		foreach ($plans as $plan) {
			$orderform = $db->q('SELECT `name`, `module` FROM `orderforms` WHERE `id` = ?', $plan['orderform']);
			$orderform = $orderform[0];
			echo '<tr><td><a href="/Admin/Plans/Name/' . urlencode($plan['name']) . '/">' . safe($plan['name']) . '</a></td><td>' . ($plan['hide'] == 1 ? '<i class="icon-remove"></i>' : '<i class="icon-check-mark"></i>') . '</td><td>' . $orderform['module'] . '</td><td>';
			if (strlen($orderform['name']) > 127) {
				// imported order form
				echo '<a href="/Admin/OrderForms/Edit/' . urlencode($orderform['name']) . '/">Imported</a>';
			} else {
				echo safe($orderform['name']);
			}
			echo '</td><td>';
			echo '<a href="/Admin/Plans/Name/' . urlencode($plan['name']) . '/" class="btn btn-primary btn-xs"><i class="icon-edit-write"></i> Edit</a>';
			echo '&nbsp;<a href="/Admin/Plans/Clone/' . urlencode($plan['name']) . '/" class="btn btn-info btn-xs"><i class="icon-code-fork"></i> Clone</a>';
			echo '&nbsp;<a href="/Admin/Plans/Delete/' . urlencode($plan['name']) . '/" class="btn btn-danger btn-xs" title="Delete" onClick="return confirm(\'Are you sure you want to delete?\');"><i class="icon-remove"></i> Delete</a>';
			echo '</td></tr>';
		}
		echo '</table>';
	}
}
