<?php
/**
 * Created by PhpStorm.
 * User: matteopulcrano
 * Date: 28/03/2019
 * Time: 16:41
 */

namespace Features\Aligner\Utils\AsyncTasks\Workers;

include_once \INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

use Exceptions\ValidationError;
use Features\Aligner;
use Features\Aligner\Model\Files_FileDao;
use Features\Aligner\Model\Jobs_JobDao;
use Features\Aligner\Model\Projects_ProjectDao;
use Features\Aligner\Model\NewDatabase;
use Features\Aligner\Model\Segments_SegmentDao;
use Features\Aligner\Model\Segments_SegmentMatchDao;
use Features\Aligner\Utils\Alignment;
use Features\Aligner\Utils\AlignUtils;
use Features\Aligner\Utils\Constants;
use Features\Aligner\Utils\ConstantsJobAnalysis;
use Features\Aligner\Utils\TaskRunner\Commons\AbstractWorker;

class SegmentWorker extends AbstractWorker {
    use Aligner\Utils\ProjectProgress;

    private $id_job;
    private $job;
    private $project;

    public function process( \TaskRunner\Commons\AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_checkDatabaseConnection();
        $this->setRedisClient($this->_queueHandler->getRedisClient());
        $this->_createSegments( $queueElement );
    }

    protected function _checkForReQueueEnd( \TaskRunner\Commons\QueueElement $queueElement ) {

        /**
         *
         * check for loop re-queuing
         */
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {

            $msg = "\n\n Error Set Contribution  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new \TaskRunner\Exceptions\EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }

    protected function _createSegments( \TaskRunner\Commons\QueueElement $queueElement ) {
        $attributes = json_decode( $queueElement->params );

        $this->id_job = $attributes->id_job;
        $this->job    = $attributes->job;
        $this->project = $attributes->project;

        Projects_ProjectDao::updateField($this->project, 'status_analysis', ConstantsJobAnalysis::ALIGN_PHASE_1);


        \Log::doLog('STARTED ALIGN FOR JOB: '.$this->id_job);

        /*$job          = Jobs_JobDao::getById( $this->id_job )[ 0 ];
        $source_file  = Files_FileDao::getByJobId( $this->id_job, "source" );
        $target_file  = Files_FileDao::getByJobId( $this->id_job, "target" );*/

        $source_file  = $attributes->source_file;
        $target_file  = $attributes->target_file;

        $source_lang = $this->job->source;
        $target_lang = $this->job->target;

        $fileStorage = new \FilesStorage();

        try {

            $fileStorage->moveFromCacheToFileDir(
                $source_file->sha1_original_file,
                $this->job->source,
                $source_file->id,
                $source_file->filename
            );

            $fileStorage->moveFromCacheToFileDir(
                $target_file->sha1_original_file,
                $this->job->target,
                $target_file->id,
                $target_file->filename
            );

            $source_segments = $this->_file2segments($source_file, $source_lang);
            $target_segments = $this->_file2segments($target_file, $target_lang);

            $this->_storeSegments($source_segments, "source");
            $this->_storeSegments($target_segments, "target");

        }catch (\Exception $e){
            \Log::doLog($e->getMessage());
            Projects_ProjectDao::updateField($this->project, 'status_analysis', ConstantsJobAnalysis::ALIGN_PHASE_9);
            $this->updateProgress($this->project->id, 0);
        }

        try {
            \WorkerClient::init( new \AMQHandler() );
            \WorkerClient::enqueue( 'ALIGNER_ALIGN_JOB', 'Features\Aligner\Utils\AsyncTasks\Workers\AlignJobWorker', json_encode( $attributes ), [
                'persistent' => \WorkerClient::$_HANDLER->persistent
            ] );
        } catch ( \Exception $e ) {

            # Handle the error, logging, ...
            $output = "**** Align Job Enqueue failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $attributes, true );
            \Log::doLog( $output );
            throw $e;

        }

    }

    /**
     * @param $file
     * @param $lang
     * @return array
     * @throws Exception
     */
    protected function _file2segments($file, $lang) {
        list($date, $sha1) = explode("/", $file->sha1_original_file);

        // Get file content
        try {
            $fileStorage = new \FilesStorage;
            $xliff_file = $fileStorage->getXliffFromCache($sha1, $lang);
            \Log::doLog('Found xliff file ['.$xliff_file.']');
            $xliff_content = file_get_contents($xliff_file);
        } catch ( \Exception $e ) {
            throw new \Exception( "File xliff not found", $e->getCode(), $e );
        }

        // Parse xliff
        try {
            $parser = new \Xliff_Parser;
            $xliff = $parser->Xliff2Array($xliff_content);
            \Log::doLog('Parsed xliff file ['.$xliff_file.']');
        } catch ( \Exception $e ) {
            throw new \Exception( "Error during xliff parsing", $e->getCode(), $e );
        }

        // Checking that parsing went well
        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
            throw new \Exception( "Parsing errors: ".json_encode($xliff[ 'parser-errors' ]), -4 );
        }

        // Creating the Segments
        $segments = array();
        $total_words = 0;

        foreach ( $xliff[ 'files' ] as $xliff_file ) {

            // An xliff can contains multiple files (docx has style, settings, ...) but only some with useful trans-units
            if ( !array_key_exists( 'trans-units', $xliff_file ) ) {
                continue;
            }

            foreach ($xliff_file[ 'trans-units' ] as $trans_unit) {

                // Extract only raw-content
                $unit_items = array_map(function ($item) {
                    return $item['raw-content'];
                }, $trans_unit[ 'seg-source' ]);

                // Build an object with raw-content and clean-content
                $unit_segments = [];
                foreach ($unit_items as $item) {
                    $unit_segment = [
                        'content_raw' => $item,
                        'content_clean' => AlignUtils::_cleanSegment($item, $lang),
                        'raw_word_count' => \CatUtils::segment_raw_word_count($item, $lang)
                    ];

                    if ($unit_segment['raw_word_count'] > 0) {
                        $total_words += $unit_segment['raw_word_count'];
                        $unit_segments[] = $unit_segment;
                    }
                }

                // Append to existing Segments
                $segments = array_merge($segments, $unit_segments);
            }
        }

        $config = Aligner::getConfig();

        if ($total_words > $config["MAX_WORDS_PER_FILE"]){
            Projects_ProjectDao::updateField( $this->project, 'status_analysis', ConstantsJobAnalysis::ALIGN_PHASE_8);
            throw new ValidationError("File exceeded the word limit, job creation canceled");
        }

        return $segments;
    }


    private function _storeSegments($segments, $type){

        $sequenceIds = $this->dbHandler->nextSequence( NewDatabase::SEQ_ID_SEGMENT, count( $segments ) );
        foreach($sequenceIds as $key => $sequenceId){
            $segments[$key]['id'] = $sequenceId;
            $segments[$key]['type'] = $type;
            $segments[$key]['id_job'] = $this->job->id;
            $segments[$key]['content_hash'] = md5($segments[$key]['content_raw']);
        }

        $segmentsDao = new Segments_SegmentDao;
        $segmentsDao->createList( $segments );

    }

}