<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Simian grid services
 *
 * PHP version 5
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *
 * @package    SimianGrid
 * @author     Jim Radford <http://www.jimradford.com/>
 * @copyright  Open Metaverse Foundation
 * @license    http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @link       http://openmetaverse.googlecode.com/
 */

function js_escape_string($s)
{
    return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $s) . '"';
}

function osd_val($key, $value)
{
  $nums = array("Version", "LeftNode", "RightNode");
  $out = "";
  if (is_null($value))
    {
      $out .= "\"\"";
    }
  else if (is_array($value))
    {

    }
  else if (is_numeric($value))
    {
      if (in_array($value, $nums))
	$out .= $value;
      else
	$out .= js_escape_string($value);
    }
  else if ($key == "ExtraData")
    {
      $out .= (!empty($value)) ? $value : '{}';
    }
  else if (is_string($value))
    {
      $out .= js_escape_string($value);
    }
  else if (is_bool($value))
    {
      $out .= ($value) ? "true" : "false";
    }
  else
    {
      $out .= sprintf("?%s?", js_escape_string($value));
    }
  return $out;
		
}

class InventoryFolder extends Inventory implements IOSD
{
    public $Version = 0;

    function __construct($id)
    {
        $this->Type = 'Folder';
        $this->ID = $id;
        $this->ExtraData = '';
        $this->CreationDate = gmdate('U');
    }

    public function toOSD()
    {
        $out = "{";
        foreach ($this as $key => $value)
        {
            $out .= sprintf("\"%s\":", $key);
            if (method_exists($value, "toOSD"))
            {
                $out .= $value->toOSD();
            }
            else
            {
	      $out.= osd_val($key, $value);
            }
            
            $out .= ',' . "";
        }
        
        $out = rtrim($out, ",");
        $out .= "}";
        return $out;
    }

    public static function fromOSD($strOSD)
    {
        throw new Exception("Not Implemented");
    }
}

class InventoryItem extends Inventory implements IOSD
{
    public $AssetID;
    public $CreatorID;
    public $Description;

    function __construct($id)
    {
        $this->ID = $id;
        $this->Type = 'Item';
        $this->ExtraData = '';
        $this->CreationDate = gmdate('U');
        $this->Description = '';
    }

    public function toOSD()
    {
        $out = "{";
        foreach ($this as $key => $value)
        {
            $out .= sprintf("\"%s\":", $key);
            if (method_exists($value, "toOSD"))
            {
                $out .= $value->toOSD();
            }
            else
            {
	      $out .= osd_val($key, $value);
            }
            
            $out .= ',' . "";
        }
        
        $out = rtrim($out, ",");
        $out .= "}";
        return $out;
    }

    public static function fromOSD($strOSD)
    {
        throw new Exception("Not Implemented");
    }
}
