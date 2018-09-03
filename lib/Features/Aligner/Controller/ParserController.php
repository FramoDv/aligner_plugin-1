<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 02/08/2018
 * Time: 13:33
 */


namespace Features\Aligner\Controller;
include_once \INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

use CatUtils;
use Exception;
use Features\Aligner\Model\Files_FileDao;
use Features\Aligner\Model\Jobs_JobDao;
use Features\Aligner\Model\Segments_SegmentMatchDao;
use Features\Aligner\Utils\Alignment;
use Features\Aligner\Model\NewDatabase;
use Features\Aligner\Model\Segments_SegmentDao;


class ParserController extends AlignerController {

    protected $id_job;


    /**
     * @throws Exception
     */
    public function jobParser() {
        ini_set('max_execution_time', 200);
        $this->id_job = $this->params['id_job'];
        $job = Jobs_JobDao::getById($this->id_job)[0];

        $segmentsMatchDao = new Segments_SegmentMatchDao;
        $segmentsMatchDao->deleteByJobId($this->id_job);

        $source_lang = $job->source;
        $target_lang = $job->target;

        $source_segments = Segments_SegmentDao::getDataForAlignment($this->id_job, "source");
        $target_segments = Segments_SegmentDao::getDataForAlignment($this->id_job, "target");

        $version = $this->params['version'];

        $alignment_class = new Alignment;

        switch ($version) {
            case 'v0':
                $alignment = $alignment_class->_alignSegmentsV0($source_segments, $target_segments);
                break;
            case 'v1':
                $alignment = $alignment_class->_alignSegmentsV1($source_segments, $target_segments);
                break;
            case 'v2':
                $alignment = $alignment_class->_alignSegmentsV2($source_segments, $target_segments, $source_lang, $target_lang);
                break;
            case 'v3':
                $alignment = $alignment_class->_alignSegmentsV3($source_segments, $target_segments, $source_lang, $target_lang);
                break;
            case 'v3b':
                $alignment = $alignment_class->_alignSegmentsV3B($source_segments, $target_segments, $source_lang, $target_lang);
                break;
            case 'v3c':
                $alignment = $alignment_class->_alignSegmentsV3C($source_segments, $target_segments, $source_lang, $target_lang);
                break;
        }

        // DEBUG //
//        $this->response->json( ['res' => $alignment] );

        $source_array = [];
        $target_array = [];
        foreach($alignment as $key => $value){
            $source_element = [];
            $source_element['segment_id'] = $value['source']['id'];
            $source_element['order'] = ($key+1)*1000000000;
            $source_element['next'] = ($key+2)*1000000000;
            $source_element['id_job'] = $this->id_job;
            $source_element['score'] = 0;
            $source_element['type'] = "source";

            $target_element = []; ;
            $target_element['segment_id'] = $value['target']['id'];
            $target_element['order'] = ($key+1)*1000000000;
            $target_element['next'] = ($key+2)*1000000000;
            $target_element['score'] = 0;
            $target_element['id_job'] = $this->id_job;
            $target_element['type'] = "target";

            $source_array[] = $source_element;
            $target_array[] = $target_element;
        }

        $source_array[count($source_array)-1]['next'] = null;
        $target_array[count($target_array)-1]['next'] = null;


        $segmentsMatchDao->createList($source_array);
        $segmentsMatchDao->createList($target_array);



        $this->response->json( ['source' => $source_array, 'target' => $target_array] );
    }

}
