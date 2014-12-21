<?php

	if (!file_exists('include/config.xml') || file_get_contents( 'include/config.xml' ) == '')
	{
		header('Location: check_install.php');
		die;
	}

  $_config = (array)simplexml_load_file('include/config.xml'); // conversion en array du fichier xml de configuration
  unset($_config['comment']); // enleve les commentaires

  $version_knxweb2 = file_get_contents(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'version', FILE_USE_INCLUDE_PATH);
  if ($_config["version"] != $version_knxweb2) {
		header('Location: check_install.php');
		die;
  }
  $MAJ_knxweb2 = false;

	require_once('include/tpl.php');
	require_once('lang/lang.php');

  $_widgets=array();

	$_objectTypes = array(
	    '1.001' => '1.001: switching (on/off) (EIS1)',
      '1.002' => '1.002 : Boolean',
      '1.003' => '1.003 : Enable',
      '1.004' => '1.004 : Ramp',
      '1.005' => '1.005 : Alarm',
      '1.006' => '1.006 : BinaryValue',
      '1.007' => '1.007 : Step',
      '1.008' => '1.008 : UpDown',
      '1.009' => '1.009 : OpenClose',
      '1.010' => '1.010 : StartStop',
      '1.011' => '1.011 : State',
      '1.012' => '1.012 : Invert',
      '1.013' => '1.013 : DimSendStyle',
      '1.014' => '1.014 : InputSource',
      '2.001' => '2.001 : Switch Control ',
      '2.002' => '2.002 : Bool Control ',
      '2.003' => '2.003 : Enable Control ',
      '2.004' => '2.004 : Ramp Control ',
      '2.005' => '2.005 : Alarm Control ',
      '2.006' => '2.006 : BinaryValue Control ',
      '2.007' => '2.007 : Step Control ',
      '2.008' => '2.008 : Direction1_Control',
      '2.009' => '2.009 : Direction2_Control',
      '2.010' => '2.010 : Start Control ',
      '2.011' => '2.011 : State Control ',
      '2.012' => '2.012 : Invert Control ',
	    '3.007' => '3.007: dimming (EIS2)',
	    '3.008' => '3.008: blinds',
      '4.001' => '4.001 : Char_ASCI',
      '4.002' => '4.002 : Char_8859_1',
	    '5.xxx' => '5.xxx: 8bit unsigned integer (EIS6)',
	    '5.001' => '5.001: scaling (from 0 to 100%)',
	    '5.003' => '5.003: angle (from 0 to 360deg)',
	    '6.xxx' => '6.xxx: 8bit signed integer (EIS14)',
	    '7.xxx' => '7.xxx: 16bit unsigned integer (EIS10)',
	    '8.xxx' => '8.xxx: 16bit signed integer',
	    '9.xxx' => '9.xxx: 16 bit floating point number (EIS5)',
      '9.001' => '9.001 : Temp : �C',
      '9.002' => '9.002 : Tempd : �K',
      '9.003' => '9.003 : Tempa : K/H',
      '9.004' => '9.004 : Lux : lux',
      '9.005' => '9.005 : Wsp : m/s',
      '9.006' => '9.006 : Pres : Pa',
      '9.007' => '9.007 : Humidity : %',
      '9.008' => '9.008 : AirQuality : ppm',
      '9.010' => '9.010 : Time : s',
      '9.011' => '9.011 : Time : ms',
      '9.020' => '9.020 : Volt : mV',
      '9.021' => '9.021 : Current : mA',
      '9.022' => '9.022 : PowerDensity : W/m�',
      '9.023' => '9.023 : KelvinPerPercent : K/%',
      '9.024' => '9.024 : Power : kW',
      '9.025' => '9.025 : VolumeFlow : l/h',
      '9.026' => '9.026 : Rain_Amount : l/m�',
      '9.027' => '9.027 : Value_Temp_F : �F',
      '9.028' => '9.028 : Value_Wsp_kmh : km/h',
	    '10.001' => '10.001: time (EIS3)',
	    '11.001' => '11.001: date (EIS4)',
	    '12.xxx' => '12.xxx: 32bit unsigned integer (EIS11)',
	    '13.xxx' => '13.xxx: 32bit signed integer',
	    '14.xxx' => '14.xxx: 32 bit IEEE 754 floating point number',
	    '16.000' => '16.000: string (EIS15) to ASCII codes 0 to 127',
	    '16.001' => '16.001: string (EIS15) with range 0 to 255 ',
	    '20.102' => '20.102: heating mode',
	    '28.001' => '28.001: variable length string objects',
	    '29.xxx' => '29.xxx: signed 64bit value',
      '232.600' => '232.600 : RGBObject'
	);

	// Convert to a Javascript array
	$json_objectTypes = json_encode($_objectTypes);

	$_objectFlags = array(
		'c' => 'Communication',
		'r' => 'Read',
		'w' => 'Write',
		't' => 'Transmit',
		'u' => 'Update',
		//'s' => 'Stateless'
		'f' => 'Force',
		//'i' => 'Init'
	);

	function parseSetting($xml) {
		$setting=array();
		foreach($xml->attributes() as	$key => $value)  $setting[(string)$key]=(string)$value;

		if ($setting['type']=='list') {
			$setting['options']=array();
			foreach($xml as $value) $setting['options'][(string)$value->attributes()->key]=(string)$value->attributes()->value;
		}
		return $setting;
	}

	function getWidget($type) {
		$path='widgets/' . $type;
		if (file_exists($path . '/manifest.xml'))
		{
			$xml = (array)simplexml_load_file($path . '/manifest.xml');

			$ret=array(
				"name"	=>	$type,
				"path"	=>	$path,
				"label"	=>	$xml['label'],
				"description"	=>	$xml['description'],
				"version" => $xml['version'],
				"category" => $xml['category'],
				"settings" => array()
			);

			if (isset($xml['settings'])) {
			$settings=(array)$xml['settings'];
			if ($settings) {
				if (is_array($settings['setting'])) {
					// Multiple settings
					foreach((array)$settings['setting'] as $v) {
						$setting=parseSetting($v);
						$ret['settings'][]=$setting;
					}
				} else
				{
					// single setting
					$setting=parseSetting($settings['setting']);
					$ret['settings'][]=$setting;
				}
			}
 			}
			if (isset($xml['feedbacks'])) {
			$feedbacks=(array)$xml['feedbacks'];
			if ($feedbacks) {
					if (isset($feedbacks['feedback']) && is_array($feedbacks['feedback'])) {
					// Multiple feedbacks
					foreach((array)$feedbacks['feedback'] as $v) {
						$ret['feedbacks'][]=(string)$v->attributes()->id;
					}
				} else
				{
					// single feedback
					if (isset($feedbacks['feedback'])) $ret['feedbacks'][]=(string)$feedbacks['feedback']->attributes()->id;
				}
			}
			}

			return $ret;
		} else return false;
	}

	function getWidgets()
	{
		$plugins = glob('widgets/*', GLOB_ONLYDIR);
		$ret=array();
		foreach ($plugins as $path)
		{
			$w=getWidget(basename($path));
			if ($w!=false) $ret[basename($path)]=$w;
		}
		return $ret;
	}
  $_widgets=getWidgets();

	function getWidgetsByCategory()
	{
		//$widgets=getWidgets();
    global $_widgets;

		$ret=array();
		foreach($_widgets as $id => $w) {
			$cat=$w['category'];
			unset($w['category']);
			if (!isset($ret[$cat])) $ret[$cat]=array();
			$ret[$cat][]=$w;
		}
		return $ret;
	}

	function addWidgetsJsCssToTpl($isEdit = false, $isMobile = false)
	{
		//$widgets = getWidgets();
    global $_widgets;
		foreach($_widgets as $name => $info)
		{
			if (file_exists($info['path'] . '/widget.css')) tpl()->addCss($info['path'] . '/widget.css');
			tpl()->addJs($info['path'] . '/widget.js');
		}
	}

  function getUiThemes()
  {
    $uitheme = glob('lib/jquery/css/*', GLOB_ONLYDIR);
    $ret=array();
    foreach ($uitheme as $path)
    {
      if (file_exists( $path . '/jquery-ui.css')) $ret[basename($path)]=basename($path);
    }
    return $ret;
  }

?>