amazon_aws_detail
=================

This OpenNetAdmin plugin will display detail information on a detail page about AWS.
When installed you get information about VPC, Region, state, tags for hosts and subnets.

Install
=======

* Install via standard method outlined here: https://github.com/opennetadmin/ona/wiki/Plugins
* Install aws-sdk (silly me I dont even know what version I used, figure this out and fix the info here) https://aws.amazon.com/sdk-for-php/
  * the sdk should be in the `aws-sdk` directory
  * `mkdir aws-sdk;cd aws-sdk;wget https://github.com/aws/aws-sdk-php/releases/download/3.9.4/aws.zip;unzip aws.zip`
* Add an AWS manufacturer and device type.  the installer script will do this for you in the future. i.e. 'AWS, EC2 Instance (Virtual Server)'
* Add a subnet type that is prefixed with AWS and contains the region.  i.e. 'AWS us-west-2'. Installer will do this in future.
* Copy the amazon_aws_detail.conf.php.example file to /opt/ona/etc/amazon_aws_detail.conf.php and add your key/secret 

TODO
====
* make an installer
* set up a better central sdk link
* set up a central way to configure the keys
*  installer should add the new subnet type and device types
* if it does not find the subnet on AWS, prompt to add it based on the info in ONA?
  This would likely need some input options, yet to be determined.
