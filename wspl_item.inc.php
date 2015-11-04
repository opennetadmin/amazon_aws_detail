<?php
global $base, $conf, $baseURL, $images, $ec2client;

// Initiate ec2client
use Aws\Ec2\Ec2Client;

// Load in the SDK
if (file_exists( dirname(__FILE__).'/aws-sdk/aws-autoloader.php')) {
  require dirname(__FILE__).'/aws-sdk/aws-autoloader.php';

}

$title_right_html = '';
$title_left_html  = '';
$modbodyhtml = '';
$modjs = '';

// Get info about this file name
$onainstalldir = dirname($base);
$file = str_replace($onainstalldir.'/www', '', __FILE__);
$thispath = dirname($file);

// future config options
$boxheight = '300px';
$divid = 'awsinfo';

    $title_left_html .= <<<EOL
        &nbsp;Amazon AWS info&nbsp;&nbsp;
EOL;

// Display only on the host display
if ($extravars['window_name'] == 'display_host') {
  if (stristr($record['devicefull'],'AWS')) {
    // FIXME get awsregion from the subnet name
    list($status, $rows, $subnet) = ona_get_subnet_record(array('id' => $record['subnet_id']));
    list($status, $rows, $subnettype) = ona_get_subnet_type_record(array('id' => $subnet['subnet_type_id']));
    // get the region info from the type
    // Assuming format of "AWS <region>"
    list ($junk,$awsregion) = explode(' ',$subnettype['display_name']);
    //$awsregion = 'us-west-2';

    $boxheight = '150px';
    $divid = 'hostawsinfo'; 

    $title_right_html .= <<<EOL
        <span id="vspherelinks"></span><a title="Reload info" onclick="el('vspherelinks').innerHTML = '';el('{$divid}').innerHTML = '<center>Reloading...</center>';xajax_window_submit('{$file}', xajax.getFormValues('awsinfo_form'), 'aws_display_stats');"><img src="{$images}/silk/arrow_refresh.png" border="0"></a>
EOL;

    $modbodyhtml .= <<<EOL
<form id="awsinfo_form" onSubmit="return false;">
<input type="hidden" name="divname" value="{$divid}">
<input type="hidden" name="hostname" value="{$record['name']}">
<input type="hidden" name="domainname" value="{$record['domain_fqdn']}">
<input type="hidden" name="awsregion" value="{$awsregion}">
</form>
<div id="{$divid}" style="height: {$boxheight};overflow-y: auto;overflow-x:hidden;font-size:small">
<center><img src="{$images}/loading.gif"></center><br>
</div>
EOL;

    // run the function that will update the content of the plugin. update it every 5 mins
    $modjs = "xajax_window_submit('{$file}', xajax.getFormValues('awsinfo_form'), 'aws_display_stats');";

  }
}

// Display on subnet pages
if ($extravars['window_name'] == 'display_subnet') {
  if (stristr($record['type'],'AWS')) {

    $divid = 'subnetawsinfo'; 

    // get the region info from the type
    // Assuming format of "AWS <region>"
    list ($junk,$awsregion) = explode(' ',$record['type']);

    if($extravars['window_name'] == 'display_subnet') { $boxheight = '150px'; $divid = 'subnetawsinfo'; }

    $title_right_html .= <<<EOL
        <span id="vspherelinks"></span><a title="Reload info" onclick="el('vspherelinks').innerHTML = '';el('{$divid}').innerHTML = '<center>Reloading...</center>';xajax_window_submit('{$file}', xajax.getFormValues('awsinfo_form'), 'aws_display_stats');"><img src="{$images}/silk/arrow_refresh.png" border="0"></a>
EOL;

    $modbodyhtml .= <<<EOL
<form id="awsinfo_form" onSubmit="return false;">
<input type="hidden" name="divname" value="{$divid}">
<input type="hidden" name="subnetip" value="{$record['ip_addr']}">
<input type="hidden" name="subnetmask" value="{$record['ip_subnet_mask_cidr']}">
<input type="hidden" name="awsregion" value="{$awsregion}">
</form>
<div id="{$divid}" style="height: {$boxheight};overflow-y: auto;overflow-x:hidden;font-size:small">
<center><img src="{$images}/loading.gif"></center><br>
</div>
EOL;

    // run the function that will update the content of the plugin. update it every 5 mins
    $modjs = "xajax_window_submit('{$file}', xajax.getFormValues('awsinfo_form'), 'aws_display_stats');";

  }
}




/*
Gather AWS information
Then update the awsinfo innerHTML with the data.
*/
function ws_aws_display_stats($window_name, $form='') {
    global $conf, $self, $onadb, $onabase, $base, $images, $baseURL;

    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);
    $awsregionlower=strtolower($form['awsregion']);

    // Pull in config file data
    $awsconffile = (file_exists($onabase.'/etc/amazon_aws_detail.conf.php')) ? $onabase.'/etc/amazon_aws_detail.conf.php' : dirname(__FILE__).'/amazon_aws_detail.conf.php';
    if (file_exists($awsconffile)) {
        require_once($awsconffile);
        if (!isset($awsRegionKeys[$awsregionlower])) {
            $htmllines .= <<<EOL
                No Amazon Keys defined for region: $awsregionlower<br>
EOL;
        }

        // Connect with creds, should only be readonly
        $ec2client = Ec2Client::factory(array(
            'key'    => $awsRegionKeys[$awsregionlower]['key'],
            'secret' => $awsRegionKeys[$awsregionlower]['secret'],
            'region' => $awsregionlower
        ));



    // If we never found our host, say so and bail
    } else {
        $htmllines .= <<<EOL
                No config file found.<br>
EOL;
        $response = new xajaxResponse();
        $response->addAssign($form['divname'], "innerHTML", $htmllines);
        return($response->getXML());
    }


    // Display on hosts
    if ($form['divname'] == 'hostawsinfo') {

        // Get list of the running instances based on a filter
        $instance = $ec2client->describeInstances(array(
          'Filters' => array(
              array(
                  'Name' => 'tag:Name',
                  'Values' => array($form['hostname']),
              ),
              array(
                  'Name' => 'tag:domain',
                  'Values' => array($form['domainname']),
              ),
          ),
        ))->toArray();

        // If we dont find it, bail
        if (!$instance['Reservations']) {
            $htmllines .= <<<EOL
                    Device not found in AWS<br>
EOL;
            $response = new xajaxResponse();
            $response->addAssign($form['divname'], "innerHTML", $htmllines);
            return($response->getXML());
        }


        // Loop through the Reservations
        // Reservations are created each time you spin up a specific set of instances
        foreach ( $instance['Reservations'] as $res ) {
          // Loop through the instances in each reservation
          foreach ($res['Instances'] as $resinst ) {
            // Clear variables
            $nametag = '';
            $domaintag = '';
            // Gather tags
            foreach ($resinst['Tags'] as $tags ) {
              // Capture the 'name' tag for later.. not the best way to do things but its what is for now
              if ( $tags['Key'] == 'Name' ) { $nametag = $tags['Value']; }
              if ( $tags['Key'] == 'domain' ) { $domaintag = $tags['Value']; }
              // get a list of all the key/value pairs of tags
              $taglist="$taglist {$tags['Key']}:{$tags['Value']}<br>";
            }

            // gather interfaces
            // FIXME this ASSUMES device instance equates to the ethernet interface.. we'll see
            foreach ($resinst['NetworkInterfaces'] as $ints ) {
                //echo "      {$ints['NetworkInterfaceId']} eth{$ints['Attachment']['DeviceIndex']} {$ints['PrivateIpAddress']} {$ints['macAddress']} {$ints['PrivateDnsName']}\n";

            }
          }
        }

        $state=$resinst['State']['Name'];

        $htmllines .= <<<EOL
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">REGION</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${form['awsregion']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">VPCID</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${resinst['VpcId']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">INSTANCEID</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${resinst['InstanceId']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">STATE</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${state}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">TYPE</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${resinst['InstanceType']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">TAGS</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${taglist}</td>
        </tr>
EOL;

    }




    // Display on subnets
    if ($form['divname'] == 'subnetawsinfo') {
        // Get content of a specific subnet, expect only one back
        $subnets = $ec2client->describeSubnets(array(
            'Filters' => array(
                array(
                    'Name' => 'cidr',
                    'Values' => array($form['subnetip'].'/'.$form['subnetmask']),
                ),
        )))->toArray();
    
        // Just get the first one
        $subnet = $subnets['Subnets'][0];
    
        // If we dont find it, bail
        if (!$subnet['SubnetId']) {
            $htmllines .= <<<EOL
                    Subnet not found in AWS<br>
EOL;
            $response = new xajaxResponse();
            $response->addAssign($form['divname'], "innerHTML", $htmllines);
            return($response->getXML());
        }


        $htmllines .= <<<EOL
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">REGION</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${form['awsregion']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">VPCID</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${subnet['VpcId']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">SUBNETID</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${subnet['SubnetId']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">AZ</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${subnet['AvailabilityZone']}</td>
        </tr>
        <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
            <td class="list-row" style="background-color: {$color};">AVAILABLE IPs</td>
            <td class="list-row" style="border-left: 1px solid; border-left-color: #aaaaaa;">${subnet['AvailableIpAddressCount']}</td>
        </tr>
EOL;

    }








    $html .= '<table class="list-box" cellspacing="0" border="0" cellpadding="0">';
    $html .= $htmllines;
    $html .= "</table>";

    // Insert the new table into the window
    $response = new xajaxResponse();
    $response->addAssign($form['divname'], "innerHTML", $html);
    $response->addScript($js);
    return($response->getXML());
}







?>
