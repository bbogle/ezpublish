<?php
//
// Definition of eZImageType class
//
// Created on: <30-Apr-2002 13:06:21 bf>
//
// Copyright (C) 1999-2002 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/home/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*!
  \class eZImageType ezimagetype.php
  \ingroup eZKernel
  \brief The class eZImageType handles image accounts and association with content objects

*/

include_once( "kernel/classes/ezdatatype.php" );
include_once( "kernel/classes/datatypes/ezimage/ezimage.php" );
include_once( "lib/ezutils/classes/ezdir.php" );
include_once( "lib/ezutils/classes/ezhttpfile.php" );
include_once( "lib/ezutils/classes/ezdir.php" );

define( 'EZ_DATATYPESTRING_MAX_IMAGE_FILESIZE_FIELD', 'data_int1' );
define( 'EZ_DATATYPESTRING_MAX_IMAGE_FILESIZE_VARIABLE', '_ezimage_max_filesize_' );
define( "EZ_DATATYPESTRING_IMAGE", "ezimage" );

class eZImageType extends eZDataType
{
    function eZImageType()
    {
        $this->eZDataType( EZ_DATATYPESTRING_IMAGE, "Image" );
    }

    function hasAttribute( $name )
    {
        return eZDataType::hasAttribute( $name );
    }

    function &attribute( $name )
    {
        return eZDataType::attribute( $name );
    }

    /*!
     Sets value according to current version
    */
    function initializeObjectAttribute( &$contentObjectAttribute, $currentVersion )
    {
        $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
        $version = $contentObjectAttribute->attribute( "version" );
        $oldimage =& eZImage::fetch( $contentObjectAttributeID, $currentVersion );
        if( $oldimage != null )
        {
            $oldimage->setAttribute( "version",  $version );
            $oldimage->store();
        }
    }

    /*!
     Delete stored attribute
    */
    function deleteStoredObjectAttribute( &$contentObjectAttribute, $version = null )
    {
        $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
        $orig_dir = "var/storage/original/image";
        $ref_dir =  "var/storage/reference/image";
        $vari_dir = "var/storage/variations/image";
        $images =& eZImage::fetch( $contentObjectAttributeID );
        if( $version == null )
        {
            foreach ( $images as $image )
            {
                $fileName = $image->attribute( "filename" );
                $variationFileName = preg_replace('/\.(.*)$/', "", $fileName ) ;
                $additionalPath = eZDir::getPathFromFilename( $fileName );
                if( file_exists( $orig_dir . "/" . $variationFileName ) )
                    unlink( $orig_dir . "/" . $variationFileName );
                if( file_exists( $orig_dir . "/" .$fileName ) )
                    unlink( $orig_dir . "/" . $fileName );
                if( file_exists( $ref_dir . "/" . $variationFileName ) )
                    unlink( $ref_dir . "/" . $variationFileName );
                if( file_exists( $ref_dir . "/" . $fileName ) )
                    unlink( $ref_dir . "/" . $fileName );
                $dir = opendir(  $vari_dir . "/" . $additionalPath );
                while ( $file = readdir($dir))
                {
                    if( preg_match( "/$variationFileName/", $file ) )
                         unlink( $vari_dir . "/" . $additionalPath . $file );
                }
            }
        }
        else
        {
            $count = 0;
            $currentImage =& eZImage::fetch( $contentObjectAttributeID, $version );
            if ( $currentImage !== null )
                $currentFileName = $currentImage->attribute( "filename" );
            foreach ( $images as $image )
            {
                $fileName = $image->attribute( "filename" );
                if( $currentFileName == $fileName )
                     $count += 1;
            }
            if( $count == 1 )
            {
                $variationFileName = preg_replace('/\.(.*)$/', "", $currentFileName ) ;
                $additionalPath = eZDir::getPathFromFilename( $currentFileName );
                if( file_exists( $orig_dir . "/" . $currentFileName ) )
                    unlink( $orig_dir . "/" .  $currentFileName );
                if( file_exists( $orig_dir . "/" . $variationFileName ) )
                    unlink( $orig_dir . "/" . $variationFileName );
                if( file_exists( $ref_dir . "/" .  $currentFileName ) )
                    unlink( $ref_dir . "/" .  $currentFileName );
                if( file_exists( $ref_dir . "/" . $variationFileName ) )
                    unlink( $ref_dir . "/" . $variationFileName );
                $dir = opendir(  $vari_dir . "/" . $additionalPath );
                while ( $file = readdir($dir))
                {
                    if( preg_match( "/$variationFileName/", $file ) )
                         unlink( $vari_dir . "/" . $additionalPath . $file );
                }
            }
        }
        eZImage::remove( $contentObjectAttributeID, $version );
        eZImageVariation::removeVariation( $contentObjectAttributeID, $version );

    }

    /*!
     Validates the input and returns true if the input was
     valid for this datatype.
    */
    function validateObjectAttributeHTTPInput( &$http, $base, &$contentObjectAttribute )
    {
        $classAttribute =& $contentObjectAttribute->contentClassAttribute();
        if( $classAttribute->attribute( "is_required" ) == true )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );
            $image =& eZImage::fetch( $contentObjectAttributeID, $version );
            if ( $image === null )
            {
                $file =& eZHTTPFile::fetch( $base . "_data_imagename_" . $contentObjectAttribute->attribute( "id" ) );
                if ( $file === null )
                {
                    $contentObjectAttribute->setValidationError( ezi18n( 'content/datatypes',
                                                                         'A valid image is required.',
                                                                         'eZImageType' ) );
                    return EZ_INPUT_VALIDATOR_STATE_INVALID;
                }
            }
        }
        return EZ_INPUT_VALIDATOR_STATE_ACCEPTED;
    }

    /*!
     Fetches the http post var integer input and stores it in the data instance.
    */
    function fetchObjectAttributeHTTPInput( &$http, $base, &$contentObjectAttribute )
    {
        if ( !eZHTTPFile::canFetch( $base . "_data_imagename_" . $contentObjectAttribute->attribute( "id" ) ) )
            return false;

        $imageFile =& eZHTTPFile::fetch( $base . "_data_imagename_" . $contentObjectAttribute->attribute( "id" ) );
        $contentObjectAttribute->setContent( $imageFile );

        if ( get_class( $imageFile ) == "ezhttpfile" )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );

            include_once( "kernel/common/image.php" );

            $img =& imageInit();

            $mime = $img->mimeTypeFor( $imageFile->attribute( "original_filename" ), true );

            if ( !$imageFile->store( "original", $mime["suffix"] ) )
            {
                eZDebug::writeError( "Failed to store http-file: " . $imageFile->attribute( "original_filename" ),
                                     "eZImageType" );
                return false;
            }

            $image =& eZImage::fetch( $contentObjectAttributeID, $version );
            if ( $image === null )
                $image =& eZImage::create( $contentObjectAttributeID , $version );

            $orig_dir = $imageFile->storageDir( "original" );
            $ref_dir = $imageFile->storageDir( "reference" );
            eZDebug::writeNotice( "dir=$ref_dir" );

            $image->setAttribute( "contentobject_attribute_id", $contentObjectAttributeID );
            $image->setAttribute( "version", $version );
            $image->setAttribute( "filename", basename( $imageFile->attribute( "filename" ) ) );
            $image->setAttribute( "original_filename", $imageFile->attribute( "original_filename" ) );
            $image->setAttribute( "mime_type", $imageFile->attribute( "mime_type" ) );

            $image->store();

            if ( !file_exists( $ref_dir ) )
            {
                $ini =& eZINI::instance();
                $perm = $ini->variable( "ImageSettings", "NewDirPermissions" );
                eZDir::mkdir( $ref_dir, octdec( $perm ), true );
            }
            $ref_imagename = $img->convert( $imageFile->attribute( "filename" ),
                                            $ref_dir, array( "width" => 400, "height" => 300 ),
                                            false, $mime );

            $contentObjectAttribute->setContent( $image );
        }
    }

    /*!
     Does nothing, since the image has been stored. See fetchObjectAttributeHTTPInput for the actual storing.
    */
    function storeObjectAttribute( &$contentObjectAttribute )
    {
    }

    function fetchClassAttributeHTTPInput( &$http, $base, &$classAttribute )
    {
        $filesizeName = $base . EZ_DATATYPESTRING_MAX_IMAGE_FILESIZE_VARIABLE . $classAttribute->attribute( 'id' );
        if ( $http->hasPostVariable( $filesizeName ) )
        {
            $filesizeValue = $http->postVariable( $filesizeName );
            $classAttribute->setAttribute( EZ_DATATYPESTRING_MAX_IMAGE_FILESIZE_FIELD, $filesizeValue );
        }
    }

    function customObjectAttributeHTTPAction( $http, $action, &$contentObjectAttribute )
    {
        if( $action == "delete_image" )
        {
            $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
            $version = $contentObjectAttribute->attribute( "version" );
            $this->deleteStoredObjectAttribute( &$contentObjectAttribute, $version );
        }
    }

    /*!
     Returns attribute of an image, default will return filename of the image.
    */
    function title( &$contentObjectAttribute, $name = "filename" )
    {
        $image =& eZImage::fetch( $contentObjectAttribute->attribute( "id" ),
                                  $contentObjectAttribute->attribute( "version" ) );

        $value = $image->attribute( $name );

        return $value;
    }

    /*!
     \todo use static members for caching when it's available in PHP5
    */
    function &objectAttributeContent( $contentObjectAttribute )
    {
        // Cache the attribute
        $cacheString = "eZImageTypeCache-".$contentObjectAttribute->attribute( "id" ) . "-" . $contentObjectAttribute->attribute( "version" );

        if ( !isset( $GLOBALS[$cacheString] ) )
        {
            $image =& eZImage::fetch( $contentObjectAttribute->attribute( "id" ),
                                      $contentObjectAttribute->attribute( "version" ) );
            $GLOBALS[$cacheString] =& $image;
        }
        else
        {
            $image =& $GLOBALS[$cacheString];
        }
        return $image;
    }

    function metaData()
    {
        return "";
    }

}

eZDataType::register( EZ_DATATYPESTRING_IMAGE, "ezimagetype" );

?>
