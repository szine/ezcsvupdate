#!/usr/bin/env php
<?php
//
// Definition of eZCsvexport class
//
// Created on: <27-Sep-2006 17:23:23 sp>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Publish Community Project
// SOFTWARE RELEASE:  4.2011
// COPYRIGHT NOTICE: Copyright (C) 1999-2011 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
// 
//   This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
// 
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*! \file
*/

require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish CSV export script\n" .
                                                        "\n" .
                                                        "ezcsvexport.php --storage-dir=export 2" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[storage-dir:]",
                                "[node]",
                                array( 'storage-dir' => 'directory to place exported files in' ),
                                false,
                                array( 'user' => true ) );
$script->initialize();

if ( count( $options['arguments'] ) < 1 )
{
    $cli->error( 'Specify a node to export' );
    $script->shutdown( 1 );
}

$nodeID = $options['arguments'][0];

if ( !is_numeric( $nodeID ) )
{
    $cli->error( 'Specify a numeric node ID' );
    $script->shutdown( 2 );
}

if ( $options['storage-dir'] )
{
    $storageDir = $options['storage-dir'];
}
else
{
    $storageDir = '';
}

$node = eZContentObjectTreeNode::fetch( $nodeID );
if ( !$node )
{
    $cli->error( "No node with ID: $nodeID" );
    $script->shutdown( 3 );
}

$cli->output( "Going to export subtree from node $nodeID to directory $storageDir \n" );

$subTreeCount = $node->subTreeCount();

$script->setIterationData( '.', '~' );

$script->resetIteration( $subTreeCount );

$subTree = $node->subTree();
$openedFPs = array();

$ignoredDataType = array("ezboolean","ezdatetime","ezobjectrelationlist");
  
while ( list( $key, $childNode ) = each( $subTree ) )
{
    $status = true;

    $object = $childNode->attribute( 'object' );

    $classIdentifier = $object->attribute( 'class_identifier' );

    if ( !isset( $openedFPs[$classIdentifier] ) )
    {
        $tempFP = @fopen( $storageDir . '/' . $classIdentifier . '.csv', "w" );
		$objectData = array();
		$objectData[] = "objectID";
		foreach($object->DataMap() as $attribute) {
			$identifier = $attribute->ContentClassAttributeIdentifier;
			$datatypeString = $attribute->DataTypeString;
			if (!in_array($datatypeString, $ignoredDataType)) {
				$objectData[] = mb_convert_encoding($identifier, 'Windows-1252', 'UTF-8');
			}
		}
        if ( $tempFP )
        {
            $openedFPs[$classIdentifier] = $tempFP;
			if ( !fputcsv( $openedFPs[$classIdentifier], $objectData, ';' ) )
			{
				$cli->error( "Can not write to file" );
				$script->shutdown( 6 );
			}
        }
        else
        {
            $cli->error( "Can not open output file for $classIdentifier class" );
            $script->shutdown( 4 );
        }
    }
    else
    {
        if ( !$openedFPs[$classIdentifier] )
        {
            $cli->error( "Can not open output file for $classIdentifier class" );
            $script->shutdown( 4 );
        }
    }

    $fp = $openedFPs[$classIdentifier];

    $objectData = array();
		
	$objectData[] = $object->attribute('id');
		
    foreach ( $object->attribute( 'contentobject_attributes' ) as $attribute )
    {
		$datatypeString = $attribute->DataTypeString;
		
		if (!in_array($datatypeString, $ignoredDataType)) {
			$attributeStringContent = $attribute->toString();
			if ( $attributeStringContent != '' )
			{
				switch ( $datatypeString )
				{
					case 'ezimage':
					{
						$imageAlt = explode( '|', $attributeStringContent);
						$imageAlt = $imageAlt[1];	
						$attributeStringContent = $imageAlt;
						
					} break;

					case 'ezbinaryfile':
					case 'ezmedia':
					{
						$binaryData = explode( '|', $attributeStringContent );
						$success = eZFileHandler::copy( $binaryData[0], $storageDir . '/' . $binaryData[1] );
						if ( !$success )
						{
							$status = false;
						}
						$attributeStringContent = $binaryData[1];
					} break;
					case 'ezxmltext':
					{
						
						$attributeStringContent = str_replace('<?xml version="1.0" encoding="utf-8"?>
<section xmlns:image="http://ez.no/namespaces/ezpublish3/image/" xmlns:xhtml="http://ez.no/namespaces/ezpublish3/xhtml/" xmlns:custom="http://ez.no/namespaces/ezpublish3/custom/">', "", $attributeStringContent);
						$attributeStringContent = str_replace(' xmlns:tmp="http://ez.no/namespaces/ezpublish3/temporary/"',"",$attributeStringContent);
					}break;
					

					default:
				}
			}
			
			
			
			
			$attributeStringContent = convert_smart_quotes($attributeStringContent) ;
			$objectData[] = mb_convert_encoding($attributeStringContent, 'ISO-8859-15', 'UTF-8');

		}
    }

    if ( !$fp )
    {
        $cli->error( "Can not open output file" );
        $script->shutdown( 5 );
    }

    if ( !fputcsv( $fp, $objectData, ';' ) )
    {
        $cli->error( "Can not write to file" );
        $script->shutdown( 6 );
    }

    $script->iterate( $cli, $status );
}

while ( $fp = each( $openedFPs ) )
{
    fclose( $fp['value'] );
}

$script->shutdown();

function convert_smart_quotes($string) 
{ 
    $search = array(chr(145), 
                    chr(146), 
                    chr(147), 
                    chr(148), 
                    chr(151),
					chr(226)
					); 

    $replace = array("'", 
                     "'", 
                     '"', 
                     '"', 
                     '-',
					 "'"); 

    return str_replace($search, $replace, $string); 
}

?>
