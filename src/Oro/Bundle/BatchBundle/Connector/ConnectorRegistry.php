<?php

namespace Oro\Bundle\BatchBundle\Connector;

use Oro\Bundle\BatchBundle\Job\JobInterface;
use Oro\Bundle\BatchBundle\Entity\JobInstance;
use Oro\Bundle\BatchBundle\Job\JobFactory;
use Oro\Bundle\BatchBundle\Step\StepFactory;

/**
 * Aims to register all connectors
 *
 */
class ConnectorRegistry
{
    protected $jobs = array();
    protected $jobFactory;
    protected $stepFactory;

    /**
     * Constructor
     * @param JobFactory  $jobFactory
     * @param StepFactory $stepFactory
     */
    public function __construct(JobFactory $jobFactory, StepFactory $stepFactory)
    {
        $this->jobFactory = $jobFactory;
        $this->stepFactory = $stepFactory;
    }

    /**
     * Get a registered job definition from a JobInstance
     *
     * @param Oro\Bundle\BatchBundle\Entity\JobInstance $jobInstance
     *
     * @return Oro\Bundle\BatchBundle\Job\JobInterface
     */
    public function getJob(JobInstance $jobInstance)
    {
        if ($connector = $this->getConnector($jobInstance->getConnector(), $jobInstance->getType())) {
            if ($job = $this->getConnectorJob($connector, $jobInstance->getAlias())) {
                $job->setConfiguration($jobInstance->getRawConfiguration());
                $jobInstance->setJob($job);

                return $job;
            }
        }
    }

    /**
     * Get the list of jobs
     * @param string $type
     *
     * @return multitype:JobInterface
     *
     * TODO : Rather return an array of array of JobInterface ?
     */
    public function getJobs($type)
    {
        return $this->jobs[$type];
    }

    /**
     * Add a step to an existig job (or create it)
     *
     * @param string                 $jobConnector
     * @param string                 $jobType
     * @param string                 $jobAlias
     * @param string                 $jobTitle
     * @param string                 $stepTitle
     * @param ItemReaderInterface    $stepReader
     * @param ItemProcessorInterface $stepProcessor
     * @param ItemWriterInterface    $stepWriter
     *
     * @return null
     */
    public function addStepToJob(
        $jobConnector,
        $jobType,
        $jobAlias,
        $jobTitle,
        $stepTitle,
        $stepReader,
        $stepProcessor,
        $stepWriter
    ) {
        if (!isset($this->jobs[$jobType][$jobConnector][$jobAlias])) {
            $this->jobs[$jobType][$jobConnector][$jobAlias] = $this->jobFactory->createJob($jobTitle);
        }

        $this->jobs[$jobType][$jobConnector][$jobAlias]->addStep(
            $this->stepFactory->createStep($stepTitle, $stepReader, $stepProcessor, $stepWriter)
        );
    }

    /**
     * @param string $connector
     * @param string $type
     *
     * @return mixed
     * TODO : Return mixed.. string or null ?
     */
    private function getConnector($connector, $type)
    {
        return isset($this->jobs[$type][$connector]) ? $this->jobs[$type][$connector] : null;
    }

    /**
     * @param array  $connector
     * @param string $jobAlias
     *
     * @return mixed
     * TODO : Return mixed.. string or null ?
     */
    private function getConnectorJob($connector, $jobAlias)
    {
        return isset($connector[$jobAlias]) ? $connector[$jobAlias] : null;
    }

    /**
     * Get list of connectors
     *
     * @return array
     */
    public function getConnectors()
    {
        return array_unique(array_keys($this->jobs));
    }
}
