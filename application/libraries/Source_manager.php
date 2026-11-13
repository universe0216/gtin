<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Source_manager {

	protected $CI;
	protected $allowed_roots = array(
		'application',
		'assets/js',
		'assets/css',
	);
	protected $allowed_extensions = array('php', 'js', 'css');

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function root_path()
	{
		return rtrim(str_replace('\\', '/', FCPATH), '/');
	}

	public function tree()
	{
		$nodes = array();

		foreach ($this->allowed_roots as $root)
		{
			$absolute = $this->absolute_path($root);

			if ($absolute === NULL || ! is_dir($absolute))
			{
				continue;
			}

			$child = $this->build_directory_node($root, $absolute);

			if ($child !== NULL)
			{
				$nodes[] = $child;
			}
		}

		return $nodes;
	}

	public function resolve_file($relative_path)
	{
		$relative = $this->normalize_relative($relative_path);

		if ($relative === '' || ! $this->is_allowed_relative($relative))
		{
			return NULL;
		}

		$absolute = $this->absolute_path($relative);

		if ($absolute === NULL || ! is_file($absolute))
		{
			return NULL;
		}

		if ($this->is_backup_filename(basename($absolute)))
		{
			return NULL;
		}

		$extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

		if ( ! in_array($extension, $this->allowed_extensions, TRUE))
		{
			return NULL;
		}

		return array(
			'relative_path' => $relative,
			'absolute_path' => $absolute,
			'basename'      => basename($absolute),
			'extension'     => $extension,
			'size'          => filesize($absolute),
			'modified_at'   => date('Y-m-d H:i:s', filemtime($absolute)),
		);
	}

	public function replace_file($relative_path, $upload)
	{
		$target = $this->resolve_file($relative_path);

		if ($target === NULL)
		{
			throw new RuntimeException(app_line('source_file_not_found'));
		}

		if (empty($upload) || ! isset($upload['error']) || (int) $upload['error'] !== UPLOAD_ERR_OK)
		{
			throw new RuntimeException(app_line('source_upload_failed'));
		}

		$uploaded_name = basename((string) ($upload['name'] ?? ''));
		$upload_ext = strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION));

		if ($uploaded_name !== $target['basename'])
		{
			throw new RuntimeException(app_line('source_name_mismatch'));
		}

		if ($upload_ext !== $target['extension'])
		{
			throw new RuntimeException(app_line('source_extension_mismatch'));
		}

		if ( ! is_uploaded_file($upload['tmp_name']))
		{
			throw new RuntimeException(app_line('source_upload_failed'));
		}

		$backup = $this->backup_current($target);

		if ( ! @move_uploaded_file($upload['tmp_name'], $target['absolute_path']))
		{
			throw new RuntimeException(app_line('source_replace_failed'));
		}

		@chmod($target['absolute_path'], 0644);

		return array(
			'relative_path' => $target['relative_path'],
			'backup_path'   => $backup['backup_path'],
			'uploaded_name' => $uploaded_name,
			'size'          => (int) filesize($target['absolute_path']),
			'modified_at'   => date('Y-m-d H:i:s', filemtime($target['absolute_path'])),
		);
	}

	public function restore_backup($relative_path, $backup_path)
	{
		$target = $this->resolve_file($relative_path);

		if ($target === NULL)
		{
			throw new RuntimeException(app_line('source_file_not_found'));
		}

		$backup = $this->resolve_backup($backup_path);

		if ($backup === NULL)
		{
			throw new RuntimeException(app_line('source_backup_not_found'));
		}

		$expected_prefix = $target['basename'].'_';

		if (strpos(basename($backup['absolute_path']), $expected_prefix) !== 0)
		{
			throw new RuntimeException(app_line('source_backup_mismatch'));
		}

		$current_backup = $this->backup_current($target);

		if ( ! @copy($backup['absolute_path'], $target['absolute_path']))
		{
			throw new RuntimeException(app_line('source_restore_failed'));
		}

		@chmod($target['absolute_path'], 0644);

		return array(
			'relative_path' => $target['relative_path'],
			'backup_path'   => $current_backup['backup_path'],
			'uploaded_name' => basename($backup['absolute_path']),
			'size'          => (int) filesize($target['absolute_path']),
			'basename'      => $target['basename'],
			'extension'     => $target['extension'],
			'modified_at'   => date('Y-m-d H:i:s', filemtime($target['absolute_path'])),
		);
	}

	public function resolve_directory($relative_path)
	{
		$relative = $this->normalize_relative($relative_path);

		if ($relative === '' || ! $this->is_allowed_relative($relative))
		{
			return NULL;
		}

		$absolute = $this->absolute_path($relative);

		if ($absolute === NULL || ! is_dir($absolute))
		{
			return NULL;
		}

		return array(
			'relative_path' => $relative,
			'absolute_path' => $absolute,
		);
	}

	public function add_file($directory_path, $upload)
	{
		$directory = $this->resolve_directory($directory_path);

		if ($directory === NULL)
		{
			throw new RuntimeException(app_line('source_folder_not_found'));
		}

		if (empty($upload) || ! isset($upload['error']) || (int) $upload['error'] !== UPLOAD_ERR_OK)
		{
			throw new RuntimeException(app_line('source_upload_failed'));
		}

		$uploaded_name = basename((string) ($upload['name'] ?? ''));

		if ( ! $this->is_valid_source_filename($uploaded_name))
		{
			throw new RuntimeException(app_line('source_invalid_filename'));
		}

		$extension = strtolower(pathinfo($uploaded_name, PATHINFO_EXTENSION));

		if ( ! in_array($extension, $this->allowed_extensions, TRUE))
		{
			throw new RuntimeException(app_line('source_extension_mismatch'));
		}

		if ( ! is_uploaded_file($upload['tmp_name']))
		{
			throw new RuntimeException(app_line('source_upload_failed'));
		}

		$relative = $directory['relative_path'].'/'.$uploaded_name;
		$absolute = $directory['absolute_path'].'/'.$uploaded_name;

		if (is_file($absolute))
		{
			throw new RuntimeException(app_line('source_file_exists'));
		}

		if ( ! @move_uploaded_file($upload['tmp_name'], $absolute))
		{
			throw new RuntimeException(app_line('source_add_failed'));
		}

		@chmod($absolute, 0644);

		return array(
			'relative_path' => $relative,
			'basename'      => $uploaded_name,
			'extension'     => $extension,
			'size'          => (int) filesize($absolute),
			'modified_at'   => date('Y-m-d H:i:s', filemtime($absolute)),
			'uploaded_name' => $uploaded_name,
		);
	}

	public function resolve_backup($relative_path)
	{
		$relative = $this->normalize_relative($relative_path);

		if ($relative === '' || ! $this->is_allowed_relative($relative))
		{
			return NULL;
		}

		$absolute = $this->absolute_path($relative);

		if ($absolute === NULL || ! is_file($absolute))
		{
			return NULL;
		}

		if ( ! $this->is_backup_filename(basename($absolute)))
		{
			return NULL;
		}

		return array(
			'relative_path' => $relative,
			'absolute_path' => $absolute,
		);
	}

	protected function backup_current(array $target)
	{
		$stamp = date('Ymd_His');
		$backup_name = $target['basename'].'_'.$stamp;
		$backup_absolute = dirname($target['absolute_path']).'/'.$backup_name;
		$backup_relative = dirname($target['relative_path']);
		$backup_relative = ($backup_relative === '.' ? '' : $backup_relative.'/').$backup_name;

		if ( ! @copy($target['absolute_path'], $backup_absolute))
		{
			throw new RuntimeException(app_line('source_backup_failed'));
		}

		return array(
			'backup_path' => str_replace('\\', '/', $backup_relative),
		);
	}

	protected function build_directory_node($relative, $absolute)
	{
		$children = array();
		$entries = @scandir($absolute);

		if ($entries === FALSE)
		{
			return NULL;
		}

		$dirs = array();
		$files = array();

		foreach ($entries as $entry)
		{
			if ($entry === '.' || $entry === '..')
			{
				continue;
			}

			$child_absolute = $absolute.'/'.$entry;
			$child_relative = $relative.'/'.$entry;

			if (is_dir($child_absolute))
			{
				if ($this->should_skip_directory($entry))
				{
					continue;
				}

				$dirs[] = array($child_relative, $child_absolute, $entry);
				continue;
			}

			if ( ! is_file($child_absolute) || $this->is_backup_filename($entry))
			{
				continue;
			}

			$extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

			if ( ! in_array($extension, $this->allowed_extensions, TRUE))
			{
				continue;
			}

			$files[] = array($child_relative, $entry);
		}

		usort($dirs, function ($left, $right) {
			return strcasecmp($left[2], $right[2]);
		});
		usort($files, function ($left, $right) {
			return strcasecmp($left[1], $right[1]);
		});

		foreach ($dirs as $dir)
		{
			$child = $this->build_directory_node($dir[0], $dir[1]);

			if ($child !== NULL)
			{
				$children[] = $child;
			}
		}

		foreach ($files as $file)
		{
			$children[] = array(
				'label' => $file[1],
				'value' => $file[0],
				'type'  => 'file',
				'icon'  => $this->file_icon($file[1]),
			);
		}

		return array(
			'label'    => basename($relative),
			'value'    => $relative,
			'type'     => 'folder',
			'icon'     => 'fas fa-folder',
			'expanded' => strpos($relative, '/') === FALSE,
			'items'    => $children,
		);
	}

	protected function is_valid_source_filename($name)
	{
		return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(php|js|css)$/', $name);
	}

	protected function should_skip_directory($name)
	{
		$skip = array(
			'cache',
			'logs',
			'vendor',
			'node_modules',
			'.git',
			'.idea',
			'.vscode',
		);

		return in_array($name, $skip, TRUE) || strpos($name, '.') === 0;
	}

	protected function is_backup_filename($name)
	{
		return (bool) preg_match('/^.+\.(php|js|css)_\d{8}_\d{6}$/i', $name);
	}

	protected function file_icon($name)
	{
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

		if ($extension === 'php')
		{
			return 'fab fa-php';
		}

		if ($extension === 'js')
		{
			return 'fab fa-js';
		}

		if ($extension === 'css')
		{
			return 'fab fa-css3-alt';
		}

		return 'fas fa-file-code';
	}

	protected function normalize_relative($relative_path)
	{
		$relative = str_replace('\\', '/', trim((string) $relative_path));
		$relative = preg_replace('#/+#', '/', $relative);
		$relative = trim($relative, '/');

		if ($relative === '' || strpos($relative, '..') !== FALSE)
		{
			return '';
		}

		return $relative;
	}

	protected function is_allowed_relative($relative)
	{
		foreach ($this->allowed_roots as $root)
		{
			if ($relative === $root || strpos($relative, $root.'/') === 0)
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	protected function absolute_path($relative)
	{
		$relative = $this->normalize_relative($relative);

		if ($relative === '' || ! $this->is_allowed_relative($relative))
		{
			return NULL;
		}

		$root = $this->root_path();
		$absolute = $root.'/'.$relative;
		$real = realpath($absolute);

		if ($real === FALSE)
		{
			return is_dir($absolute) || is_file($absolute) ? $absolute : NULL;
		}

		$real = str_replace('\\', '/', $real);

		if (strpos($real, $root.'/') !== 0 && $real !== $root)
		{
			return NULL;
		}

		return $real;
	}
}
