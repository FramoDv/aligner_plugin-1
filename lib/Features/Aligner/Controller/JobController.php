<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 31/08/2018
 * Time: 11:20
 */

namespace Features\Aligner\Controller;

use Exceptions\ValidationError;
use Features\Aligner\Controller\Validators\JobPasswordValidator;
use Features\Aligner\Model\Files_FileDao;
use Features\Aligner\Model\Segments_SegmentDao;
use Features\Aligner\Utils\ConstantsJobAnalysis;


class JobController extends AlignerController {

    protected $job;
    protected $project;

    public function afterConstruct() {
        $jobValidator = ( new JobPasswordValidator( $this ) );

        $jobValidator->onSuccess( function () use ( $jobValidator ) {
            $this->job     = $jobValidator->getJob();
            $this->project = $this->job->getProject();
        } );

        $this->appendValidator( $jobValidator );
    }

    public function information() {

        $source_file = Files_FileDao::getByJobId( $this->job->id, "source" );
        $target_file = Files_FileDao::getByJobId( $this->job->id, "target" );

        $information = [
                'job_name'        => $this->project->name,
                'source_lang'     => $this->job->source,
                'target_lang'     => $this->job->target,
                'source_filename' => $source_file->filename,
                'target_filename' => $target_file->filename
        ];


        return $this->response->json( $information );

    }

    public function checkProgress() {

        $id_job = $this->job->id;
        $job    = $this->job;

        $status_analysis = ( !empty($job) ) ? $job['status_analysis'] : ConstantsJobAnalysis::ALIGN_PHASE_0;

        $progress = ( !empty($job) ) ? $job['progress'] : ConstantsJobAnalysis::ALIGN_PHASE_0;

        $segmentDao = new Segments_SegmentDao;

        $source_segments = null;
        $target_segments = null;

        switch ( $status_analysis ){
            case ConstantsJobAnalysis::ALIGN_PHASE_0:
                $phase = 0;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_1:
                $phase = 1;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_2:
                $phase = 2;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_3:
                $phase = 3;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_4:
                $phase = 4;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_5:
                $phase = 5;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_6:
                $phase = 6;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_7:
                $phase = 7;
                break;
            case ConstantsJobAnalysis::ALIGN_PHASE_8:
                throw new ValidationError("Max words limit exceeded");
                break;
        }

        switch ( $status_analysis ) {
            case ConstantsJobAnalysis::ALIGN_PHASE_2:
            case ConstantsJobAnalysis::ALIGN_PHASE_3:
            case ConstantsJobAnalysis::ALIGN_PHASE_4:
            case ConstantsJobAnalysis::ALIGN_PHASE_5:
            case ConstantsJobAnalysis::ALIGN_PHASE_6:
            case ConstantsJobAnalysis::ALIGN_PHASE_7:
                $source_segments = $segmentDao->countByJobId($id_job, 'source', 3600);
                $source_segments = ( !empty( $source_segments ) ) ? $source_segments[0]['amount'] : null;
                $target_segments = $segmentDao->countByJobId($id_job, 'target', 3600);
                $target_segments = ( !empty( $target_segments ) ) ? $target_segments[0]['amount'] : null;
                break;
        }


        return $this->response->json( [ 'phase' => $phase,
                                        'phase_name' => $status_analysis,
                                        'progress' => $progress,
                                        'source_segments' => $source_segments,
                                        'target_segments' => $target_segments ]
        );
    }
}