<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 31/08/2018
 * Time: 11:20
 */

namespace Features\Aligner\Controller;

use Features\Aligner\Model\Files_FileDao;
use Features\Aligner\Model\Jobs_JobDao;
use Features\Aligner\Model\Segments_SegmentDao;
use Features\Aligner\Model\Segments_SegmentMatchDao;
use Features\Aligner\Utils\Constants;

class JobController extends AlignerController {

    public function information() {

        $id_job  = $this->params[ 'id_job' ];
        $job     = Jobs_JobDao::getById( $id_job )[ 0 ];
        $project = $job->getProject();

        $segmentDao            = new Segments_SegmentDao;
        $count_source_segments = $segmentDao->countByJobId( $id_job, Constants::JOBTYPESOURCE );
        $count_source_segments = ( !empty($count_source_segments) ) ? $count_source_segments[0]['source_segments'] : 0;
        $count_target_segments = $segmentDao->countByJobId( $id_job, Constants::JOBTYPETARGET );
        $count_target_segments = ( !empty($count_target_segments) ) ? $count_target_segments[0]['target_segments'] : 0;

        $source_file = Files_FileDao::getByJobId($id_job, Constants::JOBTYPESOURCE);
        $target_file = Files_FileDao::getByJobId($id_job, Constants::JOBTYPETARGET);

        $segmentMatchDao = new Segments_SegmentMatchDao;
        $miss_alignments = $segmentMatchDao->missAlignments($id_job);

        $information = [
                'job_name'              => $project->name,
                'source_lang'           => $job->source,
                'target_lang'           => $job->target,
                'total_source_segments' => $count_source_segments,
                'total_target_segments' => $count_target_segments,
                'miss_alignments' => $miss_alignments,
                'source_filename' => $source_file->filename,
                'target_filename' => $target_file->filename
        ];


        return $this->response->json( $information );

    }
}