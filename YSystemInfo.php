<?php
/**
 * YSystemInfo class file.
 *
 * @author Lorenzo Milesi <maxxer@yetopen.it>
 * @copyright Copyright (c) 2012 YetOpen S.r.l.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 **/

/**
 * YSystemInfo implements some functionality to read some Linux system informations.
 * Place this file in protected/extensions/ and add to your config.php
 * 'components'=>array(
 *      'YSystemInfo'=>array(
 *          'class'=>'application.extensions.YSystemInfo',
 *       ),
 * ),
 * 
 * then use something like Yii::app()->YSystemInfo->functionName(); 
 * 
 * @author Lorenzo Milesi <maxxer@yetopen.it>
 * @version 0.1
 */
class YSystemInfo extends CApplicationComponent
{
    const IFACE_Ethernet = "Ethernet";
    const IFACE_Loopback = "Loopback";
    const IFACE_Unspec = "UNSPEC";

	/**
	 * Get system iterface names 
	 * @return string[] interface list
	 */
	public function getInterfaceNames()
	{
        $nc = new NetworkConfig ($this->runSystemCommand("/sbin/ifconfig -a"));
		return $nc->getInterfaceNames ();
	}
	/**
	 * Get system iterface names 
	 * @param string $type an optional interface type
	 * @return string[] interface list
	 */
	public function getInterfacesByType($type = null)
	{
        $nc = new NetworkConfig ($this->runSystemCommand("/sbin/ifconfig -a"));
		return $nc->getInterfacesByType ($type);
    }
	/**
	 * Get partition size where the specified directory is mounted
	 * @param string $dir directory to search
	 * @return string partition size in KB
	 */
	public function getPartitionSize($dir)
	{
        $dir = trim ($dir);
        if (empty ($dir))
            return 0;

        // Recursely remove 
        $c = trim ($this->runSystemCommand("/bin/df | /bin/grep \"$dir$\" | /usr/bin/awk '{print $2}' "));
        if (empty ($c)) 
            return $this->getPartitionSize (dirname ($dir));
        else
            return $c;
    }
	
	/**
	 * Run system command
	 * @param string command to execute
	 * @return string[] output
	 */
    private function runSystemCommand ($cmd)
    {
        $ret = 0;
        $out = array ();
        // LANG is used to get un-localized output, otherwise strings like "Local Loopback" can be translated
        exec ("LANG=en_GB $cmd", $out, $ret);
        if ($ret != 0)
            throw new Exception ("Impossibile eseguire il comando");
        return implode ("\n", $out);
    }
}


/**
 * Network output parsing function
 * (c) Benjamin Holmberg
 * sourced from http://benajnim.com/index.php/php/parsing-ifconfig-output-in-php/
 * with little modifications (to parse unconfigured interfaces)
 **/
class NetworkConfig {
    /*
     * Pass the output of ifconfig into the constructor and this class will create a
     * usable data structure to access config info.  To define parsing fields for
     * the various link encapsulation types, modify the $baseFieldMap or
     * $encapFieldMap arrays as needed
     */
    private $interface = array();
 
    private $baseFieldMap  = array(
                            'rx'          => 'RX bytes:',
                            'tx'          => 'TX bytes:'
                            );
 
    private $encapFieldMap = array(
        'Ethernet'       => array(
                            'mac_address' => 'HWaddr ',
                            'ip_address'  => 'inet addr:',
                            'broadcast'   => 'Bcast:',
                            'netmask'     => 'Mask:'
        ),
        'Local Loopback' => array(
                            'ip_address'  => 'inet addr:'
        ),
        'UNSPEC'       => array(
                            'ip_address'  => 'inet addr:',
                            'broadcast'   => 'Bcast:',
                            'netmask'     => 'Mask:'
        ),
    );
 
    function __construct($config) {
        $interfaces = preg_split("/\n\n/", $config);
 
        foreach ($interfaces as $if) {
            if (strlen(trim($if)) > 0) {
                $interface = new stdClass;
                $interface->name = substr($if, 0, strpos($if, " "));
 
                //$interface->raw_output = $if; // raw ifconfig output for the interface
                $interface->encapsulation = TextParser::extractBetween($if, "Link encap:", array("\n", "  "));
 
                $extractFields = $this->baseFieldMap;
                if (is_array($this->encapFieldMap[$interface->encapsulation]) && count($this->encapFieldMap[$interface->encapsulation]) > 0) {
                    $extractFields = array_merge($extractFields, $this->encapFieldMap[$interface->encapsulation]);
                }
 
                foreach ($extractFields as $field => $value) {
                    $interface->$field = TextParser::extractBetween($if, $value, array("\n", "  ", '('));
                }
                $this->__set($interface->name, $interface);
            }
        }
    }
 
    public function __set($name, $value) {
        $this->interface[$name] = $value;
    }
 
    public function __get($name) {
        if (array_key_exists($name, $this->interface)) {
            return $this->interface[$name];
        }
        $trace = debug_backtrace();
        trigger_error('Undefined property via __get(): ' . $name . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
        return null;
    }
 
    public function getInterfaceNames() {
        return array_keys($this->interface);
    }
 
    public function getInterfacesByType($type) {
        $return = array();
        $ifs = $this->getInterfaceNames();
        foreach ($ifs as $if) {
            $iface = $this->__get($if);
            if ($iface->encapsulation == $type || $type == null) {
                $return[$iface->name] = $iface;
            }
        }
        return $return;
    }
}
 
/**
 * Network output parsing function
 * (c) Benjamin Holmberg
 * sourced from http://benajnim.com/index.php/php/parsing-ifconfig-output-in-php/
 **/
class TextParser {
    /*
     * $startInclusive - include the startString matched upon in the final output
     * $stopSting - can accept an array, and will match/terminate on the first value found in the string
    */
    public static function extractBetween(&$string, $startString, $stopString, $startInclusive = false) {
        $startAdd = 0;
        if ($startInclusive == false) {
            $startAdd = strlen($startString);
        }
        $startPos = strpos($string, $startString);
        if ($startPos === FALSE) // if the start string was not found return nothing
            return "";
        $startPos += $startAdd;
 
        if (is_array($stopString)) {
            $stopCompare = array();
            foreach ($stopString as $ss) {
                $stp = (strpos($string, $ss, $startPos) - $startPos);
                if ($stp > 0) {
                    $stopCompare[] = $stp;
                }
            }
            if (count($stopCompare) == 0) {
                $extractionLength = 0;
            } else {
                $extractionLength = min($stopCompare);
            }
        } else {
            $extractionLength = (strpos($string, $stopString, $startPos) - $startPos);
        }
        //echo '$string: ' . $string . "\n" . '$extractionLength: ' . $extractionLength . "\n";
        //echo '$extractionLength: ' . $extractionLength . "\n";
        if ($extractionLength > 0) {
            $rtn = trim(substr($string, $startPos, $extractionLength));
        } elseif ($extractionLength < 0) {
            $rtn = trim(substr($string, $startPos));
        } else {
            $rtn = '';
        }
        return $rtn;
    }
}
 

?>
