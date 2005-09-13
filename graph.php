<?php
/**
 * $Id$
 *
 * Author: David Danier, david.danier@team23.de
 * Project: Serverstats, http://www.webmasterpro.de/~ddanier/serverstats/
 * License: GPL v2 or later (http://www.gnu.org/copyleft/gpl.html)
 *
 * Copyright (C) 2005 David Danier
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Load all needed classes, function and everything else
require_once('init.php');
if (!isset($_GET['graph']))
{
	die('$_GET["graph"] missing');
}

try
{
	// Init the needed Vars
	$graphindex = $_GET['graph'];
	$start = isset($_GET['start']) ? $_GET['start'] : -$config['graph']['defaultperiod'];
	$end = isset($_GET['end']) ? $_GET['end'] : null;
	$graph = $config['graph']['list'][$graphindex];
	$title = $graph['title'];
	if (isset($_GET['title']))
	{
		$title = $title . ' - ' . $_GET['title'];
	}
	$usecache = $config['graph']['usecache'];
	
	// Cachefilename is generated from all vars that may change
	$filename = md5($graphindex . '_' . $start . '_' . $end . '_' . $title);
	$graphfile = GRAPHPATH . $filename . '.png';
	
	// Create Graph
	$rrdgraph = new rrdgraph($config['main']['rrdtool'], $start, $end);
	$rrdgraph->setTitle($title);
	$rrdgraph->setWidth($config['graph']['width']);
	$rrdgraph->setHeight($config['graph']['height']);
	
	if (isset($graph['base']))
	{
		$rrdgraph->setBase($graph['base']);
	}
	if (isset($graph['upperLimit']))
	{
		$rrdgraph->setUpperLimit($graph['upperLimit']);
	}
	if (isset($graph['lowerLimit']))
	{
		$rrdgraph->setLowerLimit($graph['lowerLimit']);
	}
	if (isset($graph['verticalLabel']))
	{
		$rrdgraph->setVerticalLabel($graph['verticalLabel']);
	}
	if (isset($graph['unitsExponent']))
	{
		$rrdgraph->setUnitsExponent($graph['unitsExponent']);
	}
	if (isset($graph['altYMrtg']))
	{
		$rrdgraph->setAltYMrtg($graph['altYMrtg']);
	}
	if (isset($graph['altAutoscale']))
	{
		$rrdgraph->setAltAutoscale($graph['altAutoscale']);
	}
	if (isset($graph['altAutoscaleMax']))
	{
		$rrdgraph->setAltAutoscaleMax($graph['altAutoscaleMax']);
	}
	
	$lasttype = null;
	foreach($graph['content'] as $c)
	{
		$intname = null;
		$rrdfile = null;
		if (!isset($c['type']))
		{
			throw new Exception('Unknow type');
		}
		$c['type'] = strtoupper($c['type']); // backwards compability
		// If the Graphcontent need is generated from a RRD-file we need
		// to add a DEF here
		if (in_array($c['type'], array('LINE', 'AREA', 'STACK', 'GPRINT', 'SHIFT', 'TICK')))
		{
			if (isset($c['source']))
			{
				$intname = $c['source'] . '_' . $c['ds'];
				$rrdfile = RRDPATH . $c['source'] . '.rrd';
				$rrdgraph->addDEF($intname, $rrdfile, $c['ds'], $c['cf']);
			}
			elseif (isset($c['file']) && isset($c['name']))
			{
				$intname = $c['name'];
				$rrdfile = $c['file'];
				$rrdgraph->addDEF($intname, $rrdfile, $c['ds'], $c['cf']);
			}
			elseif (isset($c['name']))
			{
				$intname = $c['name'];
			}
			else
			{
				throw new Exception('You need to set either "source" or "name" or ("file" and "name")');
			}
		}
		// Add the content
		switch ($c['type'])
		{
			case 'DEF':
				if (!array_check($c, array('name', 'ds', 'cf')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				if (isset($c['source']))
				{
					$rrdfile = RRDPATH . $c['source'] . '.rrd';
				}
				elseif (isset($c['file']))
				{
					$rrdfile = $c['file'];
				}
				else
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addDEF($c['name'], $rrdfile, $c['ds'], $c['cf']);
				break;
			case 'VDEF':
				if (!array_check($c, array('name', 'expression')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addVDEF($c['name'], $c['expression']);
				break;
			case 'CDEF':
				if (!array_check($c, array('name', 'expression')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addCDEF($c['name'], $c['expression']);
				break;
			case 'LINE':
				$rrdgraph->addLINE(array_get($c, 'width'), $intname, array_get($c, 'color'), array_get($c, 'legend'), array_get($c, 'stacked'));
				$lasttype = $c['type'];
				break;
			case 'AREA':
				$rrdgraph->addAREA($intname, array_get($c, 'color'), array_get($c, 'legend'), array_get($c, 'stacked'));
				$lasttype = $c['type'];
				break;
			case 'STACK': // backwards compability
				switch ($lasttype)
				{
					case 'LINE':
						$rrdgraph->addLINE(array_get($c, 'width'), $intname, array_get($c, 'color'), array_get($c, 'legend'), true);
						break;
					case 'AREA':
						$rrdgraph->addAREA($intname, array_get($c, 'color'), array_get($c, 'legend'), true);
						break;
					default:
						throw new Exception('You must define a LINE or AREA before using STACK');
						break;
				}
				break;
			case 'TICK':
				if (!array_check($c, array('color')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addTICK($intname, $c['offset']);
				break;
			case 'SHIFT':
				if (!array_check($c, array('offset')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addGPRINT($intname, $c['offset']);
				break;
			case 'GPRINT':
				if (!array_check($c, array('format')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				if (isset($c['cf']) && isset($c['name'])) // backwards compability
				{
					$oldintname = $intname;
					$intname = $intname . '_' . $c['cf'];
					$rrdgraph->addVDEF($intname, $oldintname . ',' . $c['cf']);
				}
				$rrdgraph->addGPRINT($intname, $c['format']);
				break;
			case 'HRULE': // backwards compability
				if (!array_check($c, array('value')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addLINE(array_get($c, 'width'), $c['value'], array_get($c, 'color'), array_get($c, 'legend'), false);
				break;
			case 'VRULE':
				if (!array_check($c, array('time')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addAREA($c['time'], array_get($c, 'color'), array_get($c, 'legend'));
				break;
			case 'COMMENT':
				if (!array_check($c, array('text')))
				{
					throw new Exception('Missing values for ' . $c['type']);
				}
				$rrdgraph->addCOMMENT($c['text']);
				break;
			default:
				throw new Exception('Unknow type ' . $c['type']);
				break;
		}
	}
	
	// Set the content-type
	@header('Content-Type: image/png');
	
	if ($usecache)
	{
		$rrdgraph->setLazy(true);
		$rrdgraph->save($graphfile);
		if (!@readfile($graphfile))
		{
			throw new Exception('Unable to read imagefile');
		}
	}
	else
	{
		$rrdgraph->output();
	}
}
catch (Exception $e)
{
	@header('Content-Type: text/plain');
	$config['main']['logger']->logException(logger::ERR, $e);
}

?>
