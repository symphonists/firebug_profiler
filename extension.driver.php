<?php
	
	class Extension_Firebug_Profiler extends Extension {
		
		private $params = array();
		
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
		public function about() {
			return array(
				'name'			=> 'Firebug Profiler',
				'version'		=> '0.01',
				'release-date'	=> '2009-05-25',
				'author'		=> array(
					'name'			=> 'Nick Dunn',
					'website'		=> 'http://airlock.com',
					'email'			=> 'nick.dunn@airlock.com'
				),
				'description'	=> 'Bastardise the backend.'
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
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
		
	/*-------------------------------------------------------------------------
		Delegates:
	-------------------------------------------------------------------------*/
		
		public function frontendOutputPostGenerate($context) {
			
			// don't output anything for unauthenticated users
			if (!Frontend::instance()->isLoggedIn()) return;
			
			require_once(EXTENSIONS . '/firebug_profiler/lib/FirePHPCore/FirePHP.class.php');
			$firephp = FirePHP::getInstance(true);
			
			$firephp->group('Profile', array('Collapsed' => true));
			
				$firephp->group('General', array('Collapsed' => false));
				foreach(Frontend::instance()->Profiler->retrieveGroup('General') as $profile) {
					$firephp->log($profile[1], $profile[0]);
				}
				$firephp->groupEnd();
				
				$firephp->group('Data Sources', array('Collapsed' => false));
				foreach(Frontend::instance()->Profiler->retrieveGroup('Datasource') as $profile) {
					$firephp->log($profile[1], $profile[0]);
				}
				$firephp->groupEnd();
				
				$firephp->group('Events', array('Collapsed' => false));
				foreach(Frontend::instance()->Profiler->retrieveGroup('Events') as $profile) {
					$firephp->log($profile[1], $profile[0]);
				}
				$firephp->groupEnd();
			
			$firephp->groupEnd();
			
			// Page Params comes last as it seems to break FirePHP headers above it
			$firephp->group('Page Parameters', array('Collapsed' => true));
			foreach($this->params as $name => $value) {
				if ($name == 'root') continue;
				$firephp->log(trim($value), $name);
			}
			$firephp->groupEnd();
			
					
		}
		
		public function frontendParamsResolve($context) {
			$this->params = $context['params'];
		}
		
	}
	
?>