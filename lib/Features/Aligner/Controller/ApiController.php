<?php
/**
 * Created by PhpStorm.
 * User: matteo
 * Date: 11/09/18
 * Time: 14:33
 */

namespace Features\Aligner\Controller;


use Features\Aligner\Model\NewDatabase;
use Features\Aligner\Model\Segments_SegmentDao;
use Features\Aligner\Model\Segments_SegmentMatchDao;
use Features\Aligner\Utils\AlignUtils;

class ApiController extends AlignerController {

    public function merge(){

        //TODO UPDATE WITH SEGMENTS FROM AND TO
        $data = array();

        $segments = array();
        $segment_orders = array();
        $type = $data[0]['type'];
        $job_id = $data[0]['job'];
        foreach ($data as $item){
            $segments[] = Segments_SegmentDao::getFromOrderJobIdAndType($item['order'], $item['job'], $item['type']);
            $segment_orders[] = $item['order'];
        }

        sort($segment_orders);
        $order1 = $segment_orders[0];
        $order2 = array_slice($segment_orders,-1);

        $raw_merge = "";
        $clean_merge = "";
        $merge_count = 0;

        $segment_ids = array();
        foreach ($segments as $segment) {
            $raw_merge .= " ".$segment['content_raw'];
            $clean_merge .= " ".$segment['content_clean'];
            $merge_count += $segment['raw_word_count'];
            $segment_ids[] = $segment['id'];
        }

        sort($segment_ids);
        $id1 = $segment_ids;

        $hash_merge = md5($raw_merge);

        $segmentQuery = "UPDATE segments
                    SET content_raw = ?,
                    content_clean = ?,
                    content_hash = ?,
                    raw_word_count = ?
                    WHERE id = ?;";
        $segmentParams = array($raw_merge, $clean_merge, $hash_merge, $merge_count, $id1);

        $matchQuery = "UPDATE segments_match as sm1,
                    segments_match as sm2
                    SET sm1.next = sm2.next
                    WHERE (sm1.order = ? AND sm1.id_job = ? AND sm1.type = ?) AND 
                    (sm2.order = ? AND sm2.id_job = ? AND sm2.type = ?)";
        $matchParams = array($order1, $job_id, $type, $order2, $job_id, $type);

        $qMarks = str_repeat('?,', count($segments) - 2) . '?';

        $deleted_ids = array_slice($segment_ids, 1, count($segments)-1);
        $deleted_orders = array_slice($segment_ids, 1, count($segments)-1);

        $deleteQueries = "DELETE FROM segments WHERE id IN ($qMarks);
        DELETE FROM segment_match WHERE order IN ($qMarks) AND id_job = ? AND type = ? ;";

        //TODO avoid using this method, make more connections and put each operation in transactions.
        $query = $segmentQuery . $matchQuery . $deleteQueries;
        $query_params = array_merge( $segmentParams, $matchParams, $deleted_ids, $deleted_orders, array($job_id, $type) );

        try {
            $conn = NewDatabase::obtain()->getConnection();
            $stm = $conn->prepare( $query );
            $stm->execute( $query_params );
        } catch ( \PDOException $e ) {
            throw new \Exception( "Segment update - DB Error: " . $e->getMessage() . " - $query_params", -2 );
        }
    }

    public function split(){

        $order = 0;
        $job = "";
        $type = "";
        $position = 0;

        $split_segment = Segments_SegmentDao::getFromOrderJobIdAndType($order, $job, $type);
        $split_match = Segments_SegmentMatchDao::getSegmentMatch($order, $job, $type);

        $avg_order = ($split_match['order'] + $split_match['next'])/2;
        $first_raw = substr($split_segment['content_raw'],0, $position);
        $second_raw = substr($split_segment['content_raw'], $position);
        $first_hash = md5($first_raw);
        $second_hash = md5($second_raw);

        $first_clean = AlignUtils::_cleanSegment($first_raw, $split_segment['language_code']);
        $second_clean = AlignUtils::_cleanSegment($second_raw, $split_segment['language_code']);
        $first_count = AlignUtils::_countWordsInSegment($first_raw, $split_segment['language_code']);
        $second_count = AlignUtils::_countWordsInSegment($second_raw, $split_segment['language_code']);

        $new_segment = $split_segment;
        $new_match = $split_match;

        $firstSegmentQuery = "UPDATE segments
        SET content_raw = ?,
        content_clean = ?,
        content_hash = ?,
        raw_word_count = ?
        WHERE id = ?";
        $firstSegmentParams = array($first_raw, $first_clean, $first_hash, $first_count, $split_segment['id']);

        $firstMatchQuery = "UPDATE segments_match
        SET next = ?
        WHERE order = ? AND job = ? AND type = ?";
        $firstMatchParams = array($avg_order, $order, $job, $type);

        try {
            $conn = NewDatabase::obtain()->getConnection();
            $stm = $conn->prepare( $firstSegmentQuery );
            $stm->execute( $firstSegmentParams );
            $stm = $conn->prepare( $firstMatchQuery );
            $stm->execute( $firstMatchParams );
        } catch ( \PDOException $e ) {
            throw new \Exception( "Segment update - DB Error: " . $e->getMessage() . " - $firstSegmentParams - $firstMatchParams", -2 );
        }

        //New segment creation

        $new_id = $this->dbHandler->nextSequence( NewDatabase::SEQ_ID_SEGMENT, 1);

        $new_segment['id'] = $new_id;
        $new_segment['content_raw'] = $second_raw;
        $new_segment['content_clean'] = $second_clean;
        $new_segment['content_hash'] = $second_hash;
        $new_segment['raw_word_count'] = $second_count;

        $segmentsDao = new Segments_SegmentDao;
        $segmentsDao->createList( array($new_segment) );

        // New segment_match creation

        $new_match['segment_id'] = $new_id;
        $new_match['order'] = $avg_order;

    }

    public function move(){
        
    }

}