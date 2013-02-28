<?php

class Modpack_Controller extends Base_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->fi1lter('before','auth');
	}

	public function action_index()
	{
		return View::make('modpack.index');
	}

	public function action_view($modpack_id = null)
	{
		if (empty($modpack_id))
			return Redirect::to('modpack');

		$modpack = Modpack::find($modpack_id);
		if (empty($modpack))
			return Redirect::to('modpack');

		return View::make('modpack.view')->with('modpack', $modpack);
	}

	public function action_build($build_id = null)
	{
		if (empty($build_id))
			return Redirect::to('modpack');

		if ($build_id == "create")
			return View::make('modpack.build.create');

		$build = Build::find($build_id);
		if (empty($build))
			return Redirect::to('modpack');

		return View::make('modpack.build.view')->with('build', $build);
	}

	public function action_create()
	{
		Asset::add('jquery', 'js/jquery.slugify.js');
		return View::make('modpack.create');
	}

	public function action_do_create()
	{
		Validator::register('checkresources', function($attribute, $value, $parameters)
		{
			if ($this->check_resource($value,"logo_180.png") && 
				$this->check_resource($value,"icon.png") && 
				$this->check_resource($value,"background.jpg"))
				return true;
			else
				return false;
		});

		$rules = array(
			'name' => 'required|unique:modpacks',
			'slug' => 'required|checkresources|unique:modpacks'
			);

		$messages = array(
			'name_required' => 'You must enter a modpack name.',
			'slug_required' => 'You must enter a modpack slug',
			'slug_checkresources' => 'Make sure all the resources required exist before submitting a pack!'
			);

		$validation = Validator::make(Input::all(), $rules, $messages);

		if ($validation->fails())
			return Redirect::back()->with_errors($validation->errors);
		$url = Config::get('solder.repo_location').$slug.'/resources/';
		try {
			$modpack = new Modpack();
			$modpack->name = Input::get('name');
			$modpack->slug = Str::slug(Input::get('slug'));
			$modpack->icon_md5 = md5_file($url.'icon.png');
			$modpack->logo_md5 = md5_file($url.'logo_180.png');
			$modpack->background_md5 = md5_file($url.'background.jpg');
			$modpack->save();
			return Redirect::to('modpack/view/'.$modpack->id);
		} catch (Exception $e) {
			Log::exception($e);
		}
	}

	/**
	 * AJAX Methods for Modpack Build Manager
	 **/
	public function action_modify($action = null)
	{
		if (empty($action))
			return Response::error('500');

		switch ($action)
		{
			case "version":
				$sql = 'UPDATE `build_modversion` SET modversion_id=? WHERE id=?';
				$affected = DB::query($sql,array(Input::get('version'), Input::get('pivot_id')));
				return Response::json(array('success' => 'Rows Affected: '.$affected));
				break;
			case "delete":
				$sql = 'DELETE FROM `build_modversion` WHERE id=?';
				$affected = DB::query($sql,array(Input::get('pivot_id')));
				return Response::json(array('success' => 'Rows Affected: '.$affected));
				break;
			case "add":
				$build = Build::find(Input::get('build'));
				$mod = Mod::where('name','=',Input::get('mod-name'))->first();
				$ver = ModVersion::where('mod_id','=', $mod->id)
									->where('version','=', Input::get('mod-version'))
									->first();
				$build->modversions()->attach($ver->id);
				return Response::json(array(
								'pretty_name' => $mod->pretty_name,
								'version' => $ver->version,
								));
		}
	}

	private function check_resource($slug,$resource)
	{
		$url = Config::get('solder.repo_location').$slug.'/resources/'.$resource;
		if (file_exists($url))
			return true;
		else
		{
			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_exec($ch);
			$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($retcode == 200)
				return true;
			else {
				return false;
			}
		}

	}
}