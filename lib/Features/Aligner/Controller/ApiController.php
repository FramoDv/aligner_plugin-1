<?php
/**
 * Created by PhpStorm.
 * User: matteo
 * Date: 11/09/18
 * Time: 14:33
 */

namespace Features\Aligner\Controller;


use Exceptions\ValidationError;
use Features\Aligner\Model\Jobs_JobDao;
use Features\Aligner\Model\NewDatabase;
use Features\Aligner\Model\Segments_SegmentDao;
use Features\Aligner\Model\Segments_SegmentMatchDao;
use Features\Aligner\Model\Segments_SegmentMatchStruct;
use Features\Aligner\Model\Segments_SegmentStruct;
use Features\Aligner\Utils\AlignUtils;
use Features\Aligner\Utils\Constants;

class ApiController extends AlignerController {

    protected $operations;

    public function checkProgress() {

        $id_job = $this->params[ 'id_job' ];
        $job    = Jobs_JobDao::getById( $id_job );

        $status_analysis = ( !empty($job) ) ? $job[0]['status_analysis'] : 'not_started';

        $segmentDao = new Segments_SegmentDao();

        $source_segments = null;
        $target_segments = null;

        switch ( $status_analysis ){
            case 'not_started':
                $phase = 0;
                break;
            case 'started':
                $phase = 1;
                break;
            case 'segments_created':
                $phase = 2;
                break;
            case 'fetching':
                $phase = 3;
                break;
            case 'translating':
                $phase = 4;
                break;
            case 'aligning':
                $phase = 5;
                break;
            case 'merging':
                $phase = 6;
                break;
            case 'complete':
                $phase = 7;
                break;
        }

        switch ( $status_analysis ) {
            case 'segments_created':
            case 'fetching':
            case 'translating':
            case 'aligning':
            case 'merging':
            case 'complete':
                $source_segments = $segmentDao->countByJobId($id_job, Constants::JOBTYPESOURCE);
                $source_segments = ( !empty( $source_segments ) ) ? $source_segments[0]['source_segments'] : null;
                $target_segments = $segmentDao->countByJobId($id_job, Constants::JOBTYPETARGET);
                $target_segments = ( !empty( $target_segments ) ) ? $target_segments[0]['target_segments'] : null;
                break;
        }


        return $this->response->json( [ 'phase' => $phase,
            'phase_name' => $status_analysis,
            'source_segments' => $source_segments,
            'target_segments' => $target_segments ]
        );

    }

    public function merge() {

        $segments = [];
        $orders   = $this->params[ 'order' ];
        $type     = $this->params[ 'type' ];
        $id_job   = $this->params[ 'id_job' ];

        sort( $orders );

        foreach ($orders as $order) {
            $segment = Segments_SegmentDao::getFromOrderJobIdAndType($order, $id_job, $type);
            if(!is_object($segment)){
                throw new ValidationError("There's no segment with the parameters specified in the input");
            }
            $segments[] =  $segment->toArray();
        }

        $deleted_orders = [];

        foreach ( $segments as $key => $segment ) {
            if ( $key != 0 ) {
                $deleted_orders[] = $segment[ 'order' ];
            }
        }

        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();
            $first_segment = Segments_SegmentDao::mergeSegments( $segments );
            Segments_SegmentMatchDao::nullifySegmentsInMatches( $deleted_orders, $id_job, $type );
            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment update - DB Error: " . $e->getMessage() . " - Merging $orders", -2 );
        }

        try{

            $this->pushOperation( [
                'type'      => $type,
                'action'    => 'update',
                'rif_order' => $first_segment[ 'order' ],
                'data'      => $first_segment
            ] );

            foreach ( $deleted_orders as $order ) {
                $this->pushOperation( [
                    'type'      => $type,
                    'action'    => 'delete',
                    'rif_order' => $order
                ] );
            }

        } catch ( ValidationError $e ) {
            throw new ValidationError( $e->getMessage(), -2 );
        }

        return $this->getOperations();

    }


    public function split() {

        $order         = $this->params[ 'order' ];
        $id_job        = $this->params[ 'id_job' ];
        $type          = $this->params[ 'type' ];
        $inverse_order = $this->params[ 'inverse_order' ];
        $inverse_type  = ( $type == Constants::JOBTYPETARGET ) ? Constants::JOBTYPESOURCE : Constants::JOBTYPETARGET;
        $positions     = $this->params[ 'positions' ];

        if ( empty( $positions ) ) {
            return true;
        }

        //Gets from 0 since they are returned as an array
        $split_segment   = Segments_SegmentDao::getFromOrderJobIdAndType( $order, $id_job, $type );
        if(!is_object($split_segment)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $split_segment   = $split_segment->toArray();

        $inverse_segment = Segments_SegmentDao::getFromOrderJobIdAndType( $inverse_order, $id_job, $inverse_type );
        if(!is_object($inverse_segment)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $inverse_segment = $inverse_segment->toArray();

        $avg_order   = AlignUtils::_getNewOrderValue( $split_segment[ 'order' ], $split_segment[ 'next' ] );
        $inverse_avg = AlignUtils::_getNewOrderValue( $inverse_segment[ 'order' ], $inverse_segment[ 'next' ] );
      
        $original_next         = $split_segment[ 'next' ];
        $original_inverse_next = $inverse_segment[ 'next' ];

        $split_segment[ 'next' ]    = $avg_order;
        $inverse_segment[ 'next' ]  = $inverse_avg;

        $raw_contents = [];
        $full_raw     = AlignUtils::_mark_xliff_tags( $split_segment[ 'content_raw' ] );
        $positions[]  = mb_strlen( $full_raw, 'UTF-8' );
        foreach ( $positions as $key => $position ) {
            $start          = ( $key == 0 ) ? 0 : $positions[ $key - 1 ] + 1;
            $raw_substring  = mb_substr( $full_raw, $start, ( $position + 1 ) - $start, 'UTF-8' );
            $raw_contents[] = AlignUtils::_restore_xliff_tags( $raw_substring );
        }

        $first_raw   = array_shift( $raw_contents );
        $first_hash  = md5( $first_raw );
        $first_clean = AlignUtils::_cleanSegment( $first_raw, $split_segment[ 'language_code' ] );
        $first_count = AlignUtils::_countWordsInSegment( $first_raw, $split_segment[ 'language_code' ] );

        $new_segment = $split_segment;
        $new_match   = $split_segment;
        $null_match  = $inverse_segment;

        $split_segment[ 'content_raw' ]    = $first_raw;
        $split_segment[ 'content_clean' ]  = $first_clean;
        $split_segment[ 'content_hash' ]   = $first_hash;
        $split_segment[ 'raw_word_count' ] = $first_count;

        $update_order         = $avg_order;
        $inverse_update_order = $inverse_avg;

        $new_ids          = $this->dbHandler->nextSequence( NewDatabase::SEQ_ID_SEGMENT, count( $raw_contents ) );
        $new_segments     = [];
        $null_segments    = [];
        $new_matches      = [];
        $new_null_matches = [];

        $null_segment                     = $inverse_segment;
        $null_segment[ 'id' ]             = null;
        $null_segment[ 'content_raw' ]    = null;
        $null_segment[ 'content_clean' ]  = null;
        $null_segment[ 'content_hash' ]   = null;
        $null_segment[ 'raw_word_count' ] = null;

        foreach ( $new_ids as $key => $id ) {

            //create new segments
            $new_segment[ 'id' ]             = $id;
            $new_segment[ 'content_raw' ]    = array_shift( $raw_contents );
            $new_segment[ 'content_clean' ]  = AlignUtils::_cleanSegment( $new_segment[ 'content_raw' ], $new_segment[ 'language_code' ] );
            $new_segment[ 'content_hash' ]   = md5( $new_segment[ 'content_raw' ] );
            $new_segment[ 'raw_word_count' ] = AlignUtils::_countWordsInSegment( $new_segment[ 'content_raw' ], $new_segment[ 'language_code' ] );

            //create new matches
            $new_match[ 'segment_id' ] = $id;
            $new_match[ 'order' ]      = $avg_order;
            $new_segment[ 'order' ]    = $new_match[ 'order' ];

            //If we split the last segment we add new next values for the new segments
            $avg_order = ( $split_segment[ 'next' ] != null ) ? AlignUtils::_getNewOrderValue( $new_match[ 'order' ], $original_next ) : $avg_order + Constants::DISTANCE_INT_BETWEEN_MATCHES;

            $new_match[ 'next' ]   = ( $key != count( $new_ids ) - 1 ) ? $avg_order : $original_next;
            $new_segment[ 'next' ] = $new_match[ 'next' ];
            $new_matches[]         = $new_match;

            //create new null matches
            $null_match[ 'segment_id' ] = null;
            $null_match[ 'order' ]      = $inverse_avg;
            $null_segment[ 'order' ]    = $inverse_avg;

            //If we split the last segment we add new next values for the new segments
            $inverse_avg = ( $inverse_segment[ 'next' ] != null ) ? AlignUtils::_getNewOrderValue( $null_match[ 'order' ], $original_inverse_next ) : $inverse_avg + Constants::DISTANCE_INT_BETWEEN_MATCHES;

            $null_match[ 'next' ]   = ( $key != count( $new_ids ) - 1 ) ? $inverse_avg : $original_inverse_next;
            $null_segment[ 'next' ] = $null_match[ 'next' ];
            $new_null_matches[]     = $null_match;

            $new_segments[]  = $new_segment;
            $null_segments[] = $null_segment;
        }


        //New segment creation
        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();

            $segmentsDao = new Segments_SegmentDao;
            $segmentsDao->createList( $new_segments );

            $segmentsMatchDao = new Segments_SegmentMatchDao;
            $segmentsMatchDao->createList( array_merge( $new_matches, $new_null_matches ) );

            Segments_SegmentDao::updateSegmentContent( $split_segment [ 'id' ], [ $first_raw, $first_clean, $first_hash, $first_count ] );
            Segments_SegmentMatchDao::updateNextSegmentMatch( $update_order, $order, $id_job, $type );
            Segments_SegmentMatchDao::updateNextSegmentMatch( $inverse_update_order, $inverse_order, $id_job, $inverse_type );

            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment update - DB Error: " . $e->getMessage(), -2 );
        }

        //Check which segments to retrieve for source/target
        $source_start = ( $type == Constants::JOBTYPESOURCE ) ? $split_segment[ 'order' ] : $inverse_segment[ 'order' ];
        $source_end   = ( $type == Constants::JOBTYPESOURCE ) ? $original_next : $original_inverse_next;
        $target_start = ( $type == Constants::JOBTYPETARGET ) ? $split_segment[ 'order' ] : $inverse_segment[ 'order' ];
        $target_end   = ( $type == Constants::JOBTYPETARGET ) ? $original_next : $original_inverse_next;

        $segments       = array_merge( [ $split_segment ], $new_segments );
        $sourceSegments = ( $type == Constants::JOBTYPESOURCE ) ? $segments : array_merge( [ $inverse_segment ], $null_segments );
        $targetSegments = ( $type == Constants::JOBTYPETARGET ) ? $segments : array_merge( [ $inverse_segment ], $null_segments );
        
        try{

            $this->pushOperation( [
                'type'      => Constants::JOBTYPESOURCE,
                'action'    => 'update',
                'rif_order' => $source_start,
                'data'      => array_shift( $sourceSegments )
            ] );

            foreach ( $sourceSegments as $sourceSegment ) {

                $this->pushOperation( [
                    'type'      => Constants::JOBTYPESOURCE,
                    'action'    => 'create',
                    'rif_order' => $source_end,
                    'data'      => $sourceSegment
                ] );

            }

            $this->pushOperation( [
                'type'      => Constants::JOBTYPETARGET,
                'action'    => 'update',
                'rif_order' => $target_start,
                'data'      => array_shift( $targetSegments )
            ] );

            foreach ( $targetSegments as $targetSegment ) {

                $this->pushOperation( [
                    'type'      => Constants::JOBTYPETARGET,
                    'action'    => 'create',
                    'rif_order' => $target_end,
                    'data'      => $targetSegment
                ] );

            }

        } catch ( ValidationError $e ) {
            throw new ValidationError( $e->getMessage(), -2 );
        }

        return $this->getOperations();
    }

    public function moveInEmpty($referenceMatch){


        $order                     = $this->params[ 'order' ];
        $id_job                    = $this->params[ 'id_job' ];
        $type                      = $this->params[ 'type' ];
        $destination_order         = $this->params[ 'destination' ];

        if($order == $destination_order){ return $this->getOperations();}

        $movingSegment = Segments_SegmentDao::getFromOrderJobIdAndType( $order, $id_job, $type );
        if(!is_object($movingSegment)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $movingSegment = $movingSegment->toArray();

        $new_match_order                       = AlignUtils::_getNewOrderValue( $destination_order, $referenceMatch[ 'next' ] );
        $destination_match                     = $referenceMatch;
        $destination_match[ 'segment_id' ]     = $movingSegment[ 'id' ];
        $destination_match[ 'content_raw' ]    = $movingSegment[ 'content_raw' ];
        $destination_match[ 'content_clean' ]  = $movingSegment[ 'content_clean' ];
        $destination_match[ 'raw_word_count' ] = $movingSegment[ 'raw_word_count' ];
        $destination_match[ 'next' ]           = $new_match_order;

        $this->pushOperation( [
                'type'      => $type,
                'action'    => 'update',
                'rif_order' => $destination_order,
                'data'      => $destination_match
        ] );

        $starting_match                     = $movingSegment;
        $starting_match[ 'segment_id' ]     = null;
        $starting_match[ 'content_raw' ]    = null;
        $starting_match[ 'content_clean' ]  = null;
        $starting_match[ 'raw_word_count' ] = null;

        $this->pushOperation( [
                'type'      => $type,
                'action'    => 'update',
                'rif_order' => $order,
                'data'      => $starting_match
        ] );

        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();
            Segments_SegmentMatchDao::nullifySegmentsInMatches( [ $order ], $id_job, $type );
            Segments_SegmentMatchDao::updateFields( [ 'segment_id' => $movingSegment[ 'id' ] ], $destination_order, $id_job, $type );
            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment Move - DB Error: " . $e->getMessage(), -2 );
        }

        return $this->getOperations();


    }

    public function moveInFill($referenceMatch){

        $order               = $this->params[ 'order' ];
        $id_job              = $this->params[ 'id_job' ];
        $type                = $this->params[ 'type' ];
        $inverse_type        = ( $type == Constants::JOBTYPETARGET ) ? Constants::JOBTYPESOURCE : Constants::JOBTYPETARGET;
        $destination_order         = $this->params[ 'destination' ];
        $inverse_destination_order = $this->params[ 'inverse_destination' ];

        if($order == $destination_order){ return $this->getOperations();}

        $movingSegment = Segments_SegmentDao::getFromOrderJobIdAndType( $order, $id_job, $type );
        if(!is_object($movingSegment)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $movingSegment = $movingSegment->toArray();

        $new_match_order = AlignUtils::_getNewOrderValue( $destination_order, $referenceMatch['next'] );
        $destination_match = $referenceMatch;
        $destination_match['segment_id'] = $movingSegment['id'];
        $destination_match['content_raw'] = $movingSegment['content_raw'];
        $destination_match['content_clean'] = $movingSegment['content_clean'];
        $destination_match['raw_word_count'] = $movingSegment['raw_word_count'];
        $destination_match['next'] = $new_match_order;

        $this->pushOperation( [
                'type'      => $type,
                'action'    => 'update',
                'rif_order' => $destination_order,
                'data'      => $destination_match
        ] );

        $starting_match = $movingSegment;
        $starting_match['segment_id'] = null;
        $starting_match['content_raw'] = null;
        $starting_match['content_clean'] = null;
        $starting_match['raw_word_count'] = null;

        $this->pushOperation( [
                'type'      => $type,
                'action'    => 'update',
                'rif_order' => $order,
                'data'      => $starting_match
        ] );

        $new_match_destination = [];
        $new_match_destination[ 'order' ]          = $new_match_order;
        $new_match_destination[ 'next' ]           = $referenceMatch['next'];
        $new_match_destination[ 'score' ]          = 100;
        $new_match_destination[ 'segment_id' ]     = $referenceMatch[ 'id' ];
        $new_match_destination[ 'type' ]           = $type;
        $new_match_destination[ 'id_job' ]         = $id_job;
        $new_match_destination[ 'content_raw' ]    = $referenceMatch['content_raw'];
        $new_match_destination[ 'content_clean' ]  = $referenceMatch['content_clean'];
        $new_match_destination[ 'raw_word_count' ] = $referenceMatch['raw_word_count'];

        $this->pushOperation( [
                'type'      => $type,
                'action'    => 'create',
                'rif_order' => $destination_order,
                'data'      => $new_match_destination
        ] );

        $inverseReference = Segments_SegmentMatchDao::getSegmentMatch( $inverse_destination_order, $id_job, $inverse_type );
        if(!is_object($inverseReference)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $inverseReference = $inverseReference->toArray();

        $new_match_null = [];
        $new_inverse_order           = AlignUtils::_getNewOrderValue( $inverseReference[ 'order' ], $inverseReference[ 'next' ] );
        $new_match_null[ 'order' ]          = $new_inverse_order;
        $new_match_null[ 'next' ]           = $inverseReference[ 'next' ];
        $new_match_null[ 'score' ]          = 100;
        $new_match_null[ 'segment_id' ]     = null;
        $new_match_null[ 'type' ]           = $inverse_type;
        $new_match_null[ 'id_job' ]         = $id_job;
        $new_match_null[ 'content_raw' ]    = null;
        $new_match_null[ 'content_clean' ]  = null;
        $new_match_null[ 'raw_word_count' ] = null;

        $this->pushOperation( [
                'type'      => $inverse_type,
                'action'    => 'create',
                'rif_order' => $inverseReference[ 'order' ],
                'data'      => $new_match_null
        ] );

        $inverseReference['next'] = $new_inverse_order;
        $this->pushOperation( [
                'type'      => $inverse_type,
                'action'    => 'update',
                'rif_order' => $inverseReference['order'],
                'data'      => $inverseReference
        ] );

        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();
            $segmentsMatchDao = new Segments_SegmentMatchDao;
            $segmentsMatchDao->createList( [ $new_match_destination, $new_match_null ] );
            Segments_SegmentMatchDao::nullifySegmentsInMatches( [ $order ], $id_job, $type );
            Segments_SegmentMatchDao::updateFields( ['segment_id' => $movingSegment['id'], 'next' => $new_match_order], $destination_order, $id_job, $type );
            Segments_SegmentMatchDao::updateFields( ['next' => $new_inverse_order], $inverse_destination_order, $id_job, $inverse_type );
            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment Move - DB Error: " . $e->getMessage(), -2 );
        }

        return $this->getOperations();

    }

    public function move() {

        $id_job              = $this->params[ 'id_job' ];
        $type                = $this->params[ 'type' ];
        $destination_order   = $this->params[ 'destination' ];

        $referenceMatch = Segments_SegmentDao::getFromOrderJobIdAndType( $destination_order, $id_job, $type );
        if(!is_object($referenceMatch)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $referenceMatch = $referenceMatch->toArray();

        if(!empty($referenceMatch['id'])){
            return $this->moveInFill($referenceMatch);
        }
        else {
            return $this->moveInEmpty($referenceMatch);
        }

    }


    public function addGap() {


        $order      = $this->params[ 'order' ];
        $id_job     = $this->params[ 'id_job' ];
        $type       = $this->params[ 'type' ];
        $other_type = ( $type == Constants::JOBTYPETARGET ) ? Constants::JOBTYPESOURCE : Constants::JOBTYPETARGET;

        $gap_match     = [];
        $balance_match = [];

        $previous_match = Segments_SegmentMatchDao::getPreviousSegmentMatch( $order, $id_job, $type );
        if(!is_object($previous_match)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $previous_match = $previous_match->toArray();
        $previous_order = ( empty( $previous_match ) ) ? 0 : $previous_match[ 'order' ];


        try{

            $gap_match[ 'order' ]          = AlignUtils::_getNewOrderValue( $previous_order, $order );
            $gap_match[ 'next' ]           = $order;
            $gap_match[ 'segment_id' ]     = null;
            $gap_match[ 'score' ]          = 100;
            $gap_match[ 'id_job' ]         = $id_job;
            $gap_match[ 'type' ]           = $type;
            $gap_match[ 'content_raw' ]    = null;
            $gap_match[ 'content_clean' ]  = null;
            $gap_match[ 'raw_word_count' ] = null;

            $this->pushOperation( [
                'type'      => $type,
                'action'    => 'create',
                'rif_order' => $order,
                'data'      => $gap_match
            ] );

            $last_match = Segments_SegmentMatchDao::getLastSegmentMatch( $id_job, $other_type )->toArray();

            $last_match[ 'order' ]             = $last_match[ 'order' ];
            $balance_match[ 'order' ]          = $last_match[ 'order' ] + Constants::DISTANCE_INT_BETWEEN_MATCHES;
            $balance_match[ 'next' ]           = null;
            $balance_match[ 'segment_id' ]     = null;
            $balance_match[ 'score' ]          = 100;
            $balance_match[ 'id_job' ]         = $id_job;
            $balance_match[ 'type' ]           = $other_type;
            $balance_match[ 'content_raw' ]    = null;
            $balance_match[ 'content_clean' ]  = null;
            $balance_match[ 'raw_word_count' ] = null;

            $this->pushOperation( [
                'type'   => $other_type,
                'action' => 'push',
                'data'   => $balance_match
            ] );

            if ( !empty( $previous_match ) ) {

                $previous_match[ 'next' ] = $gap_match[ 'order' ];

                $previous_segment = Segments_SegmentDao::getFromOrderJobIdAndType( $previous_order, $id_job, $type )->toArray();


                $previous_segment[ 'next' ] = $gap_match[ 'order' ];

                $this->pushOperation( [
                    'type'      => $type,
                    'action'    => 'update',
                    'rif_order' => $previous_match[ 'order' ],
                    'data'      => $previous_segment
                ] );

            }

            $last_match[ 'next' ] = $balance_match[ 'order' ];

            $last_segment = Segments_SegmentDao::getFromOrderJobIdAndType( $last_match[ 'order' ], $id_job, $type );
            if ( !empty( $last_segment ) ) {
                $last_segment = $last_segment->toArray();
            }
            $last_segment[ 'type' ]           = $other_type;
            $last_segment[ 'content_raw' ]    = ( !empty( $last_segment[ 'content_raw' ] ) ) ? $last_segment[ 'content_raw' ] : null;
            $last_segment[ 'content_clean' ]  = ( !empty( $last_segment[ 'content_clean' ] ) ) ? $last_segment[ 'content_clean' ] : null;
            $last_segment[ 'raw_word_count' ] = ( !empty( $last_segment[ 'raw_word_count' ] ) ) ? $last_segment[ 'raw_word_count' ] : null;


            $this->pushOperation( [
                'type'      => $other_type,
                'action'    => 'update',
                'rif_order' => $last_match[ 'order' ],
                'data'      => $last_segment
            ] );

        } catch ( ValidationError $e ) {
            throw new ValidationError( $e->getMessage(), -2 );
        }

        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();

            $segmentsMatchDao = new Segments_SegmentMatchDao;
            $segmentsMatchDao->createList( [ $gap_match, $balance_match ] );

            if ( !empty( $previous_match ) ) {
                Segments_SegmentMatchDao::updateNextSegmentMatch( $gap_match[ 'order' ], $previous_order, $id_job, $type );
            }
            Segments_SegmentMatchDao::updateNextSegmentMatch( $balance_match[ 'order' ], $last_match[ 'order' ], $id_job, $other_type );
            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment update - DB Error: " . $e->getMessage() . " - Order no. $order ", -2 );
        }

        return $this->getOperations();
    }

    public function delete() {

        $matches = $this->params[ 'matches' ];
        $id_job  = $this->params[ 'id_job' ];

        $sources = [];
        $targets = [];

        foreach ( $matches as $match ) {
            if ( $match[ 'type' ] == Constants::JOBTYPETARGET ) {
                $targets[] = $match[ 'order' ];
            } else {
                $sources[] = $match[ 'order' ];
            }
        }

        if ( count( $targets ) != count( $sources ) ) {
            throw new ValidationError( "There is a different amount of source matches and target matches, Deletion cancelled", -2 );
        }

        $sourceMatches = Segments_SegmentMatchDao::getMatchesFromOrderArray( $sources, $id_job, Constants::JOBTYPESOURCE );
        $targetMatches = Segments_SegmentMatchDao::getMatchesFromOrderArray( $targets, $id_job, Constants::JOBTYPETARGET );

        foreach ( $sourceMatches as $sourceMatch ) {
            if ( $sourceMatch[ 'segment_id' ] != null ) {
                throw new ValidationError( "Segment Matches contain reference to existing segments, Deletion cancelled", -2 );
            }
        }

        foreach ( $targetMatches as $targetMatch ) {
            if ( $targetMatch[ 'segment_id' ] != null ) {
                throw new ValidationError( "Segment Matches contain reference to existing segments, Deletion cancelled", -2 );
            }
        }

        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();
            if ( !empty( $sources ) ) {
                Segments_SegmentMatchDao::updateMatchesBeforeDeletion( $sources, $id_job, Constants::JOBTYPESOURCE );
                Segments_SegmentMatchDao::deleteMatches( $sources, $id_job, Constants::JOBTYPESOURCE );
            }
            if ( !empty( $targets ) ) {
                Segments_SegmentMatchDao::updateMatchesBeforeDeletion( $targets, $id_job, Constants::JOBTYPETARGET );
                Segments_SegmentMatchDao::deleteMatches( $targets, $id_job, Constants::JOBTYPETARGET );
            }
            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment update - DB Error: " . $e->getMessage(), -2 );
        }

    }

    public function reverse() {

        $id_job = $this->params[ 'id_job' ];
        $type   = $this->params[ 'type' ];
        $order1 = $this->params[ 'order1' ];
        $order2 = $this->params[ 'order2' ];

        $segment_1 = Segments_SegmentDao::getFromOrderJobIdAndType( $order1, $id_job, $type );
        if(!is_object($segment_1)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $segment_1 = $segment_1->toArray();

        $segment_2 = Segments_SegmentDao::getFromOrderJobIdAndType( $order2, $id_job, $type );
        if(!is_object($segment_2)){
            throw new ValidationError("There's no segment with the parameters specified in the input");
        }
        $segment_2 = $segment_2->toArray();

        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();
            Segments_SegmentMatchDao::updateFields( [ 'segment_id' => $segment_2[ 'id' ], 'score' => 100 ], $order1, $id_job, $type );
            Segments_SegmentMatchDao::updateFields( [ 'segment_id' => $segment_1[ 'id' ], 'score' => 100 ], $order2, $id_job, $type );
            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment update - DB Error: " . $e->getMessage(), -2 );
        }

        $segment_1_copy = $segment_1;

        $segment_1[ 'order' ] = $segment_2[ 'order' ];
        $segment_1[ 'next' ]  = $segment_2[ 'next' ];
        $segment_1[ 'score' ] = 100;

        $segment_2[ 'order' ] = $segment_1_copy[ 'order' ];
        $segment_2[ 'next' ]  = $segment_1_copy[ 'next' ];
        $segment_2[ 'score' ] = 100;

        try{
            $this->pushOperation( [
                'type'      => $type,
                'action'    => "update",
                'rif_order' => $segment_2[ 'order' ],
                'data'      => $segment_2
            ] );

            $this->pushOperation( [
                'type'      => $type,
                'action'    => "update",
                'rif_order' => $segment_1[ 'order' ],
                'data'      => $segment_1
            ] );
        } catch ( ValidationError $e ) {
            throw new ValidationError( $e->getMessage(), -2 );
        }

        return $this->getOperations();

    }

    public function mergeAndAlign() {
        $matches           = $this->params[ 'matches' ];
        $id_job            = $this->params[ 'id_job' ];
        $destination_order = $this->params[ 'destination' ];

        $sources        = [];
        $targets        = [];
        $sourceOrders   = [];
        $targetOrders   = [];

        foreach ( $matches as $match ) {
            if ( $match[ 'type' ] == Constants::JOBTYPETARGET ){
                $targetOrders[] = $match['order'];
            } else {
                $sourceOrders[] = $match['order'];
            }
        }

        sort($sourceOrders);
        sort($targetOrders);

        foreach ( $sourceOrders as $order ) {
            $source = Segments_SegmentDao::getFromOrderJobIdAndType($order, $id_job, Constants::JOBTYPESOURCE);
            if(!is_object($source)){
                throw new ValidationError("There's no segment with the parameters specified in the input");
            }
            $sources[] = $source->toArray();
        }
        foreach ( $targetOrders as $order ) {
            $target = Segments_SegmentDao::getFromOrderJobIdAndType( $order, $id_job, Constants::JOBTYPETARGET );
            if(!is_object($target)){
                throw new ValidationError("There's no segment with the parameters specified in the input");
            }
            $targets[] = $target->toArray();
        }

        $first_source = $sources[0];
        $first_target = $targets[0];

        if($first_target['order'] != $destination_order){
            $referenceMatch = Segments_SegmentDao::getFromOrderJobIdAndType( $destination_order, $id_job, Constants::JOBTYPETARGET );
            if(!is_object($referenceMatch)){
                throw new ValidationError("There's no segment with the parameters specified in the input");
            }
            $referenceMatch = $referenceMatch->toArray();

            $new_match_order = AlignUtils::_getNewOrderValue( $destination_order, $referenceMatch['next'] );

            $starting_match = $first_target;
            $starting_match['segment_id'] = null;
            $starting_match['content_raw'] = null;
            $starting_match['content_clean'] = null;
            $starting_match['raw_word_count'] = null;

            $destination_match = $referenceMatch;
            $destination_match['segment_id'] = $first_target['id'];
            $destination_match['next'] = $new_match_order;

            $new_match_destination = [];
            $new_match_destination[ 'order' ]          = $new_match_order;
            $new_match_destination[ 'next' ]           = $referenceMatch['next'];
            $new_match_destination[ 'score' ]          = 100;
            $new_match_destination[ 'segment_id' ]     = $referenceMatch[ 'id' ];
            $new_match_destination[ 'type' ]           = Constants::JOBTYPETARGET;
            $new_match_destination[ 'id_job' ]         = $id_job;
            $new_match_destination[ 'content_raw' ]    = $referenceMatch['content_raw'];
            $new_match_destination[ 'content_clean' ]  = $referenceMatch['content_clean'];
            $new_match_destination[ 'raw_word_count' ] = $referenceMatch['raw_word_count'];

            $inverseReference  = Segments_SegmentMatchDao::getSegmentMatch( $first_source['order'], $id_job, Constants::JOBTYPESOURCE );
            if(!is_object($inverseReference)){
                throw new ValidationError("There's no segment with the parameters specified in the input");
            }
            $inverseReference  = $inverseReference->toArray();

            $new_match_null    = [];
            $new_inverse_order = AlignUtils::_getNewOrderValue( $inverseReference[ 'order' ], $inverseReference[ 'next' ] );

            $new_match_null[ 'order' ]          = $new_inverse_order;
            $new_match_null[ 'next' ]           = $inverseReference[ 'next' ];
            $new_match_null[ 'score' ]          = 100;
            $new_match_null[ 'segment_id' ]     = null;
            $new_match_null[ 'type' ]           = Constants::JOBTYPESOURCE;
            $new_match_null[ 'id_job' ]         = $id_job;
            $new_match_null[ 'content_raw' ]    = null;
            $new_match_null[ 'content_clean' ]  = null;
            $new_match_null[ 'raw_word_count' ] = null;

            $inverseReference['next'] = $new_inverse_order;
        }
        array_shift( $sourceOrders );
        array_shift( $targetOrders );
        $conn = NewDatabase::obtain()->getConnection();
        try {
            $conn->beginTransaction();
            $first_source_segment = ( count($sources) > 1 ) ? Segments_SegmentDao::mergeSegments( $sources ) : $first_source;
            $first_target_segment = ( count($targets) > 1 ) ? Segments_SegmentDao::mergeSegments( $targets ) : $first_target;
            if( !empty($sourceOrders) ){ Segments_SegmentMatchDao::nullifySegmentsInMatches( $sourceOrders, $id_job, Constants::JOBTYPESOURCE ); }
            if( !empty($targetOrders) ){ Segments_SegmentMatchDao::nullifySegmentsInMatches( $targetOrders, $id_job, Constants::JOBTYPETARGET ); }

            if($first_target['order'] != $destination_order ){
                $segmentsMatchDao = new Segments_SegmentMatchDao;
                $segmentsMatchDao->createList( [ $new_match_destination, $new_match_null ] );
                Segments_SegmentMatchDao::nullifySegmentsInMatches( [ $first_target['order'] ], $id_job, Constants::JOBTYPETARGET );
                Segments_SegmentMatchDao::updateFields( ['segment_id' => $first_target_segment['id'], 'next' => $new_match_order], $destination_order, $id_job, Constants::JOBTYPETARGET );
                Segments_SegmentMatchDao::updateFields( ['next' => $new_inverse_order], $first_source_segment['order'], $id_job, Constants::JOBTYPESOURCE );
            }

            $conn->commit();
        } catch ( \PDOException $e ) {
            $conn->rollBack();
            throw new \PDOException( "Segment update - DB Error: " . $e->getMessage() . " - Merge-align  ", -2 );
        }

        array_shift( $sources );
        array_shift( $targets );
        
        //TODO replace stuff in match
        $destination_match['content_raw']    = $first_target_segment['content_raw'];
        $destination_match['content_clean']  = $first_target_segment['content_clean'];
        $destination_match['raw_word_count'] = $first_target_segment['raw_word_count'];

        $inverseReference['content_raw']    = $first_source_segment['content_raw'];
        $inverseReference['content_clean']  = $first_source_segment['content_clean'];
        $inverseReference['raw_word_count'] = $first_source_segment['raw_word_count'];
        
        try{

            $this->pushOperation( [
                'type'      => Constants::JOBTYPESOURCE,
                'action'    => 'update',
                'rif_order' => $first_source_segment['order'],
                'data'      => $first_source_segment
            ] );

            foreach ( $sources as $source ) {
                $source['content_raw'] = null;
                $source['content_clean'] = null;
                $source['raw_word_count'] = null;
                $this->pushOperation( [
                    'type'      => Constants::JOBTYPESOURCE,
                    'action'    => 'update',
                    'rif_order' => $source['order'],
                    'data' => $source
                ] );
            }

            $this->pushOperation( [
                'type'      => Constants::JOBTYPETARGET,
                'action'    => 'update',
                'rif_order' => $first_target_segment['order'],
                'data'      => $first_target_segment
            ] );

            foreach ( $targets as $target ) {
                $target['content_raw'] = null;
                $target['content_clean'] = null;
                $target['raw_word_count'] = null;
                $this->pushOperation( [
                    'type'      => Constants::JOBTYPETARGET,
                    'action'    => 'update',
                    'rif_order' => $target['order'],
                    'data' => $target
                ] );
            }

            if($first_target['order'] != $destination_order){
                $this->pushOperation( [
                    'type'      => Constants::JOBTYPETARGET,
                    'action'    => 'update',
                    'rif_order' => $destination_order,
                    'data'      => $destination_match
                ] );

                $this->pushOperation( [
                    'type'      => Constants::JOBTYPETARGET,
                    'action'    => 'update',
                    'rif_order' => $first_target['order'],
                    'data'      => $starting_match
                ] );

                $this->pushOperation( [
                    'type'      => Constants::JOBTYPETARGET,
                    'action'    => 'create',
                    'rif_order' => $referenceMatch['next'],
                    'data'      => $new_match_destination
                ] );

                //TODO fix rif_order for front-end
                $this->pushOperation( [
                    'type'      => Constants::JOBTYPESOURCE,
                    'action'    => 'create',
                    'rif_order' => $first_source[ 'next' ],
                    'data'      => $new_match_null
                ] );

                $this->pushOperation( [
                    'type'      => Constants::JOBTYPESOURCE,
                    'action'    => 'update',
                    'rif_order' => $inverseReference['order'],
                    'data'      => $inverseReference
                ] );
            }


        } catch ( ValidationError $e ) {
            throw new ValidationError( $e->getMessage(), -2 );
        }
        return $this->getOperations();
    }
    
    private function pushOperation( $operation ) {
        $operation_fields = [ 'type', 'action', 'rif_order', 'data' ];
        foreach ( array_keys( $operation ) as $field ){
            if( !in_array( $field, $operation_fields ) ){
                throw new ValidationError( "Operation format is not valid" );
            }
        }
        AlignUtils::_parseArrayIntegers( $operation );
        if( !empty( $operation[ 'data' ] ) ){
            $segment_fields = AlignUtils::_getObjectVariables(new Segments_SegmentStruct());
            $match_fields   = AlignUtils::_getObjectVariables(new Segments_SegmentMatchStruct());

            $data_fields    = AlignUtils::_array_union($segment_fields, $match_fields);
            foreach ( array_keys( $operation[ 'data' ] ) as $field ){
                if( !in_array( $field, $data_fields ) ){
                    throw new ValidationError( "Operation data format is not valid" );
                }
            }
            $operation['data']['content_raw']   = AlignUtils::_mark_xliff_tags($operation['data']['content_raw']);
            $operation['data']['content_clean'] = htmlspecialchars_decode($operation['data']['content_clean']);
            AlignUtils::_parseArrayIntegers( $operation['data'] );
        }
        $this->operations[] = $operation;
    }

    private function getOperations() {
        return $this->response->json( $this->operations );
    }

}