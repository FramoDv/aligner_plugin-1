<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 25/06/18
 * Time: 12:43
 */

namespace Features;

use Features\Aligner\Utils\AlignUtils;
use Klein\Klein;
use BasicFeatureStruct;
use Features\Aligner\Controller\HomeController;

class Aligner extends BaseFeature
{
    const FEATURE_CODE = "aligner";

    protected $autoActivateOnProject = false;

    public function __construct( BasicFeatureStruct $feature ) {
        parent::__construct( $feature );
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/upload', 'POST', 'Features\Aligner\Controller\UploadController', 'upload' );
        route( '/upload/delete', 'DELETE', 'Features\Aligner\Controller\UploadController', 'delete' );
        route( '/xliff_conversion', 'POST', 'Features\Aligner\Controller\UploadController', 'convert' );
        route( '/create_project', 'POST', 'Features\Aligner\Controller\ProjectController', 'create' );

        route( '/job/[:id_job]/[:password]/check_progress', 'GET', 'Features\Aligner\Controller\JobController', 'checkProgress' );

        route( '/job/[:id_job]/[:password]/information', 'GET', 'Features\Aligner\Controller\JobController', 'information' );

        route( '/job/[:id_job]/[:password]/segments', 'GET', 'Features\Aligner\Controller\SegmentsController', 'get' );
        
        route( '/job/[:id_job]/[:password]/segment/split', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'split' );
        route( '/job/[:id_job]/[:password]/segment/merge', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'merge' );
        route( '/job/[:id_job]/[:password]/segment/move', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'move' );
        route( '/job/[:id_job]/[:password]/segment/delete', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'delete' );
        route( '/job/[:id_job]/[:password]/segment/switch', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'switchAction' );
        route( '/job/[:id_job]/[:password]/segment/merge_align', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'mergeAndAlign' );
        route( '/job/[:id_job]/[:password]/segment/hide', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'hide' );
        route( '/job/[:id_job]/[:password]/segment/show', 'POST', 'Features\Aligner\Controller\JobDirectActionController', 'show' );

        route( '/job/[:id_job]/[:password]/segment/undo/delete', 'POST', 'Features\Aligner\Controller\JobUndoActionController', 'undoDelete' );
        route( '/job/[:id_job]/[:password]/segment/undo/switch', 'POST', 'Features\Aligner\Controller\JobUndoActionController', 'undoSwitchAction' );
        route( '/job/[:id_job]/[:password]/segment/undo/split', 'POST', 'Features\Aligner\Controller\JobUndoActionController', 'undoSplit' );
        route( '/job/[:id_job]/[:password]/segment/undo/merge', 'POST', 'Features\Aligner\Controller\JobUndoActionController', 'undoMerge' );

        route( '/tm/mine', 'GET', 'Features\Aligner\Controller\TmController', 'getUserTM' );
        route( '/tm/create_key', 'POST', 'Features\Aligner\Controller\TmController', 'createTmKey' );
        route( '/tm/[:key]/save', 'POST', 'Features\Aligner\Controller\TmController', 'saveTm' );

        route( '/job/[:id_job]/[:password]/push_tmx', 'POST', 'Features\Aligner\Controller\JobTmxController', 'pushTMXInTM' );
        route( '/job/[:id_job]/[:password]/tm/[:key]/push_tmx', 'POST', 'Features\Aligner\Controller\JobTmxController', 'pushTMXInTM' );

        $klein->respond( 'GET', '/index', [ __CLASS__, 'homeRoute' ] );
        $klein->respond( 'GET', '/configs', [ __CLASS__, 'getConfigs' ] );
    }

    public static function homeRoute( $request, $response, $service, $app ) {
        $controller    = new HomeController( $request, $response, $service, $app );
        $template_path = dirname( __FILE__ ) . '/Aligner/View/Html/index.html';
        $controller->setView( $template_path );
        $controller->respond( 'composeView' );
    }

    public static function getConfigs($request, $response, $service, $app){

        $config = [];

        $oauth_client = \OauthClient::getInstance()->getClient();
        $config[ 'authURL' ] = $oauth_client->createAuthUrl();
        $config[ 'gdriveAuthURL' ] = \ConnectedServices\GDrive::generateGDriveAuthUrl();
        $message = AlignUtils::collectFlashMessages();
        $config['forgot_password'] = isset($message['service'][0]['value']) && ($message['service'][0]['value'] == 'passwordReset');
        return $response->json( $config );

    }


}