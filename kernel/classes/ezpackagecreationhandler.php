<?php
//
// Definition of eZPackageCreationHandler class
//
// Created on: <21-Nov-2003 11:52:36 amos>
//
// Copyright (C) 1999-2004 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file ezpackagecreationhandler.php
*/

/*!
  \ingroup package
  \class eZPackageCreationHandler ezpackagecreationhandler.php
  \brief The class eZPackageCreationHandler does

*/

class eZPackageCreationHandler
{
    /*!
     Constructor
    */
    function eZPackageCreationHandler( $id, $name, $steps )
    {
        $this->Attributes = array( 'id' => $id,
                                   'name' => $name,
                                   'steps' => $steps,
                                   'step_map' => false,
                                   'current_steps' => $steps );
        $this->InitializeStepMethodMap = array();
        $this->ValidateStepMethodMap = array();
        $this->CommitStepMethodMap = array();
        $this->LoadStepMethodMap = array();
    }

	/*!
	 Will go over the steps and make sure that:
	 - The next and previous links are correct
	 - Steps that aren't needed are removed

 	 It will also make sure that steps can be looked up by their ID.
	*/
	function generateStepMap( &$package, &$persistentData )
	{
		$steps = $this->attribute( 'steps' );
        $map = array();
        $lastStep = false;
        $currentSteps = array();
        for ( $i = 0; $i < count( $steps ); ++$i )
        {
            $step =& $steps[$i];
            if ( !isset( $step['previous_step'] ) )
            {
                if ( $lastStep )
                    $step['previous_step'] = $lastStep['id'];
                else
                    $step['previous_step'] = false;
            }
            if ( !isset( $step['next_step'] ) )
            {
                if ( $i + 1 < count( $steps ) )
                    $step['next_step'] = $steps[$i+1]['id'];
                else
                    $step['next_step'] = false;
            }
			if ( isset( $step['methods']['initialize'] ) )
			    $this->InitializeStepMethodMap[$step['id']] = $step['methods']['initialize'];
            if( isset( $step['methods']['load'] ) )
                $this->LoadStepMethodMap[$step['id']] = $step['methods']['load'];
			if ( isset( $step['methods']['validate'] ) )
			    $this->ValidateStepMethodMap[$step['id']] = $step['methods']['validate'];
			if ( isset( $step['methods']['commit'] ) )
			    $this->CommitStepMethodMap[$step['id']] = $step['methods']['commit'];
			$isStepIncluded = true;
			if ( isset( $step['methods']['check'] ) )
			{
				$checkMethod = $step['methods']['check'];
				$isStepIncluded = $this->$checkMethod( $package, $persistentData );
			}
			if ( $isStepIncluded )
			{
	            $map[$step['id']] =& $step;
    	        $lastStep =& $step;
    	        $currentSteps[] =& $step;
			}
        }
        $this->StepMap = array( 'first' => &$steps[0],
                                'map' => &$map,
                                'steps' => &$steps );
        $this->Attributes['step_map'] =& $this->StepMap;
        $this->Attributes['current_steps'] = $currentSteps;
	}

    function attributes()
    {
        return array_keys( $this->Attributes );
    }

    function hasAttribute( $name )
    {
        return array_key_exists( $name, $this->Attributes );
    }

    function &attribute( $name )
    {
        if ( array_key_exists( $name, $this->Attributes ) )
            return $this->Attributes[$name];
        return null;
    }

    function initializeStepMethodMap()
    {
        return $this->InitializeStepMethodMap;
    }

    function loadStepMethodMap()
    {
        return $this->LoadStepMethodMap;
    }

    function validateStepMethodMap()
    {
        return $this->ValidateStepMethodMap;
    }

    function commitStepMethodMap()
    {
        return $this->CommitStepMethodMap;
    }

    /*!
	 \return a process step map which has proper next/previous links,
	         method maps and allows lookup of steps by ID.
    */
    function &stepMap()
    {
        return $this->StepMap;
    }

    /*!
     \virtual
    */
    function stepTemplate( $step )
    {
        $stepTemplateName = $step['template'];
        if ( isset( $step['use_standard_template'] ) and
             $step['use_standard_template'] )
            $stepTemplateDir = "create";
        else
            $stepTemplateDir = "creators/" . $this->attribute( 'id' );
        return array( 'name' => $stepTemplateName,
                      'dir' => $stepTemplateDir );
    }

    /*!
     \virtual
     This is called the first time the step is entered (ie. not on validations)
     and can be used to fill in values in the \a $persistentData variable
     for use in the template or later retrieval.
    */
    function initializeStep( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        $methodMap = $this->initializeStepMethodMap();
        if ( count( $methodMap ) > 0 )
        {
            if ( isset( $methodMap[$step['id']] ) )
            {
                $method = $methodMap[$step['id']];
                return $this->$method( $package, $http, $step, $persistentData, $tpl );
            }
        }
    }

    /*!
     \virtual
     Called each time a step is loaded, and can be used to fetch and process input data in each step.
    */
    function loadStep( &$package, &$http, $currentStepID, &$persistentData, &$tpl, &$module )
    {
        $methodMap = $this->loadStepMethodMap();
        if ( count( $methodMap ) > 0 )
        {
            if ( isset( $methodMap[$currentStepID] ) )
            {
                $method = $methodMap[$currentStepID];
                return $this->$method( $package, $http, $currentStepID, $persistentData, $tpl, $module );
            }
        }
    }

    /*!
     This is called after a step is finished. Reimplement this function to validate
     the step values and give back errors.
     \return \c false if the next step should not be fetched (ie. errors) or
             \c true if the all is OK and the next step should be fetched.
             It is also possible to return a step identifier, in which case
             this will be the next step.
    */
    function validateStep( &$package, &$http, $currentStepID, &$stepMap, &$persistentData, &$errorList )
    {
        $nextStep = $this->validateAndAdvanceStep( $package, $http, $currentStepID, $stepMap, $persistentData, $errorList );
        if ( $nextStep === true )
        {
            if ( !isset( $stepMap['map'][$currentStepID] ) )
            {
                $nextStep = $stepMap['first']['id'];
            }
            else
            {
                $currentStep =& $stepMap['map'][$currentStepID];
                $nextStep = $currentStep['next_step'];
            }
        }
        else if ( $nextStep === false )
            $nextStep = $currentStepID;
        return $nextStep;
    }

    /*!
     \virtual
    */
    function validateAndAdvanceStep( &$package, &$http, $currentStepID, &$stepMap, &$persistentData, &$errorList )
    {
        $methodMap = $this->validateStepMethodMap();
        if ( count( $methodMap ) > 0 )
        {
            if ( isset( $methodMap[$currentStepID] ) )
            {
                $method = $methodMap[$currentStepID];
                return $this->$method( $package, $http, $currentStepID, $stepMap, $persistentData, $errorList );
            }
        }
        return true;
    }

    /*!
     \virtual
     This is called after a step has validated it's information. It can
     be used to put values in the \a $persistentData variable for later retrieval.
    */
    function commitStep( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        $methodMap = $this->commitStepMethodMap();
        if ( count( $methodMap ) > 0 )
        {
            if ( isset( $methodMap[$step['id']] ) )
            {
                $method = $methodMap[$step['id']];
                return $this->$method( $package, $http, $step, $persistentData, $tpl );
            }
        }
    }

    /*!
     \virtual
     Finalizes the creation process with the gathered information.
     This is usually the function that creates the package and
     adds the proper elements.
    */
    function finalize( &$package, &$http, &$persistentData )
    {
    }

    /*!
     \static
     \return a list of the available creators usable as a limitation in the role system.
    */
    function creatorLimitationList()
    {
        $creators =& eZPackageCreationHandler::creatorList();
        $list = array();
        foreach ( $creators as $creator )
        {
            $list[] = array( 'name' => $creator->attribute( 'name' ),
                             'id' => $creator->attribute( 'id' ) );
        }
        return $list;
    }

    /*!
     \static
     \return a list of the available creators.
    */
    function &creatorList( $checkRoles = false )
    {
        $allowedCreators = false;

		include_once( "kernel/classes/datatypes/ezuser/ezuser.php" );
		$currentUser =& eZUser::currentUser();
		$accessResult = $currentUser->hasAccessTo( 'package', 'create' );
	    $limitationList = array();
	    $canCreate = false;
		if ( $accessResult['accessWord'] == 'no' )
		    return array();
		if ( $accessResult['accessWord'] == 'limited' )
		{
		    $limitationList =& $accessResult['policies'];
            foreach( $limitationList as $dummyKey => $limitationArray ) // TODO : fix this
            {
                foreach ( $limitationArray as $key => $limitation )
                {
                    if ( $key == 'CreatorType' )
                    {
                        if ( !is_array( $allowedCreators ) )
                            $allowedCreators = array();
                        $list = $limitation;
                        $allowedCreators = array_merge( $allowedCreators, $list );
                    }
				}
			}
		}

        $creators =& $GLOBALS['eZPackageCreatorList'];
        if ( !isset( $creators ) )
        {
            $creators = array();
            $ini =& eZINI::instance( 'package.ini' );
            $list = $ini->variable( 'CreationSettings', 'HandlerList' );
            foreach ( $list as $name )
            {
                if ( is_array( $allowedCreators ) and
                     !in_array( $name, $allowedCreators ) )
                     continue;
                $handler =& eZPackageCreationHandler::instance( $name );
                $creators[] =& $handler;
            }
        }
        return $creators;
    }

    /*!
     \return the package creation handler object for the handler named \a $handlerName.
    */
    function &instance( $handlerName )
    {
        $handlers =& $GLOBALS['eZPackageCreationHandlers'];
        if ( !isset( $handlers ) )
            $handlers = array();
        $handler = false;
        if ( eZExtension::findExtensionType( array( 'ini-name' => 'package.ini',
                                                    'repository-group' => 'PackageSettings',
                                                    'repository-variable' => 'RepositoryDirectories',
                                                    'extension-group' => 'PackageSettings',
                                                    'extension-variable' => 'ExtensionDirectories',
                                                    'subdir' => 'packagecreators',
                                                    'extension-subdir' => 'packagecreators',
                                                    'suffix-name' => 'packagecreator.php',
                                                    'type-directory' => true,
                                                    'type' => $handlerName,
                                                    'alias-group' => 'CreationSettings',
                                                    'alias-variable' => 'HandlerAlias' ),
                                             $result ) )
        {
            $handlerFile = $result['found-file-path'];
            if ( file_exists( $handlerFile ) )
            {
                include_once( $handlerFile );
                $handlerClassName = $result['type'] . 'PackageCreator';
                if ( isset( $handlers[$result['type']] ) )
                {
                    $handler =& $handlers[$result['type']];
                    $handler->reset();
                }
                else
                {
                    $handler =& new $handlerClassName( $handlerName );
                    $handlers[$result['type']] =& $handler;
                }
            }
        }
        return $handler;
    }

    /*!
     \static
     \return A ready to use creation step which takes care of package information.
    */
	function packageInformationStep()
	{
		return array( 'id' => 'packageinfo',
                      'name' => ezi18n( 'kernel/package', 'Package information' ),
					  'methods' => array( 'initialize' => 'initializePackageInformation',
					                      'validate' => 'validatePackageInformation',
										  'commit' => 'commitPackageInformation' ),
                      'use_standard_template' => true,
                      'template' => 'info.tpl' );
	}

    /*!
     \static
     \return A ready to use creation step which takes care of reading in maintainer information.
    */
	function packageMaintainerStep()
	{
		return array( 'id' => 'packagemaintainer',
                      'name' => ezi18n( 'kernel/package', 'Package maintainer' ),
					  'methods' => array( 'initialize' => 'initializePackageMaintainer',
					                      'validate' => 'validatePackageMaintainer',
										  'commit' => 'commitPackageMaintainer',
										  'check' => 'checkPackageMaintainer' ),
                      'use_standard_template' => true,
                      'template' => 'maintainer.tpl' );
	}

    /*!
     \static
     \return A ready to use creation step which takes care of reading in a changelog entry.
    */
	function packageChangelogStep()
	{
		return array( 'id' => 'packagechangelog',
                      'name' => ezi18n( 'kernel/package', 'Package changelog' ),
					  'methods' => array( 'initialize' => 'initializePackageChangelog',
					                      'validate' => 'validatePackageChangelog',
										  'commit' => 'commitPackageChangelog' ),
                      'use_standard_template' => true,
                      'template' => 'changelog.tpl' );
	}

    /*!
     \static
     \return A ready to use creation step which takes care of fetching a thumbnail image.
    */
	function packageThumbnailStep()
	{
		return array( 'id' => 'packagethumbnail',
                      'name' => ezi18n( 'kernel/package', 'Package thumbnail' ),
					  'methods' => array( 'initialize' => 'initializePackageThumbnail',
					                      'validate' => 'validatePackageThumbnail',
										  'commit' => 'commitPackageThumbnail' ),
                      'use_standard_template' => true,
                      'template' => 'thumbnail.tpl' );
	}

    /*!
     \virtual
     \return the type installation this package uses.

     This method is called from the createPackage() method and will return \c 'install' by default.
     If you want the creator to have a different install type reimplement this function in the creator.
    */
    function packageInstallType( &$package, &$persistentData )
    {
        return 'install';
    }

    /*!
     \virtual
     \return the initial state of the package.

     The state of a package generally tells how stable a package is,
     see eZPackage::stateList() for more information on possible states.
     \note The default returns \c 'alpha'
    */
    function packageInitialState( &$package, &$persistentData )
    {
        return 'alpha';
    }

    /*!
     \virtual
     \return The initial changelog entry for a package.
     It is possible to get different initial texts by reimplementing this function.

     \note This function is called from initializePackageChangelog()
    */
    function initialChangelogEntry( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        return '- Creation of package.';
    }

    /*!
     \virtual
     \return The package type taken from \a $package if the package exists,
             otherwise \c false.
     If the creator should have a specific package type this function should be reimplemented.
     See eZPackage::typeList() for more information on available types.

     \note This function is called from createPackage and checkPackageMaintainer()
    */
	function packageType( &$package, &$persistentData )
	{
		if ( get_class( $package ) == 'ezpackage' )
		{
		    return $package->attribute( 'type' );
		}
		return false;
	}

    /*!
     Creates a new package in \a $package and initializes it with the
     basic data. The information is taken from the \a $persistentData
     which must be filled in prior to this function is called.
     \return \c true if the package was created or \c false if it was only re-initialized.
     \sa packageType, packageInitialState and packageInstallType
    */
	function createPackage( &$package, &$http, &$persistentData, &$cleanupFiles )
	{
		$createdPackage = false;
		if ( get_class( $package ) != 'ezpackage' )
		{
	        $package = eZPackage::create( $persistentData['name'],
    	                                  array( 'summary' => $persistentData['summary'] ) );
			$createdPackage = true;
		}
		else
			$package->setAttribute( 'summary', $persistentData['summary'] );

        $package->setAttribute( 'is_active', false );
        $package->setAttribute( 'type', $this->packageType( $package, $persistentData ) );

        $package->setRelease( $persistentData['version'], '1', false,
                              $persistentData['licence'], $this->packageInitialState( $package, $persistentData ) );

        $package->setAttribute( 'description', $persistentData['description'] );
        $package->setAttribute( 'install_type', $this->packageInstallType( $package, $persistentData ) );

        $package->setAttribute( 'packaging-host', $persistentData['host'] );
        $package->setAttribute( 'packaging-packager', $persistentData['packager'] );

        $changelogPerson = $persistentData['changelog_person'];
        $changelogEmail = $persistentData['changelog_email'];
        $changelogEntries = $persistentData['changelog_entries'];

        $maintainerPerson = $persistentData['maintainer_person'];
        $maintainerEmail = $persistentData['maintainer_email'];
        $maintainerRole = $persistentData['maintainer_role'];

		if ( $maintainerPerson )
		{
            $package->appendMaintainer( $maintainerPerson, $maintainerEmail, $maintainerRole );
        }

        if ( $changelogPerson )
        {
	        $package->appendChange( $changelogPerson, $changelogEmail, $changelogEntries );
	    }

        if ( $persistentData['licence'] == 'GPL' )
        {
            eZPackageCreationHandler::appendLicence( $package );
        }


        $collections = array();
        $cleanupFiles = array();

		$thumbnail= $persistentData['thumbnail'];
		if ( $thumbnail )
		{
            $fileItem = array( 'file' => $thumbnail['filename'],
                               'type' => 'thumbnail',
                               'role' => false,
                               'design' => false,
                               'path' => $thumbnail['url'],
                               'collection' => 'default',
                               'file-type' => false,
                               'role-value' => false,
                               'variable-name' => false );

            $package->appendFile( $fileItem['file'], $fileItem['type'], $fileItem['role'],
                                  $fileItem['design'], $fileItem['path'], $fileItem['collection'],
                                  null, null, true, null,
                                  $fileItem['file-type'], $fileItem['role-value'], $fileItem['variable-name'] );
            if ( !in_array( $fileItem['collection'], $collections ) )
                $collections[] = $fileItem['collection'];
            $cleanupFiles[] = $fileItem['path'];
        }

        foreach ( $collections as $collection )
        {
            $installItems = $package->installItems( 'ezfile', false, $collection, true );
            if ( count( $installItems ) == 0 )
                $package->appendInstall( 'ezfile', false, false, true,
                                         false, false,
                                         array( 'collection' => $collection ) );
            $dependencyItems = $package->dependencyItems( 'provides', 'ezfile', 'collection', $collection );
            if ( count( $dependencyItems ) == 0 )
                $package->appendDependency( 'provides', 'ezfile', 'collection', $collection );
            $installItems = $package->installItems( 'ezfile', false, $collection, false );
            if ( count( $installItems ) == 0 )
                $package->appendInstall( 'ezfile', false, false, false,
                                         false, false,
                                         array( 'collection' => $collection ) );
        }

        $package->setAttribute( 'is_active', true );
        $package->store();

        return $createdPackage;
	}

    /*!
     \virtual
     This is called on the package information step to initialize the name, summary and description fields.
     Reimplementing this function allows the creator to fill in some default values for the information fields.
     \note The default does nothing.
    */
	function generatePackageInformation( &$packageInformation, &$package, &$http, $step, &$persistentData )
	{
	}

    /*!
     Initializes the package information step with some default values.
     It will call generatePackageInformation() after the values are initialized.
    */
    function initializePackageInformation( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        $persistentData['name'] = false;
        $persistentData['summary'] = false;
        $persistentData['description'] = false;
        $persistentData['licence'] = 'GPL';
        $persistentData['version'] = '1.0';
        if ( isset( $_SERVER['HOSTNAME'] ) )
            $host = $_SERVER['HOSTNAME'];
        else
            $host = $_SERVER['HTTP_HOST'];
        $persistentData['host'] = $host;
        $user =& eZUser::currentUser();
	    $userObject =& $user->attribute( 'contentobject' );
	    $packager = false;
		if ( $userObject )
			$packager = $userObject->attribute( 'name' );
        $persistentData['packager'] = $packager;
		$this->generatePackageInformation( $persistentData, $package, $http, $step, $persistentData );
    }

    /*!
     Reads in the package information values from POST variables and makes sure
     that the package name and package summary is filled in, the version is in correct
     format and that a package does not already exists with the same name.
    */
    function validatePackageInformation( &$package, &$http, $currentStepID, &$stepMap, &$persistentData, &$errorList )
    {
        $packageName = false;
        $packageSummary = false;
        $packageVersion = false;
        $packageDescription = false;
        $packageLicence = 'GPL';
        $packageHost = false;
        $packagePackager = false;
        if ( $http->hasPostVariable( 'PackageName' ) )
        {
            $packageName = trim( $http->postVariable( 'PackageName' ) );

            /* Make sure to generate a package name that can be called through
             * a urlalias */
            include 'lib/ezi18n/classes/ezchartransform.php';
            $trans = new eZCharTransform;
            $packageName = $trans->transformByGroup( $packageName, 'urlalias' );
        }
        if ( $http->hasPostVariable( 'PackageSummary' ) )
            $packageSummary = $http->postVariable( 'PackageSummary' );
        if ( $http->hasPostVariable( 'PackageDescription' ) )
            $packageDescription = $http->postVariable( 'PackageDescription' );
        if ( $http->hasPostVariable( 'PackageVersion' ) )
            $packageVersion = trim( $http->postVariable( 'PackageVersion' ) );
        if ( $http->hasPostVariable( 'PackageLicence' ) )
            $packageLicence = $http->postVariable( 'PackageLicence' );
        if ( $http->hasPostVariable( 'PackageHost' ) )
            $packageHost = $http->postVariable( 'PackageHost' );
        if ( $http->hasPostVariable( 'PackagePackager' ) )
            $packagePackager = $http->postVariable( 'PackagePackager' );

        $persistentData['name'] = $packageName;
        $persistentData['summary'] = $packageSummary;
        $persistentData['description'] = $packageDescription;
        $persistentData['version'] = $packageVersion;
        $persistentData['licence'] = $packageLicence;
        $persistentData['host'] = $packageHost;
        $persistentData['packager'] = $packagePackager;

        $result = true;
        if ( $packageName == '' )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Package name' ),
                                  'description' => ezi18n( 'kernel/package', 'Package name is missing' ) );
            $result = false;
        }
        else
        {
            $existingPackage =& eZPackage::fetch( $packageName, false, true );
            if ( $existingPackage )
            {
                $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Package name' ),
                                      'description' => ezi18n( 'kernel/package', 'A package named %packagename already exists, please give another name', false, array( '%packagename' => $packageName ) ) );
                $result = false;
            }
        }
        if ( !$packageSummary )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Summary' ),
                                  'description' => ezi18n( 'kernel/package', 'Summary is missing' ) );
            $result = false;
        }
        if ( !preg_match( "#^[0-9](\.[0-9]([a-zA-Z]+[0-9]*)?)*$#", $packageVersion ) )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Version' ),
                                  'description' => ezi18n( 'kernel/package', 'The version must only contain numbers (optionally followed by text) and must be delimited by dots (.), e.g. 1.0, 3.4.0beta1' ) );
            $result = false;
        }
        return $result;
    }

    /*!
     Commits package information.
    */
    function commitPackageInformation( &$package, &$http, $step, &$persistentData, &$tpl )
    {
    }

    /*!
     Initializes the package changelog step with some values taken from the
     current users and the funcvtion initialChangelogEntry().
    */
    function initializePackageChangelog( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        $user =& eZUser::currentUser();
	    $userObject =& $user->attribute( 'contentobject' );
		if ( $userObject )
			$changelogPerson = $userObject->attribute( 'name' );
		$changelogEmail = $user->attribute( 'email' );
        $changelogText = '';

        $persistentData['changelog_person'] = $changelogPerson;
        $persistentData['changelog_email'] = $changelogEmail;
        if ( get_class( $package ) != 'ezpackage' )
        {
            $changelogText = $this->initialChangelogEntry( $package, $http, $step, $persistentData, $tpl );
        }
        $persistentData['changelog_text'] = $changelogText;
        $persistentData['changelog_entries'] = array();
    }

    /*!
     Checks if the POST variables contains a name and email for the changelog person and
     the changelog field contains some text.
    */
    function validatePackageChangelog( &$package, &$http, $currentStepID, &$stepMap, &$persistentData, &$errorList )
    {
        $changelogPerson = false;
        $changelogEmail = false;
        $changelogText = false;
        if ( $http->hasPostVariable( 'PackageChangelogPerson' ) )
            $changelogPerson = trim( $http->postVariable( 'PackageChangelogPerson' ) );
        if ( $http->hasPostVariable( 'PackageChangelogEmail' ) )
            $changelogEmail = $http->postVariable( 'PackageChangelogEmail' );
        if ( $http->hasPostVariable( 'PackageChangelogText' ) )
            $changelogText = $http->postVariable( 'PackageChangelogText' );

        $persistentData['changelog_person'] = $changelogPerson;
        $persistentData['changelog_email'] = $changelogEmail;
        $persistentData['changelog_text'] = $changelogText;

        $result = true;
        if ( trim( $changelogPerson ) == '' )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Name' ),
                                  'description' => ezi18n( 'kernel/package', 'You must enter a name for the changelog' ) );
            $result = false;
        }
        if ( trim( $changelogEmail ) == '' )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'E-Mail' ),
                                  'description' => ezi18n( 'kernel/package', 'You must enter an e-mail for the changelog' ) );
            $result = false;
        }
        if ( trim( $changelogText ) == '' )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Changelog' ),
                                  'description' => ezi18n( 'kernel/package', 'You must supply some text for the changelog entry' ) );
            $result = false;
        }
        return $result;
    }

    /*!
     Parses the changelog entry text and turns into an array with change entries.
    */
    function commitPackageChangelog( &$package, &$http, $step, &$persistentData, &$tpl )
    {
		$changelogEntries = array();
		$changelogText = $persistentData['changelog_text'];
		$lines = preg_split( "#\r\n|\n|\r#", $changelogText );
		$currentEntries = false;
		foreach ( $lines as $line )
		{
			if ( strlen( $line ) > 0 and
			     ( $line[0] == '-' or $line[0] == '*' ) )
			{
				if ( $currentEntries !== false )
				{
					$changelogEntries[] = implode( ' ', $currentEntries );
				}
				$currentEntries = array();
				$currentEntries[] = trim( substr( $line, 1 ) );
			}
			else
			{
				if ( $currentEntries === false )
				{
					$changelogEntries = array();
				}
				$currentEntries[] = trim( $line );
			}
		}
		if ( $currentEntries !== false )
		{
			$changelogEntries[] = implode( ' ', $currentEntries );
		}
        $persistentData['changelog_entries'] = $changelogEntries;
    }

    /*!
     Initializes the package maintainer step with some values taken from the current user.
    */
    function initializePackageMaintainer( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        $maintainerPerson = false;
        $maintainerEmail = false;
        $user =& eZUser::currentUser();
   	    $userObject =& $user->attribute( 'contentobject' );
		if ( $userObject )
			$maintainerPerson = $userObject->attribute( 'name' );
		$maintainerEmail = $user->attribute( 'email' );
        $persistentData['maintainer_person'] = $maintainerPerson;
        $persistentData['maintainer_email'] = $maintainerEmail;
        $persistentData['maintainer_role'] = false;
    }

    /*!
     Checks if the POST variables has a name and email for the person.
    */
    function validatePackageMaintainer( &$package, &$http, $currentStepID, &$stepMap, &$persistentData, &$errorList )
    {
        $maintainerPerson = false;
        $maintainerEmail = false;
        $maintainerRole = false;
        if ( $http->hasPostVariable( 'PackageMaintainerPerson' ) )
            $maintainerPerson = trim( $http->postVariable( 'PackageMaintainerPerson' ) );
        if ( $http->hasPostVariable( 'PackageMaintainerEmail' ) )
            $maintainerEmail = $http->postVariable( 'PackageMaintainerEmail' );
        if ( $http->hasPostVariable( 'PackageMaintainerRole' ) )
            $maintainerRole = $http->postVariable( 'PackageMaintainerRole' );

        $persistentData['maintainer_person'] = $maintainerPerson;
        $persistentData['maintainer_email'] = $maintainerEmail;
        $persistentData['maintainer_role'] = $maintainerRole;

        $result = true;
        if ( trim( $maintainerPerson ) == '' )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'Name' ),
                                  'description' => ezi18n( 'kernel/package', 'You must enter a name of the maintainer' ) );
            $result = false;
        }
        if ( trim( $maintainerEmail ) == '' )
        {
            $errorList[] = array( 'field' => ezi18n( 'kernel/package', 'E-Mail' ),
                                  'description' => ezi18n( 'kernel/package', 'You must enter an e-mail address of the maintainer' ) );
            $result = false;
        }
        return $result;
    }

    /*!
     Commits maintainer step data. Does nothing for now.
    */
    function commitPackageMaintainer( &$package, &$http, $step, &$persistentData, &$tpl )
    {
    }

    /*!
     Checks if the maintainer step is required and return \c true if so,
     otherwise \c false.
     The maintainer step is not required if the user has no maintainer roles to use
     or if the package already has a maintainer with the same name as the current user.
    */
	function checkPackageMaintainer( &$package, &$persistentData )
	{
	    $roleList = eZPackage::fetchMaintainerRoleIDList( $this->packageType( $package, $persistentData ), true );
	    if ( count( $roleList ) > 0 )
	    {
	        if ( get_class( $package ) == 'ezpackage' )
	        {
	            $maintainerPerson = false;
                $user =& eZUser::currentUser();
           	    $userObject =& $user->attribute( 'contentobject' );
        		if ( $userObject )
        			$maintainerPerson = $userObject->attribute( 'name' );

    			$maintainers = $package->attribute( 'maintainers' );
    			foreach ( $maintainers as $maintainer )
    			{
    				if ( $maintainer['person'] == $maintainerPerson )
    				{
       					return false;
    				}
    			}
    		}
    		return true;
	    }
	    return false;
	}

    /*!
     Initializes the package thumbnail step.
    */
    function initializePackageThumbnail( &$package, &$http, $step, &$persistentData, &$tpl )
    {
        $persistentData['thumbnail'] = false;
    }

    /*!
     Checks if the POST variables has a proper thumbnail image.
    */
    function validatePackageThumbnail( &$package, &$http, $currentStepID, &$stepMap, &$persistentData, &$errorList )
    {
        include_once( 'lib/ezutils/classes/ezhttpfile.php' );
        $file =& eZHTTPFile::fetch( 'PackageThumbnail' );

        $result = true;
        if ( $file )
        {
            include_once( 'lib/ezutils/classes/ezmimetype.php' );
            $mimeData = eZMimeType::findByFileContents( $file->attribute( 'original_filename' ) );
            $dir = eZSys::storageDirectory() . '/temp';
            eZMimeType::changeDirectoryPath( $mimeData, $dir );
            $file->store( false, false, $mimeData );
            $persistentData['thumbnail'] = $mimeData;
        }
        return $result;
    }

    /*!
     Commits thumbnail step data. Does nothing for now.
    */
    function commitPackageThumbnail( &$package, &$http, $step, &$persistentData, &$tpl )
    {
    }

    /*!
     \static
     Appends the GPL licence file to the package object \a $package.
    */
    function appendLicence( &$package )
    {
        $package->appendDocument( 'LICENCE', false, false, false, true,
                                  "This file is part of the package " . $package->attribute( 'name' ) . ".\n" .
                                  "\n" .
                                  "This package is free software; you can redistribute it and/or modify\n" .
                                  "it under the terms of the GNU General Public License as published by\n" .
                                  "the Free Software Foundation; either version 2 of the License, or\n" .
                                  "(at your option) any later version.\n" .
                                  "\n" .
                                  "This package is distributed in the hope that it will be useful,\n" .
                                  "but WITHOUT ANY WARRANTY; without even the implied warranty of\n" .
                                  "MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the\n" .
                                  "GNU General Public License for more details.\n" .
                                  "\n" .
                                  "You should have received a copy of the GNU General Public License\n" .
                                  "along with this package; if not, write to the Free Software\n" .
                                  "Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA\n" );
    }
}

?>
