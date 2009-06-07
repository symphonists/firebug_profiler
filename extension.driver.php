<?php
	
	class Extension_Firebug_Profiler extends Extension {
		
		private $params = array();
		
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		public function about() {
			return array(
				'name'			=> 'Firebug Profiler',
				'version'		=> '1.1',
				'release-date'	=> '2009-05-05',
				'author'		=> array(
					'name'			=> 'Nick Dunn',
					'website'		=> 'http://airlock.com',
					'email'			=> 'nick.dunn@airlock.com'
				),
				'description'	=> 'View Symphony profile and debug information in Firebug.'
			);
		}
		
		public function install(){
			$this->_Parent->Configuration->set('enabled', 'yes', 'firebug_profiler');
			$this->_Parent->saveConfig();
		}		
		
		public function getSubscribedDelegates() {
			return array(
			
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'savePreferences'
				),							
				
				array(
					'page' => '/system/preferences/',
					'delegate' => 'CustomActions',
					'callback' => 'toggleFirebugProfiler'
				),
			
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendOutputPreGenerate',
					'callback'	=> 'frontendOutputPreGenerate'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=>	'FrontendOutputPostGenerate',
					'callback'	=>	'frontendOutputPostGenerate'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=>	'FrontendPageResolved',
					'callback'	=>	'frontendPageResolved'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=>	'FrontendParamsResolve',
					'callback'	=>	'frontendParamsResolve'
				)
			);
		}
		
		public function appendPreferences($context){

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Firebug Profiler'));			
			
			$label = Widget::Label();
			$input = Widget::Input('settings[firebug_profiler][enabled]', 'yes', 'checkbox');
			if($this->_Parent->Configuration->get('enabled', 'firebug_profiler') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' Send XML of each Data Source and Event');
			$group->appendChild($label);
						
			$group->appendChild(new XMLElement('p', 'Enable this option to send the XML fragments for each Data Source and Event to the Firebug console. This can add several KB to each page request, depending on the XML size.', array('class' => 'help')));
									
			$context['wrapper']->appendChild($group);
						
		}
		
		public function savePreferences($context){
			if(!is_array($context['settings'])) $context['settings'] = array('firebug_profiler' => array('enabled' => 'no'));
			
			elseif(!isset($context['settings']['firebug_profiler'])){
				$context['settings']['firebug_profiler'] = array('enabled' => 'no');
			}		
		}
				
		public function toggleFirebugProfiler($context){
			
			if($_REQUEST['action'] == 'toggle-firebug-profiler'){			
				$value = ($this->_Parent->Configuration->get('enabled', 'firebug_profiler') == 'no' ? 'yes' : 'no');					
				$this->_Parent->Configuration->set('enabled', $value, 'firebug_profiler');
				$this->_Parent->saveConfig();
				redirect((isset($_REQUEST['redirect']) ? URL . '/symphony' . $_REQUEST['redirect'] : $this->_Parent->getCurrentPageURL() . '/'));
			}
			
		}
		
	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/
		
		public function FrontendOutputPreGenerate($context) {
			$this->xml = $context['xml'];
		}
	
		public function frontendOutputPostGenerate($context) {

			// don't output anything for unauthenticated users
			if (!Frontend::instance()->isLoggedIn()) return;
		
			require_once(EXTENSIONS . '/firebug_profiler/lib/FirePHPCore/FirePHP.class.php');
			$firephp = FirePHP::getInstance(true);
			
			$events = Frontend::instance()->Profiler->retrieveGroup('Event');
			$datasources = Frontend::instance()->Profiler->retrieveGroup('Datasource');
			
			$xml_generation = Frontend::instance()->Profiler->retrieveByMessage('XML Generation');
			
			$dbstats = Frontend::instance()->Database->getStatistics();
			
			// Profile group
			$firephp->group('Profile', array('Collapsed' => false));
				
				$table = array();
				$table[] = array('', '');
				foreach(Frontend::instance()->Profiler->retrieveGroup('General') as $profile) {
					$table[] = array($profile[0], $profile[1] . 's');
				}
				$firephp->table('Page Building', $table);
				
				$event_total = 0;
				foreach($events as $r) $event_total += $r[1];

				$ds_total = 0;
				foreach($datasources as $r) $ds_total += $r[1];
				
				$table = array();
				$table[] = array('', '');
				$table[] = array(__('Total Database Queries'), $dbstats['queries']);
				if (count($dbstats['slow-queries']) > 0) {
					$table[] = array(__('Slow Queries (> 0.09s)'), count($dbstats['slow-queries']) . 's');
				}
				$table[] = array(__('Total Time Spent on Queries'), $dbstats['total-query-time'] . 's');
				$table[] = array(__('Time Triggering All Events'), $event_total) . 's';
				$table[] = array(__('Time Running All Data Sources'), $ds_total . 's');
				$table[] = array(__('XML Generation Function'), $xml_generation[1] . 's');
				// $table[] = array(__('XSLT Generation'), $xsl_transformation[1]); not available for this delegate
				$table[] = array(__('Output Creation Time'), Frontend::instance()->Profiler->retrieveTotalRunningTime());
				$firephp->table('Page Output', $table);
				
			
				if (count($datasources) > 0) {
					$table = array();
					$table[] = array('Data Source', 'Time', 'Queries');
					foreach($datasources as $profile) {
						$table[] = array($profile[0], $profile[1] . 's', $profile[4]);
					}
					$firephp->table('Data Sources', $table);
				}			
			
				if (count($events) > 0) {
					$table = array();
					$table[] = array('Event', 'Time', 'Queries');
					foreach($events as $profile) {
						$table[] = array($profile[0], $profile[1] . 's', $profile[4]);
					}
					$firephp->table('Events', $table);
				}				
		
			$firephp->groupEnd();
		
			// Debug group
			
			$xml = simplexml_load_string($this->xml);

			$firephp->group('Debug', array('Collapsed' => false));
			
				if ($this->_Parent->Configuration->get('enabled', 'firebug_profiler') == 'yes') {
				
					$table = array();
					$table[] = array('Event', 'XML');		
					$xml_events = $xml->xpath('/data/events/*');
					if (count($xml_events) > 0) {
						foreach($xml_events as $event) {
							$table[] = array($event->getName(), $event->asXML());
						}
						$firephp->table('Events (' . count($xml_events) .')', $table);
					}				

					$table = array();
					$table[] = array('Data Source', 'Entries', 'XML');		
					$xml_datasources = $xml->xpath('/data/*[name() != "events"]');
					if (count($xml_datasources) > 0) {
						foreach($xml_datasources as $ds) {
							$entries = $ds->xpath('entry[@id]');
							$table[] = array($ds->getName(), count($entries), $ds->asXML());
						}
						$firephp->table('Data Sources (' . count($xml_datasources) .')', $table);
					}
				
				}
				
				$param_table = array();
				$param_table[] = array('Parameter', 'Value');

				foreach($this->params as $name => $value) {
					if ($name == 'root') continue;
					$param_table[] = array('$' . trim($name), ($value == null) ? '' : $value);
				}

				$firephp->table('Page Parameters', $param_table);
				
			$firephp->groupEnd();
				
		}
	
		public function frontendParamsResolve($context) {
			$this->params = $context['params'];
		}
		
	}