<?php if (!defined('NEOFRAG_CMS')) exit;
/**************************************************************************
Copyright © 2015 Michaël BILCOT & Jérémy VALENTIN

This file is part of NeoFrag.

NeoFrag is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

NeoFrag is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with NeoFrag. If not, see <http://www.gnu.org/licenses/>.
**************************************************************************/

abstract class Module extends Addon
{
	static public $core = [
		'access'      => FALSE,
		'addons'      => FALSE,
		'admin'       => FALSE,
		'comments'    => TRUE,
		'error'       => FALSE,
		'live_editor' => FALSE,
		'monitoring'  => FALSE,
		'pages'       => TRUE,
		'search'      => TRUE,
		'settings'    => FALSE,
		'user'        => FALSE
	];

	static public function __load($loader, $name, $type, $settings, &$class, &$path, &$construct)
	{
		$class     = 'm_'.$name;
		$path      = $loader->paths2('modules', $name.'/'.$name.'.php');
		$construct = [$name, $type, $path, $settings];
	}

	static public function __label()
	{
		return ['Modules', 'Module', 'fa-sticky-note-o', 'primary'];
	}

	private $_output      = '';
	private $_actions     = [];

	public function __toString()
	{
		return $this->url->extension == 'json' ? (is_string($this->_output) ? $this->_output : json_encode($this->_output)) : $this->output->data['output'].display($this->_output);
	}

	public function __init()
	{

	}

	public function __actions()
	{
		return [
			['enable',   'Activer',       'fa-check',   'success'],
			['disable',  'Désactiver',    'fa-times',   'muted'],
			['settings', 'Configuration', 'fa-wrench',  'warning'],
			NULL,
			['reset',    'Réinitialiser', 'fa-refresh', 'danger'],
			['delete',   'Désinstaller',  'fa-remove',  'danger']
		];
	}

	public function paths()
	{
		return function(){
			if (!empty(NeoFrag()->theme))
			{
				if (in_array($theme_name = NeoFrag()->theme->name, ['default', 'admin']))
				{
					unset($theme_name);
				}
			}

			return [
				'assets' => [
					'assets',
					'overrides/modules/'.$this->name,
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name : '',
					'modules/'.$this->name
				],
				'controllers' => [
					'overrides/modules/'.$this->name.'/controllers',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/controllers' : '',
					'modules/'.$this->name.'/controllers'
				],
				'forms' => [
					'overrides/modules/'.$this->name.'/forms',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/forms' : '',
					'modules/'.$this->name.'/forms'
				],
				'helpers' => [
					'overrides/modules/'.$this->name.'/helpers',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/helpers' : '',
					'modules/'.$this->name.'/helpers'
				],
				'lang' => [
					'overrides/modules/'.$this->name.'/lang',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/lang' : '',
					'modules/'.$this->name.'/lang'
				],
				'libraries' => [
					'overrides/modules/'.$this->name.'/libraries',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/libraries' : '',
					'modules/'.$this->name.'/libraries'
				],
				'models' => [
					'overrides/modules/'.$this->name.'/models',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/models' : '',
					'modules/'.$this->name.'/models'
				],
				'views' => [
					'overrides/modules/'.$this->name.'/views',
					!empty($theme_name) ? 'themes/'.$theme_name.'/overrides/modules/'.$this->name.'/views' : '',
					'modules/'.$this->name.'/views'
				]
			];
		};
	}

	public function append_output($output)
	{
		if (is_string($output))
		{
			$this->_output .= $output;
		}
		else
		{
			$this->_output = $output;
		}
	}

	public function add_action($url, $title, $icon = '')
	{
		$this->_actions[] = [$url, $title, $icon];
	}

	public function get_actions()
	{
		return $this->_actions;
	}

	public function get_method(&$args, $ignore_ajax = FALSE)
	{
		$url = '';

		if ($this->url->admin)
		{
			$url .= 'admin';
		}
		
		if ($this->url->ajax && !$ignore_ajax)
		{
			$url .= '/ajax';
		}
		
		$url = ltrim($url, '/');
		
		$routes = $this->info()->routes;
		
		if ($url)
		{
			foreach (array_keys($routes) as $route)
			{
				if (!preg_match('#^'.$url.'#', $route))
				{
					unset($routes[$route]);
				}
			}
			
			$url .= '/';
		}
		
		$url .= implode('/', $args);

		$method = NULL;

		foreach ($routes as $route => $function)
		{
			if (preg_match('#^'.str_replace(array_map(function($a){ return '{'.$a.'}'; }, array_keys(self::$route_patterns)) + ['#'], array_values(self::$route_patterns) + ['\#'], $route).'$#', $url, $matches))
			{
				$args = [];
				
				if (in_string('{url_title*}', $route))
				{
					foreach (array_offset_left($matches) as $arg)
					{
						$args = array_merge($args, explode('/', trim($arg, '/')));
					}
				}
				else
				{
					$args = array_offset_left($matches);
				}
				
				$args = array_map(function($a){return trim($a, '/');}, $args);

				$method = $function;
				break;
			}
		}
		
		return $method;
	}
	
	public function get_permissions($type = NULL)
	{
		if (method_exists($this, 'permissions'))
		{
			$permissions = $this->permissions();
			
			if ($type === NULL)
			{
				return $permissions;
			}
			else if (isset($permissions[$type]))
			{
				return $permissions[$type];
			}
		}

		return [];
	}

	public function is_administrable(&$category = NULL)
	{
		if (property_exists($info = $this->info(), 'admin'))
		{
			$category = $info->admin;

			if (is_bool($category))
			{
				$category = $category ? 'default' : 'none';
			}

			return TRUE;
		}

		return FALSE;
	}

	public function is_authorized()
	{
		static $allowed;
		
		if ($allowed === NULL)
		{
			$allowed = FALSE;
			
			if ($this->is_administrable())
			{
				if ($this->user->admin)
				{
					$allowed = TRUE;
				}
				else if (isset($this->groups($this->user->id)[1]) && ($all_permissions = $this->get_permissions('default')))
				{
					foreach ($all_permissions['access'] as $a)
					{
						foreach ($a['access'] as $action => $access)
						{
							if (!empty($access['admin']) && $this->access($this->name, $action))
							{
								$allowed = TRUE;
								break 2;
							}
						}
					}
				}
			}
		}
		
		return $allowed;
	}
}

/*
NeoFrag Alpha 0.1.6
./classes/module.php
*/